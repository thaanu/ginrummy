<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Domain\GinRummy\GameState;
use App\Domain\GinRummy\GinRummyEngine;
use App\Domain\GinRummy\Hand;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property GameStatus $status
 * @property TurnPhase|null $turn_phase
 * @property int|null $host_player_id
 * @property int|null $current_player_id
 * @property int|null $winner_player_id
 * @property list<string>|null $stock
 * @property list<string>|null $discard
 * @property list<int>|null $player_order
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $last_activity_at
 * @property-read Collection<int, Player> $players
 *
 * @method static GameFactory factory($count = null, $state = [])
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('seat_number');
    }

    /**
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isWaiting(): bool
    {
        return $this->status === GameStatus::Waiting;
    }

    public function isPlaying(): bool
    {
        return $this->status === GameStatus::Playing;
    }

    public function isCompleted(): bool
    {
        return $this->status === GameStatus::Completed;
    }

    public function isFull(): bool
    {
        return $this->players()->count() >= GinRummyEngine::MAXIMUM_PLAYERS;
    }

    /**
     * Builds the domain state from the persisted columns and the loaded hands.
     */
    public function toGameState(): GameState
    {
        $hands = [];

        foreach ($this->players as $player) {
            $hands[$player->id] = Hand::fromCodes($player->hand ?? []);
        }

        return new GameState(
            status: $this->status,
            playerOrder: $this->turnOrder(),
            hands: $hands,
            stock: Card::fromCodes($this->stock ?? []),
            discard: Card::fromCodes($this->discard ?? []),
            turnPhase: $this->turn_phase,
            currentPlayerId: $this->current_player_id,
            winnerPlayerId: $this->winner_player_id,
        );
    }

    /**
     * Turn order once the game has started, falling back to seating order.
     *
     * @return list<int>
     */
    public function turnOrder(): array
    {
        $order = $this->player_order ?? $this->players->pluck('id')->all();

        return array_values(array_map(intval(...), $order));
    }

    /**
     * Writes a domain state back onto the game and its players. Callers are
     * responsible for wrapping this in a transaction.
     */
    public function applyGameState(GameState $state): void
    {
        $wasPlaying = $this->isPlaying();

        $this->fill([
            'status' => $state->status,
            'turn_phase' => $state->turnPhase,
            'current_player_id' => $state->currentPlayerId,
            'winner_player_id' => $state->winnerPlayerId,
            'player_order' => $state->playerOrder,
            'stock' => Card::toCodes($state->stock),
            'discard' => Card::toCodes($state->discard),
            'last_activity_at' => now(),
        ]);

        if ($state->status === GameStatus::Playing && $this->started_at === null) {
            $this->started_at = now();
        }

        if ($state->status === GameStatus::Completed && ($wasPlaying || $this->completed_at === null)) {
            $this->completed_at = now();
        }

        $this->save();

        foreach ($this->players as $player) {
            $codes = $state->handFor($player->id)->codes();

            if ($player->hand !== $codes) {
                $player->forceFill(['hand' => $codes])->save();
            }
        }
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'turn_phase' => TurnPhase::class,
            'stock' => 'array',
            'discard' => 'array',
            'player_order' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
