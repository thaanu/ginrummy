<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Game;
use App\Services\PlayerIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the temporary identity the browser holds for the game being visited
 * and attaches it to the request.
 *
 * Nothing here trusts a route parameter or request body: the only thing that
 * can identify a player is the secret token in their cookie.
 */
class IdentifyPlayer
{
    public function __construct(private readonly PlayerIdentity $identity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');

        if ($game instanceof Game) {
            $player = $this->identity->resolve($request, $game);

            if ($player !== null) {
                $request->attributes->set('player', $player);
            }
        }

        return $next($request);
    }
}
