<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\GinRummyEngine;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameLock;

/**
 * Rearranges a player's own cards.
 *
 * The order of a hand means nothing to the rules, so this changes no public
 * state and tells nobody else. It still takes the table lock, because it writes
 * to the same column a draw or a discard does.
 *
 * No game event is recorded: the audit trail is for what happened in the game,
 * and tidying your cards is not that.
 */
final readonly class SortHand
{
    public function __construct(
        private GameLock $lock,
        private GinRummyEngine $engine,
    ) {}

    /**
     * @param  list<Card>  $order
     */
    public function handle(Game $game, Player $player, array $order): Game
    {
        return $this->lock->run($game, function (Game $game) use ($player, $order): Game {
            $game->applyGameState(
                $this->engine->sortHand($game->toGameState(), $player->id, $order),
            );

            return $game;
        });
    }
}
