<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Player;

it('frees the seat when a player leaves a lobby', function (): void {
    $game = Game::factory()->create();
    seatPlayer($game, 'Ahmed', 1, host: true);
    [$john, $johnToken] = seatPlayer($game, 'John', 2);

    asPlayer($game->code, $johnToken)
        ->post(route('games.leave', $game->code))
        ->assertRedirect(route('home'));

    expect(Player::find($john->id))->toBeNull()
        ->and($game->fresh()->players()->count())->toBe(1);
});

it('hands the room to the next seat when the host leaves', function (): void {
    $game = Game::factory()->create();
    [$ahmed, $ahmedToken] = seatPlayer($game, 'Ahmed', 1, host: true);
    [$john] = seatPlayer($game, 'John', 2);

    asPlayer($game->code, $ahmedToken)->post(route('games.leave', $game->code));

    expect($game->fresh()->host_player_id)->toBe($john->id)
        ->and($john->fresh()->is_host)->toBeTrue();
});

it('keeps the seat of a player who leaves a game in progress', function (): void {
    $game = Game::factory()->playing()->create();
    [$ahmed, $ahmedToken] = seatPlayer($game, 'Ahmed', 1, host: true);
    seatPlayer($game, 'John', 2);

    asPlayer($game->code, $ahmedToken)->post(route('games.leave', $game->code))->assertRedirect(route('home'));

    expect(Player::find($ahmed->id))->not->toBeNull();
});

it('clears the browser identity on the way out', function (): void {
    $game = Game::factory()->create();
    [, $ahmedToken] = seatPlayer($game, 'Ahmed', 1, host: true);
    seatPlayer($game, 'John', 2);

    $response = asPlayer($game->code, $ahmedToken)->post(route('games.leave', $game->code));

    $cookie = $response->getCookie(config('ginrummy.identity_cookie'));

    expect($cookie)->not->toBeNull()
        ->and(json_decode((string) $cookie?->getValue(), true))->toBe([]);
});
