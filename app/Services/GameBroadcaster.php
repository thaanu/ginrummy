<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Events\GameStateChanged;
use App\Events\PlayerHandChanged;
use App\Models\Game;
use App\Models\Player;
use Closure;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Pushes state to the table. Public state goes to everyone; a hand only ever
 * goes to the player holding it.
 *
 * Broadcasting is a convenience, not the source of truth. The move has already
 * been committed by the time we get here, so an unreachable WebSocket server
 * must never turn a successful move into a failed request: it is logged, and
 * clients pick the change up from their next action response or heartbeat.
 */
final class GameBroadcaster
{
    public function state(Game $game, GameEventType $reason): void
    {
        $this->send(
            fn () => GameStateChanged::dispatch($game, $reason),
            ['game_id' => $game->id, 'game_code' => $game->code, 'channel' => 'table'],
        );
    }

    public function hands(Player ...$players): void
    {
        foreach ($players as $player) {
            $this->send(
                fn () => PlayerHandChanged::dispatch($player),
                ['game_id' => $player->game_id, 'player_id' => $player->id, 'channel' => 'hand'],
            );
        }
    }

    public function allHands(Game $game): void
    {
        $this->hands(...$game->players->all());
    }

    /**
     * @param  Closure(): mixed  $dispatch
     * @param  array<string, mixed>  $context
     */
    private function send(Closure $dispatch, array $context): void
    {
        try {
            $dispatch();
        } catch (BroadcastException $exception) {
            Log::warning('gin-rummy.broadcast_failed', [
                ...$context,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
