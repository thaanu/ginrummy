<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Games\JoinGame;
use App\Domain\GinRummy\GinRummyEngine;
use App\Http\Requests\NicknameRequest;
use App\Models\Game;
use App\Services\PlayerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function __construct(private readonly PlayerIdentity $identity) {}

    public function show(Request $request, Game $game): Response|RedirectResponse
    {
        if ($this->currentPlayer($request) !== null) {
            return to_route('games.show', $game);
        }

        return Inertia::render('Join', [
            'code' => $game->code,
            'status' => $game->status->value,
            'playerCount' => $game->players()->count(),
            'openSeats' => $this->openSeats($game),
        ]);
    }

    public function store(NicknameRequest $request, Game $game, JoinGame $joinGame): RedirectResponse
    {
        if ($this->currentPlayer($request) !== null) {
            return to_route('games.show', $game);
        }

        ['token' => $token] = $joinGame->handle($game, $request->nickname());

        Cookie::queue($this->identity->rememberCookie($request, $game->code, $token));

        return to_route('games.show', $game);
    }

    private function openSeats(Game $game): int
    {
        return max(0, GinRummyEngine::MAXIMUM_PLAYERS - $game->players()->count());
    }
}
