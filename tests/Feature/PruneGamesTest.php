<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Player;

it('deletes lobbies that have been idle past their window', function (): void {
    $stale = Game::factory()->create(['last_activity_at' => now()->subHours(3)]);
    $fresh = Game::factory()->create(['last_activity_at' => now()->subMinutes(30)]);

    $this->artisan('games:prune')->assertSuccessful();

    expect(Game::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and(Game::find($stale->id))->toBeNull();
});

it('keeps a game that is still being played', function (): void {
    $playing = Game::factory()->playing()->create(['last_activity_at' => now()->subHours(3)]);

    $this->artisan('games:prune');

    expect(Game::find($playing->id))->not->toBeNull();
});

it('deletes a finished game once its retention window passes', function (): void {
    $old = Game::factory()->completed()->create(['last_activity_at' => now()->subHours(25)]);
    $recent = Game::factory()->completed()->create(['last_activity_at' => now()->subHours(1)]);

    $this->artisan('games:prune');

    expect(Game::find($old->id))->toBeNull()
        ->and(Game::find($recent->id))->not->toBeNull();
});

it('takes the players and events of a pruned game with it', function (): void {
    $stale = Game::factory()->create(['last_activity_at' => now()->subHours(3)]);
    seatPlayer($stale, 'Ahmed', 1, host: true);

    $this->artisan('games:prune');

    expect(Player::query()->where('game_id', $stale->id)->count())->toBe(0);
});
