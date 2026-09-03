<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    /**
     * The player this browser is acting as in the current game, if any.
     */
    protected function currentPlayer(Request $request): ?Player
    {
        $player = $request->attributes->get('player');

        return $player instanceof Player ? $player : null;
    }

    /**
     * @throws HttpException when the request carries no valid identity.
     */
    protected function requirePlayer(Request $request): Player
    {
        $player = $this->currentPlayer($request);

        abort_if($player === null, 403, 'You are not seated at this table.');

        return $player;
    }
}
