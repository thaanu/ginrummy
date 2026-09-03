<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameBroadcaster;
use App\Services\GameJournal;
use App\Services\GameLock;

/**
 * Removes a player from a lobby they have not yet started playing in.
 *
 * Once a game is under way a seat is never vacated, because doing so would
 * strand the cards it holds. Leaving then only clears the browser's identity.
 */
final readonly class LeaveGame
{
    public function __construct(
        private GameLock $lock,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    public function handle(Game $game, Player $player): void
    {
        $updated = $this->lock->run($game, function (Game $game) use ($player): ?Game {
            if (! $game->isWaiting()) {
                return null;
            }

            $game->players->firstWhere('id', $player->id)?->delete();
            $game->setRelation('players', $game->players->reject(
                fn (Player $seated): bool => $seated->id === $player->id,
            )->values());

            $this->promoteNewHostIfNeeded($game, $player);

            $game->forceFill(['last_activity_at' => now()])->save();

            $this->journal->record($game, null, GameEventType::PlayerLeft, [
                'left' => $player->nickname,
                'remaining' => $game->players->count(),
            ]);

            return $game;
        });

        if ($updated instanceof Game) {
            $this->broadcaster->state($updated, GameEventType::PlayerLeft);
        }
    }

    /**
     * The lowest remaining seat inherits the room, so a lobby survives its host
     * walking away.
     */
    private function promoteNewHostIfNeeded(Game $game, Player $leaving): void
    {
        if ($game->host_player_id !== $leaving->id) {
            return;
        }

        $successor = $game->players->sortBy('seat_number')->first();

        if (! $successor instanceof Player) {
            $game->forceFill(['host_player_id' => null])->save();

            return;
        }

        $successor->forceFill(['is_host' => true])->save();
        $game->forceFill(['host_player_id' => $successor->id])->save();
    }
}
