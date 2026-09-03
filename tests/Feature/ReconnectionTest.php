<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\TurnPhase;
use App\Models\Game;
use App\Models\Player;

beforeEach(function (): void {
    $this->game = Game::factory()->playing()->create();
    [$this->ahmed, $this->ahmedToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->john, $this->johnToken] = seatPlayer($this->game, 'John', 2);

    $this->ahmed->forceFill(['hand' => ['2H', '5S', 'KD']])->save();
    $this->john->forceFill(['hand' => ['AC', '7D', 'QS']])->save();

    $this->game->forceFill([
        'turn_phase' => TurnPhase::Draw,
        'current_player_id' => $this->ahmed->id,
        'player_order' => [$this->ahmed->id, $this->john->id],
        'stock' => ['9C'],
        'discard' => ['4D'],
    ])->save();
});

it('restores the same seat and hand after a refresh', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->get(route('games.show', $this->game->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Game')
            ->where('playerId', $this->ahmed->id)
            ->where('private.hand', ['2H', '5S', 'KD'])
            ->where('state.currentPlayerId', $this->ahmed->id),
        );
});

it('never puts an opponent hand on the page', function (): void {
    $response = asPlayer($this->game->code, $this->johnToken)
        ->get(route('games.show', $this->game->code));

    expect($response->getContent())->not->toContain('5S')
        ->and($response->getContent())->not->toContain('KD');
});

it('sends a stranger to the join screen rather than the table', function (): void {
    $this->get(route('games.show', $this->game->code))
        ->assertRedirect(route('games.join', $this->game->code));
});

it('rejects an invented token', function (): void {
    asPlayer($this->game->code, str_repeat('a', 64))
        ->get(route('games.show', $this->game->code))
        ->assertRedirect(route('games.join', $this->game->code));
});

it('cannot be impersonated by passing a player id', function (): void {
    $this->get(route('games.show', $this->game->code).'?player_id='.$this->ahmed->id)
        ->assertRedirect(route('games.join', $this->game->code));
});

it('marks a silent player as disconnected and a heartbeat as connected again', function (): void {
    $this->john->forceFill(['last_seen_at' => now()->subMinutes(5)])->save();

    $response = asPlayer($this->game->code, $this->ahmedToken)
        ->get(route('games.show', $this->game->code));

    $players = collect($response->viewData('page')['props']['state']['players']);

    expect($players->firstWhere('id', $this->john->id)['connected'])->toBeFalse();

    asPlayer($this->game->code, $this->johnToken)
        ->postJson(route('games.presence', $this->game->code))
        ->assertOk();

    expect(Player::find($this->john->id)->isConnected())->toBeTrue();
});

it('keeps a browser identity for each game it has joined', function (): void {
    $other = Game::factory()->create();
    [$otherPlayer, $otherToken] = seatPlayer($other, 'Ahmed', 1, host: true);

    test()->withCredentials()->withCookie(
        (string) config('ginrummy.identity_cookie'),
        (string) json_encode([$this->game->code => $this->ahmedToken, $other->code => $otherToken]),
    );

    $this->get(route('games.show', $other->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('playerId', $otherPlayer->id));
});
