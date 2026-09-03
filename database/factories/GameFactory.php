<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\GinRummy\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(10_000_000, 99_999_999),
            'status' => GameStatus::Waiting,
            'last_activity_at' => now(),
        ];
    }

    public function playing(): static
    {
        return $this->state(fn (): array => [
            'status' => GameStatus::Playing,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => GameStatus::Completed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }
}
