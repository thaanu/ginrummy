<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use Illuminate\Support\Facades\Log;

/**
 * Appends to the game's audit trail and writes a matching application log line.
 *
 * Payloads are for debugging and replay, so they may contain card identities;
 * they are never sent to a browser. Session tokens are never recorded.
 */
final class GameJournal
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(Game $game, ?Player $actor, GameEventType $type, array $payload = []): GameEvent
    {
        $event = GameEvent::create([
            'game_id' => $game->id,
            'player_id' => $actor?->id,
            'event_type' => $type,
            'payload' => $payload,
            'sequence_number' => $this->nextSequenceNumber($game),
        ]);

        Log::info('gin-rummy.'.$type->value, [
            'game_id' => $game->id,
            'game_code' => $game->code,
            'player_id' => $actor?->id,
            'status' => $game->status->value,
            'turn_phase' => $game->turn_phase?->value,
            'current_player_id' => $game->current_player_id,
            'sequence_number' => $event->sequence_number,
        ]);

        return $event;
    }

    private function nextSequenceNumber(Game $game): int
    {
        return (int) GameEvent::query()->where('game_id', $game->id)->max('sequence_number') + 1;
    }
}
