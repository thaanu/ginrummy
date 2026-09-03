<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Domain\GinRummy\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameJournal;
use App\Services\PlayerIdentity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Opens a new table and seats its host.
 */
final readonly class CreateGame
{
    private const CODE_LENGTH = 8;

    private const CODE_ATTEMPTS = 25;

    public function __construct(
        private PlayerIdentity $identity,
        private GameJournal $journal,
    ) {}

    /**
     * @return array{game: Game, player: Player, token: string}
     */
    public function handle(string $nickname): array
    {
        $token = $this->identity->issueToken();

        /** @var array{game: Game, player: Player} $result */
        $result = DB::transaction(function () use ($nickname, $token): array {
            $game = Game::create([
                'code' => $this->generateCode(),
                'status' => GameStatus::Waiting,
                'last_activity_at' => now(),
            ]);

            $player = Player::create([
                'game_id' => $game->id,
                'nickname' => $nickname,
                'seat_number' => 1,
                'session_token_hash' => Player::hashToken($token),
                'is_host' => true,
                'hand' => [],
                'last_seen_at' => now(),
            ]);

            $game->forceFill(['host_player_id' => $player->id])->save();
            $game->setRelation('players', collect([$player]));

            $this->journal->record($game, $player, GameEventType::GameCreated, [
                'nickname' => $player->nickname,
            ]);

            return ['game' => $game, 'player' => $player];
        });

        return [...$result, 'token' => $token];
    }

    /**
     * A human friendly code that is unique among games that still exist. It is
     * drawn from the CSPRNG rather than derived from a database id, so codes
     * cannot be enumerated.
     */
    private function generateCode(): string
    {
        for ($attempt = 0; $attempt < self::CODE_ATTEMPTS; $attempt++) {
            $code = (string) random_int(10 ** (self::CODE_LENGTH - 1), (10 ** self::CODE_LENGTH) - 1);

            if (! Game::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to allocate a unique game code.');
    }
}
