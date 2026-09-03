<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\GinRummy\Exceptions\GameRuleException;
use App\Models\Game;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Serialises every mutating action on a single game.
 *
 * Two layers protect the state: an atomic Redis lock keyed on the game, and a
 * database transaction that re-reads the row with `FOR UPDATE`. Together they
 * mean a double-clicked draw can only ever be applied once, and the callback
 * always sees the freshest state.
 */
final class GameLock
{
    /**
     * @template TReturn
     *
     * @param  Closure(Game): TReturn  $callback  receives a freshly locked game
     * @return TReturn
     */
    public function run(Game $game, Closure $callback): mixed
    {
        $lock = Cache::lock(
            "gin-rummy:game:{$game->id}",
            (int) config('ginrummy.lock.seconds'),
        );

        try {
            return $lock->block(
                (int) config('ginrummy.lock.wait_seconds'),
                fn (): mixed => DB::transaction(function () use ($game, $callback): mixed {
                    $fresh = Game::query()
                        ->whereKey($game->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $fresh->load('players');

                    return $callback($fresh);
                }),
            );
        } catch (LockTimeoutException) {
            throw new GameRuleException('That table is busy right now. Please try again.');
        }
    }
}
