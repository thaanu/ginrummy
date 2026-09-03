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
 * Puts a card face up on the discard pile and passes the turn along.
 */
final readonly class DiscardCard
{
    public function __construct(
        private GameLock $lock,
        private GinRummyEngine $engine,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    public function handle(Game $game, Player $player, Card $card): Game
    {
        $updated = $this->lock->run($game, function (Game $game) use ($player, $card): Game {
            $state = $this->engine->discard($game->toGameState(), $player->id, $card);

            $game->applyGameState($state);

            $this->journal->record($game, $player, GameEventType::CardDiscarded, [
                'card' => $card->code(),
            ]);

            $this->journal->record($game, $player, GameEventType::TurnChanged, [
                'current_player_id' => $state->currentPlayerId,
            ]);

            return $game;
        });

        $this->broadcaster->state($updated, GameEventType::CardDiscarded);
        $this->broadcaster->hands($updated->players->firstWhere('id', $player->id) ?? $player->refresh());

        return $updated;
    }
}
