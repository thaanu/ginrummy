<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameBroadcaster;

/**
 * Marks a player as still present.
 *
 * Disconnected players are never removed or replaced; the table simply shows
 * who has gone quiet, and a heartbeat brings them back.
 */
final readonly class RecordPresence
{
    public function __construct(private GameBroadcaster $broadcaster) {}

    public function handle(Game $game, Player $player): void
    {
        $wasConnected = $player->isConnected();

        $player->touchPresence();

        if (! $wasConnected) {
            $this->broadcaster->state($game->fresh(['players']) ?? $game, GameEventType::PlayerReconnected);
        }
    }
}
