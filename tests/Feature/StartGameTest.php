<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Events\GameStateChanged;
use App\Events\PlayerHandChanged;
use App\Models\Game;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->game = Game::factory()->create();
    [$this->host, $this->hostToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->guest, $this->guestToken] = seatPlayer($this->game, 'John', 2);
});

it('deals the table when the host starts the game', function (): void {
    asPlayer($this->game->code, $this->hostToken)
        ->postJson(route('games.start', $this->game->code))
        ->assertOk();

    $game = $this->game->fresh(['players']);

    expect($game->status)->toBe(GameStatus::Playing)
        ->and($game->turn_phase)->toBe(TurnPhase::Discard)
        ->and($game->started_at)->not->toBeNull()
        ->and($game->player_order)->toBe([$this->host->id, $this->guest->id])
        ->and($game->current_player_id)->toBeIn([$this->host->id, $this->guest->id]);

    $hands = $game->players->mapWithKeys(fn ($player) => [$player->id => count($player->hand)]);

    expect($hands[$game->current_player_id])->toBe(11)
        ->and($hands->sum())->toBe(21)
        ->and(count($game->stock))->toBe(31);
});

it('puts one complete deck into play and nothing more', function (): void {
    asPlayer($this->game->code, $this->hostToken)->postJson(route('games.start', $this->game->code));

    $game = $this->game->fresh(['players']);

    $codes = array_merge(
        $game->stock,
        $game->discard,
        ...$game->players->map(fn ($player): array => $player->hand)->all(),
    );

    expect($codes)->toHaveCount(52)
        ->and(array_unique($codes))->toHaveCount(52);
});

it('broadcasts the new state and every hand privately', function (): void {
    Event::fake([GameStateChanged::class, PlayerHandChanged::class]);

    asPlayer($this->game->code, $this->hostToken)->postJson(route('games.start', $this->game->code));

    Event::assertDispatched(GameStateChanged::class);
    Event::assertDispatchedTimes(PlayerHandChanged::class, 2);
});

it('refuses to start for anyone but the host', function (): void {
    asPlayer($this->game->code, $this->guestToken)
        ->postJson(route('games.start', $this->game->code))
        ->assertStatus(422)
        ->assertJson(['message' => 'Only the host can start this game.']);

    expect($this->game->fresh()->status)->toBe(GameStatus::Waiting);
});

it('refuses to start with fewer than two players', function (): void {
    $solo = Game::factory()->create();
    [, $token] = seatPlayer($solo, 'Alone', 1, host: true);

    asPlayer($solo->code, $token)
        ->postJson(route('games.start', $solo->code))
        ->assertStatus(422)
        ->assertJson(['message' => 'At least two players are needed to start.']);
});

it('refuses to start the same game twice', function (): void {
    asPlayer($this->game->code, $this->hostToken)->postJson(route('games.start', $this->game->code))->assertOk();

    asPlayer($this->game->code, $this->hostToken)
        ->postJson(route('games.start', $this->game->code))
        ->assertStatus(422)
        ->assertJson(['message' => 'This game has already started.']);
});

it('refuses to act without a valid identity', function (): void {
    $this->postJson(route('games.start', $this->game->code))->assertForbidden();
});

it('refuses an identity token belonging to another game', function (): void {
    $other = Game::factory()->create();
    [, $strayToken] = seatPlayer($other, 'Stranger', 1, host: true);

    asPlayer($this->game->code, $strayToken)
        ->postJson(route('games.start', $this->game->code))
        ->assertForbidden();
});
