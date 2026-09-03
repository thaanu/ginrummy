<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'nickname' => fake()->unique()->firstName(),
            'seat_number' => 1,
            'session_token_hash' => Player::hashToken(Str::random(64)),
            'is_host' => false,
            'hand' => [],
            'last_seen_at' => now(),
        ];
    }

    public function host(): static
    {
        return $this->state(fn (): array => ['is_host' => true]);
    }

    public function seat(int $number): static
    {
        return $this->state(fn (): array => ['seat_number' => $number]);
    }

    /**
     * Seats a player whose raw token is known, so a test can act as them.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['session_token_hash' => Player::hashToken($token)]);
    }
}
