<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Domain\GinRummy\Exceptions\GameRuleException;
use App\Domain\GinRummy\GinRummyEngine;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameBroadcaster;
use App\Services\GameJournal;
use App\Services\GameLock;

/**
 * Locks the lobby, shuffles, deals and hands the first turn to a randomly
 * chosen player.
 */
final readonly class StartGame
{
    public function __construct(
        private GameLock $lock,
        private GinRummyEngine $engine,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    public function handle(Game $game, Player $host): Game
    {
        $started = $this->lock->run($game, function (Game $game) use ($host): Game {
            if ($game->host_player_id !== $host->id) {
                throw new GameRuleException('Only the host can start this game.');
            }

            if ($game->isPlaying()) {
                throw new GameRuleException('This game has already started.');
            }

            if ($game->isCompleted()) {
                throw new GameRuleException('This game has already finished.');
            }

            if ($game->players->count() < GinRummyEngine::MINIMUM_PLAYERS) {
                throw new GameRuleException('At least two players are needed to start.');
            }

            /** @var list<int> $playerIds */
            $playerIds = $game->players->sortBy('seat_number')->pluck('id')->values()->all();

            $state = $this->engine->start($playerIds);

            $game->applyGameState($state);

            $this->journal->record($game, $host, GameEventType::GameStarted, [
                'player_order' => $state->playerOrder,
                'starting_player_id' => $state->currentPlayerId,
                'stock_count' => $state->stockCount(),
            ]);

            return $game;
        });

        $this->broadcaster->state($started, GameEventType::GameStarted);
        $this->broadcaster->allHands($started);

        return $started;
    }
}
