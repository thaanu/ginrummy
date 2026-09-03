<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\GinRummy\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Console\Command;

/**
 * Deletes abandoned and finished games. Players and events cascade with them.
 */
class PruneGames extends Command
{
    protected $signature = 'games:prune';

    protected $description = 'Delete abandoned lobbies and finished games that are past their retention window';

    public function handle(): int
    {
        $deleted = 0;

        foreach (GameStatus::cases() as $status) {
            $hours = (int) config("ginrummy.ttl.{$status->value}");

            if ($hours <= 0) {
                continue;
            }

            $deleted += Game::query()
                ->where('status', $status)
                ->where('last_activity_at', '<', now()->subHours($hours))
                ->delete();
        }

        $this->info("Pruned {$deleted} game(s).");

        return self::SUCCESS;
    }
}
