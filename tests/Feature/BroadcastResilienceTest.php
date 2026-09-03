<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\TurnPhase;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for a WebSocket server that is down.
 */
final class UnreachableBroadcaster implements Broadcaster
{
    public function auth($request): mixed
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        throw new BroadcastException('cURL error 7: Failed to connect to localhost:8080');
    }
}

beforeEach(function (): void {
    Broadcast::extend('unreachable', fn (): Broadcaster => new UnreachableBroadcaster);
    config()->set('broadcasting.connections.unreachable', ['driver' => 'unreachable']);
    config()->set('broadcasting.default', 'unreachable');
});

it('still seats a player when the WebSocket server is down', function (): void {
    $game = Game::factory()->create();
    seatPlayer($game, 'Ahmed', 1, host: true);

    $this->post(route('games.join.store', $game->code), ['nickname' => 'Hussain'])
        ->assertRedirect(route('games.show', $game->code));

    expect(Player::query()->where('nickname', 'Hussain')->exists())->toBeTrue();
});

it('still starts the game when the WebSocket server is down', function (): void {
    $game = Game::factory()->create();
    [, $token] = seatPlayer($game, 'Ahmed', 1, host: true);
    seatPlayer($game, 'John', 2);

    asPlayer($game->code, $token)
        ->postJson(route('games.start', $game->code))
        ->assertOk()
        ->assertJsonPath('state.status', 'playing');
});

it('still applies a move, and reports the new state to the player who made it', function (): void {
    $game = Game::factory()->playing()->create();
    [$ahmed, $token] = seatPlayer($game, 'Ahmed', 1, host: true);
    [$john] = seatPlayer($game, 'John', 2);

    $ahmed->forceFill(['hand' => ['3H', '4H']])->save();
    $john->forceFill(['hand' => ['9C']])->save();
    $game->forceFill([
        'turn_phase' => TurnPhase::Draw,
        'current_player_id' => $ahmed->id,
        'player_order' => [$ahmed->id, $john->id],
        'stock' => ['2D', '7S'],
        'discard' => ['KH'],
    ])->save();

    asPlayer($game->code, $token)
        ->postJson(route('games.draw', $game->code), ['source' => 'stock'])
        ->assertOk()
        ->assertJsonPath('private.hand', ['3H', '4H', '7S']);

    expect($ahmed->fresh()->hand)->toBe(['3H', '4H', '7S']);
});

it('records the failure so an operator can see the server is unreachable', function (): void {
    Log::spy();

    $game = Game::factory()->create();
    seatPlayer($game, 'Ahmed', 1, host: true);

    $this->post(route('games.join.store', $game->code), ['nickname' => 'Hussain']);

    Log::shouldHaveReceived('warning')->with('gin-rummy.broadcast_failed', Mockery::any());
});
