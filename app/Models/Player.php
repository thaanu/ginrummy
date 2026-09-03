<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A temporary identity that exists only for the lifetime of a single game.
 *
 * Players are never authenticated by id or nickname. The raw session token is
 * generated once, handed to the browser in a signed, http-only cookie, and only
 * ever stored here as a SHA-256 hash.
 *
 * @property int $id
 * @property int $game_id
 * @property string $nickname
 * @property int $seat_number
 * @property string $session_token_hash
 * @property bool $is_host
 * @property list<string>|null $hand
 * @property Carbon|null $last_seen_at
 * @property-read Game $game
 *
 * @method static PlayerFactory factory($count = null, $state = [])
 */
class Player extends Model implements Authenticatable
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    /**
     * A player is considered connected while they have been seen recently.
     */
    public const PRESENCE_WINDOW_SECONDS = 45;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['session_token_hash', 'hand'];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isConnected(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::PRESENCE_WINDOW_SECONDS));
    }

    public function cardCount(): int
    {
        return count($this->hand ?? []);
    }

    public function touchPresence(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    /**
     * The remaining methods satisfy Laravel's Authenticatable contract, which
     * broadcasting requires to authorize a private channel. There are no
     * passwords and no "remember me" here: identity comes from the token hash
     * and nothing else.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'session_token_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->session_token_hash;
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // Sessions are not remembered; a player's identity lives in their cookie.
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hand' => 'array',
            'is_host' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
