<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Games\CreateGame;
use App\Actions\Games\LeaveGame;
use App\Http\Requests\NicknameRequest;
use App\Models\Game;
use App\Services\GamePresenter;
use App\Services\PlayerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __construct(
        private readonly PlayerIdentity $identity,
        private readonly GamePresenter $presenter,
    ) {}

    public function store(NicknameRequest $request, CreateGame $createGame): RedirectResponse
    {
        ['game' => $game, 'token' => $token] = $createGame->handle($request->nickname());

        Cookie::queue($this->identity->rememberCookie($request, $game->code, $token));

        return to_route('games.show', $game);
    }

    public function show(Request $request, Game $game): Response|RedirectResponse
    {
        $player = $this->currentPlayer($request);

        if ($player === null) {
            return to_route('games.join', $game->code);
        }

        $game->load('players');

        return Inertia::render('Game', [
            'state' => $this->presenter->publicState($game),
            'private' => $this->presenter->privateState($player),
            'playerId' => $player->id,
            'invitationUrl' => route('games.join', $game->code),
        ]);
    }

    public function destroy(Request $request, Game $game, LeaveGame $leaveGame): RedirectResponse
    {
        $player = $this->requirePlayer($request);

        $leaveGame->handle($game, $player);

        Cookie::queue($this->identity->forgetCookie($request, $game->code));

        return to_route('home');
    }
}
