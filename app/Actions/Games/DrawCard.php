<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Enums\DrawSource;
use App\Domain\GinRummy\Enums\GameEventType;
use App\Domain\GinRummy\GameState;
use App\Domain\GinRummy\GinRummyEngine;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameBroadcaster;
use App\Services\GameJournal;
use App\Services\GameLock;

/**
 * Draws the top card of the stock or of the discard pile into a player's hand.
 */
final readonly class DrawCard
{
    public function __construct(
        private GameLock $lock,
        private GinRummyEngine $engine,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    public function handle(Game $game, Player $player, DrawSource $source): Game
    {
        $updated = $this->lock->run($game, function (Game $game) use ($player, $source): Game {
            $before = $game->toGameState();

            $after = $source === DrawSource::Stock
                ? $this->engine->drawFromStock($before, $player->id)
                : $this->engine->drawFromDiscard($before, $player->id);

            $game->applyGameState($after);

            if (! $after->isPlaying()) {
                $this->journal->record($game, null, GameEventType::GameCompleted, [
                    'reason' => 'no_cards_remaining',
                ]);

                return $game;
            }

            if ($source === DrawSource::Stock && $before->stockCount() === 0) {
                $this->journal->record($game, null, GameEventType::StockReshuffled, [
                    'stock_count' => $after->stockCount(),
                ]);
            }

            $this->journal->record($game, $player, GameEventType::CardDrawn, [
                'source' => $source->value,
                'card' => $this->drawnCard($before, $after, $player->id),
                'stock_count' => $after->stockCount(),
            ]);

            return $game;
        });

        $reason = $updated->isCompleted() ? GameEventType::GameCompleted : GameEventType::CardDrawn;

        $this->broadcaster->state($updated, $reason);
        $this->broadcaster->hands($updated->players->firstWhere('id', $player->id) ?? $player->refresh());

        return $updated;
    }

    /**
     * The card that ended up in the player's hand. Recorded for the audit trail
     * only; it is never broadcast.
     */
    private function drawnCard(GameState $before, GameState $after, int $playerId): ?string
    {
        $added = array_values(array_diff(
            $after->handFor($playerId)->codes(),
            $before->handFor($playerId)->codes(),
        ));

        return $added[0] ?? null;
    }
}
