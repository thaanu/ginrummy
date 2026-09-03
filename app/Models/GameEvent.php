<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\GinRummy\Enums\GameEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only audit trail of everything that happened in a game. It is kept
 * for debugging and for reconstructing a finished game; it is never read back
 * to drive gameplay.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $player_id
 * @property GameEventType $event_type
 * @property array<string, mixed> $payload
 * @property int $sequence_number
 * @property Carbon|null $created_at
 */
class GameEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => GameEventType::class,
            'payload' => 'array',
        ];
    }
}
