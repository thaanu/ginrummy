<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\TurnPhase;
use App\Events\GameStateChanged;
use App\Models\Game;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->game = Game::factory()->playing()->create();
    [$this->ahmed, $this->ahmedToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->john, $this->johnToken] = seatPlayer($this->game, 'John', 2);

    $this->ahmed->forceFill(['hand' => ['9C', '3H', '5H', '4H']])->save();
    $this->john->forceFill(['hand' => ['KD', 'QS']])->save();

    $this->game->forceFill([
        'turn_phase' => TurnPhase::Draw,
        'current_player_id' => $this->john->id,
        'player_order' => [$this->ahmed->id, $this->john->id],
        'stock' => ['2D'],
        'discard' => ['KH'],
    ])->save();
});

it('saves the order a player drags their cards into', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', '9C']])
        ->assertOk()
        ->assertJsonPath('private.hand', ['3H', '4H', '5H', '9C']);

    expect($this->ahmed->fresh()->hand)->toBe(['3H', '4H', '5H', '9C']);
});

it('survives a refresh', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', '9C']]);

    asPlayer($this->game->code, $this->ahmedToken)
        ->get(route('games.show', $this->game->code))
        ->assertInertia(fn ($page) => $page->where('private.hand', ['3H', '4H', '5H', '9C']));
});

it('can be done while waiting for another player', function (): void {
    expect($this->game->current_player_id)->toBe($this->john->id);

    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['9C', '5H', '4H', '3H']])
        ->assertOk();
});

it('tells nobody else, because the order is nobody else\'s business', function (): void {
    Event::fake([GameStateChanged::class]);

    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', '9C']]);

    Event::assertNotDispatched(GameStateChanged::class);
});

it('refuses an order that drops a card', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H']])
        ->assertStatus(422)
        ->assertJson(['message' => 'That is not the hand you are holding.']);

    expect($this->ahmed->fresh()->hand)->toBe(['9C', '3H', '5H', '4H']);
});

it('refuses an order holding a card the player does not have', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', 'AS']])
        ->assertStatus(422);

    expect($this->ahmed->fresh()->hand)->toBe(['9C', '3H', '5H', '4H']);
});

it('cannot be used to duplicate a card', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '3H', '4H', '5H']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('hand.1');

    expect($this->ahmed->fresh()->hand)->toBe(['9C', '3H', '5H', '4H']);
});

it('cannot reach into another player\'s hand', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['KD', 'QS']])
        ->assertStatus(422);

    expect($this->john->fresh()->hand)->toBe(['KD', 'QS']);
});

it('is refused once the game is over', function (): void {
    $this->game->forceFill(['status' => 'completed'])->save();

    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', '9C']])
        ->assertStatus(422)
        ->assertJson(['message' => 'This game is not in play.']);
});

it('is refused without a valid identity', function (): void {
    $this->postJson(route('games.sort', $this->game->code), ['hand' => ['3H', '4H', '5H', '9C']])
        ->assertForbidden();
});
