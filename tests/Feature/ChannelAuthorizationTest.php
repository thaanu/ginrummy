<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    // The null broadcaster the rest of the suite uses skips authorization
    // entirely, so these tests swap in the real Reverb broadcaster and
    // re-register the channel callbacks against it.
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app-id');

    require base_path('routes/channels.php');

    $this->game = Game::factory()->playing()->create();
    [$this->ahmed, $this->ahmedToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->john, $this->johnToken] = seatPlayer($this->game, 'John', 2);
});

function authorizeChannel(string $channel, ?string $gameCode = null, ?string $token = null): TestResponse
{
    $request = $gameCode !== null && $token !== null
        ? asPlayer($gameCode, $token)
        : test();

    return $request->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

it('lets a seated player listen to their own table', function (): void {
    authorizeChannel("private-game.{$this->game->code}", $this->game->code, $this->ahmedToken)
        ->assertOk();
});

it('lets a player listen to their own hand channel', function (): void {
    authorizeChannel(
        "private-game.{$this->game->code}.player.{$this->ahmed->id}",
        $this->game->code,
        $this->ahmedToken,
    )->assertOk();
});

it('refuses to let one player listen to another player\'s hand', function (): void {
    authorizeChannel(
        "private-game.{$this->game->code}.player.{$this->john->id}",
        $this->game->code,
        $this->ahmedToken,
    )->assertForbidden();
});

it('refuses an anonymous listener', function (): void {
    authorizeChannel("private-game.{$this->game->code}")->assertForbidden();
});

it('refuses a token issued for a different game', function (): void {
    $other = Game::factory()->create();
    [, $strayToken] = seatPlayer($other, 'Stranger', 1, host: true);

    authorizeChannel("private-game.{$this->game->code}", $this->game->code, $strayToken)
        ->assertForbidden();
});
