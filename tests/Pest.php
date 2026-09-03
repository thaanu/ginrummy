<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->withoutVite())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Seats a player at a game and returns the raw token their browser would hold.
 *
 * @return array{0: Player, 1: string}
 */
function seatPlayer(Game $game, string $nickname, int $seat, bool $host = false): array
{
    $token = Str::random(64);

    $player = Player::factory()
        ->for($game)
        ->seat($seat)
        ->withToken($token)
        ->create(['nickname' => $nickname, 'is_host' => $host]);

    if ($host) {
        $game->forceFill(['host_player_id' => $player->id])->save();
    }

    return [$player, $token];
}

/**
 * Continues the current test as the player holding this token, exactly as a
 * browser would: by presenting the identity cookie and nothing else.
 */
function asPlayer(string $gameCode, string $token): TestCase
{
    /** @var TestCase $test */
    $test = test();

    return $test
        ->withCredentials()
        ->withCookie(
            (string) config('ginrummy.identity_cookie'),
            (string) json_encode([$gameCode => $token]),
        );
}
