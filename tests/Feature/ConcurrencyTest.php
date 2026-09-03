<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\TurnPhase;
use App\Models\Game;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->game = Game::factory()->playing()->create();
    [$this->ahmed, $this->ahmedToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->john, $this->johnToken] = seatPlayer($this->game, 'John', 2);

    $this->ahmed->forceFill(['hand' => ['3H', '4H']])->save();
    $this->john->forceFill(['hand' => ['9C']])->save();

    $this->game->forceFill([
        'turn_phase' => TurnPhase::Draw,
        'current_player_id' => $this->ahmed->id,
        'player_order' => [$this->ahmed->id, $this->john->id],
        'stock' => ['2D', '7S'],
        'discard' => ['KH'],
    ])->save();
});

it('gives a double clicked draw exactly one card', function (): void {
    $code = $this->game->code;

    asPlayer($code, $this->ahmedToken)->postJson(route('games.draw', $code), ['source' => 'stock'])->assertOk();
    asPlayer($code, $this->ahmedToken)->postJson(route('games.draw', $code), ['source' => 'stock'])->assertStatus(422);

    expect($this->ahmed->fresh()->hand)->toBe(['3H', '4H', '7S'])
        ->and($this->game->fresh()->stock)->toBe(['2D']);
});

it('refuses to act while another request holds the table lock', function (): void {
    config()->set('ginrummy.lock.wait_seconds', 0);

    $lock = Cache::lock("gin-rummy:game:{$this->game->id}", 10);
    $lock->get();

    try {
        asPlayer($this->game->code, $this->ahmedToken)
            ->postJson(route('games.draw', $this->game->code), ['source' => 'stock'])
            ->assertStatus(422)
            ->assertJson(['message' => 'That table is busy right now. Please try again.']);
    } finally {
        $lock->release();
    }

    expect($this->ahmed->fresh()->hand)->toBe(['3H', '4H']);
});

it('starts the game only once even when the host clicks twice', function (): void {
    $waiting = Game::factory()->create();
    [, $token] = seatPlayer($waiting, 'Host', 1, host: true);
    seatPlayer($waiting, 'Guest', 2);

    asPlayer($waiting->code, $token)->postJson(route('games.start', $waiting->code))->assertOk();

    $dealt = $waiting->fresh(['players'])->players->map(fn ($player): array => $player->hand);

    asPlayer($waiting->code, $token)->postJson(route('games.start', $waiting->code))->assertStatus(422);

    expect($waiting->fresh(['players'])->players->map(fn ($player): array => $player->hand))->toEqual($dealt);
});

it('accepts only the first of two winning declarations', function (): void {
    $winning = ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'];

    $this->ahmed->forceFill(['hand' => [...$winning, 'KS']])->save();
    $this->game->forceFill(['turn_phase' => TurnPhase::Discard])->save();

    $code = $this->game->code;

    asPlayer($code, $this->ahmedToken)->postJson(route('games.declare', $code), ['card' => 'KS'])->assertOk();
    asPlayer($code, $this->ahmedToken)->postJson(route('games.declare', $code), ['card' => 'KS'])->assertStatus(422);

    expect($this->game->fresh()->winner_player_id)->toBe($this->ahmed->id);
});
