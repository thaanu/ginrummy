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
use App\Services\PlayerIdentity;

/**
 * Seats a new player in a waiting lobby.
 */
final readonly class JoinGame
{
    public function __construct(
        private GameLock $lock,
        private PlayerIdentity $identity,
        private GameJournal $journal,
        private GameBroadcaster $broadcaster,
    ) {}

    /**
     * @return array{game: Game, player: Player, token: string}
     */
    public function handle(Game $game, string $nickname): array
    {
        $token = $this->identity->issueToken();

        /** @var array{game: Game, player: Player} $result */
        $result = $this->lock->run($game, function (Game $game) use ($nickname, $token): array {
            if ($game->isPlaying()) {
                throw new GameRuleException('This game has already started.');
            }

            if ($game->isCompleted()) {
                throw new GameRuleException('This game has already finished.');
            }

            if ($game->players->count() >= GinRummyEngine::MAXIMUM_PLAYERS) {
                throw new GameRuleException('This game is already full.');
            }

            $taken = $game->players->contains(
                fn (Player $player): bool => mb_strtolower($player->nickname) === mb_strtolower($nickname),
            );

            if ($taken) {
                throw new GameRuleException('That nickname is already taken in this game.');
            }

            $player = Player::create([
                'game_id' => $game->id,
                'nickname' => $nickname,
                'seat_number' => ((int) $game->players->max('seat_number')) + 1,
                'session_token_hash' => Player::hashToken($token),
                'is_host' => false,
                'hand' => [],
                'last_seen_at' => now(),
            ]);

            $game->forceFill(['last_activity_at' => now()])->save();
            $game->setRelation('players', $game->players->push($player));

            $this->journal->record($game, $player, GameEventType::PlayerJoined, [
                'nickname' => $player->nickname,
                'seat_number' => $player->seat_number,
            ]);

            return ['game' => $game, 'player' => $player];
        });

        $this->broadcaster->state($result['game'], GameEventType::PlayerJoined);

        return [...$result, 'token' => $token];
    }
}
