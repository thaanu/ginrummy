<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Games\DeclareDone;
use App\Actions\Games\DiscardCard;
use App\Actions\Games\DrawCard;
use App\Actions\Games\RecordPresence;
use App\Actions\Games\SortHand;
use App\Actions\Games\StartGame;
use App\Http\Requests\DeclareDoneRequest;
use App\Http\Requests\DiscardRequest;
use App\Http\Requests\DrawRequest;
use App\Http\Requests\SortHandRequest;
use App\Models\Game;
use App\Models\Player;
use App\Services\GamePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every in-game move. Each method does the same four things: authorize the
 * player, hand off to an action, and return that player's own view of the
 * result. All rule enforcement lives in the domain layer.
 */
class GameActionController extends Controller
{
    public function __construct(private readonly GamePresenter $presenter) {}

    public function start(Request $request, Game $game, StartGame $startGame): JsonResponse
    {
        $player = $this->requirePlayer($request);

        return $this->stateFor($startGame->handle($game, $player), $player);
    }

    public function draw(DrawRequest $request, Game $game, DrawCard $drawCard): JsonResponse
    {
        $player = $this->requirePlayer($request);

        return $this->stateFor($drawCard->handle($game, $player, $request->source()), $player);
    }

    public function discard(DiscardRequest $request, Game $game, DiscardCard $discardCard): JsonResponse
    {
        $player = $this->requirePlayer($request);

        return $this->stateFor($discardCard->handle($game, $player, $request->card()), $player);
    }

    public function declare(DeclareDoneRequest $request, Game $game, DeclareDone $declareDone): JsonResponse
    {
        $player = $this->requirePlayer($request);

        return $this->stateFor($declareDone->handle($game, $player, $request->card()), $player);
    }

    public function sort(SortHandRequest $request, Game $game, SortHand $sortHand): JsonResponse
    {
        $player = $this->requirePlayer($request);

        return $this->stateFor($sortHand->handle($game, $player, $request->order()), $player);
    }

    public function presence(Request $request, Game $game, RecordPresence $recordPresence): JsonResponse
    {
        $player = $this->requirePlayer($request);

        $recordPresence->handle($game, $player);

        return $this->stateFor($game->fresh(['players']) ?? $game, $player);
    }

    /**
     * The response mirrors what is broadcast, so a client that misses a
     * WebSocket message still ends up consistent after its next move.
     */
    private function stateFor(Game $game, Player $player): JsonResponse
    {
        $game->loadMissing('players');

        $self = $game->players->firstWhere('id', $player->id) ?? $player->refresh();

        return response()->json([
            'state' => $this->presenter->publicState($game),
            'private' => $this->presenter->privateState($self),
        ]);
    }
}
