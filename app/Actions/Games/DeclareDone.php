<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Enums\GameEventType;
use App\Domain\GinRummy\GinRummyEngine;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameBroadcaster;
use App\Services\GameJournal;
use App\Services\GameLock;

/**
 * Claims a win.
 *
 * The server decides on its own whether the ten cards fully meld; no meld
 * arrangement is accepted from the browser. A rejected claim changes nothing,
 * so the player simply keeps playing.
 */
final readonly class DeclareDone
{
    public function __construct(
        private GameLock $lock,
        private GinRummyEngine $engine,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    public function handle(Game $game, Player $player, ?Card $discarding = null): Game
    {
        $updated = $this->lock->run($game, function (Game $game) use ($player, $discarding): Game {
            $state = $this->engine->declareDone($game->toGameState(), $player->id, $discarding);

            $game->applyGameState($state);

            $this->journal->record($game, $player, GameEventType::PlayerWon, [
                'hand' => $state->handFor($player->id)->codes(),
            ]);

            $this->journal->record($game, $player, GameEventType::GameCompleted, []);

            return $game;
        });

        $this->broadcaster->state($updated, GameEventType::PlayerWon);
        $this->broadcaster->allHands($updated);

        return $updated;
    }
}
