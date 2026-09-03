<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\GinRummyEngine;
use App\Models\Game;
use App\Models\Player;

/**
 * Builds the two payload shapes the client is allowed to see.
 *
 * The public payload is safe to send to every seat: it never contains a card
 * anyone is holding. The private payload contains one player's own hand and is
 * only ever delivered to that player.
 */
final readonly class GamePresenter
{
    public function __construct(private GinRummyEngine $engine = new GinRummyEngine) {}

    /**
     * @return array{
     *     code: string,
     *     status: string,
     *     turnPhase: string|null,
     *     hostPlayerId: int|null,
     *     currentPlayerId: int|null,
     *     winnerPlayerId: int|null,
     *     stockCount: int,
     *     discardCount: int,
     *     discardTop: string|null,
     *     winningMelds: list<list<string>>|null,
     *     players: list<array{id: int, nickname: string, seat: int, isHost: bool, cardCount: int, connected: bool}>
     * }
     */
    public function publicState(Game $game): array
    {
        $discard = $game->discard ?? [];

        return [
            'code' => $game->code,
            'status' => $game->status->value,
            'turnPhase' => $game->turn_phase?->value,
            'hostPlayerId' => $game->host_player_id,
            'currentPlayerId' => $game->current_player_id,
            'winnerPlayerId' => $game->winner_player_id,
            'stockCount' => count($game->stock ?? []),
            'discardCount' => count($discard),
            'discardTop' => $discard === [] ? null : $discard[count($discard) - 1],
            'winningMelds' => $this->winningMelds($game),
            'players' => $this->players($game),
        ];
    }

    /**
     * A player's own cards, plus the two things the table needs to know about
     * them: which of them currently form melds, and what it would take to go
     * gin. Both are answered by the engine so the browser never has to know a
     * rule of its own.
     *
     * @return array{
     *     playerId: int,
     *     hand: list<string>,
     *     melds: list<list<string>>,
     *     canGoGin: bool,
     *     ginDiscards: list<string>
     * }
     */
    public function privateState(Player $player): array
    {
        $hand = $player->hand ?? [];
        $cards = Card::fromCodes($hand);

        return [
            'playerId' => $player->id,
            'hand' => $hand,
            'melds' => array_map(
                fn (array $meld): array => Card::toCodes($meld),
                $this->engine->meldsIn($cards),
            ),
            'canGoGin' => $this->engine->isWinningHand($cards),
            'ginDiscards' => Card::toCodes($this->engine->winningDiscards($cards)),
        ];
    }

    /**
     * @return list<array{id: int, nickname: string, seat: int, isHost: bool, cardCount: int, connected: bool}>
     */
    private function players(Game $game): array
    {
        $ordered = $game->players->sortBy(
            fn (Player $player): int => $this->turnPosition($game, $player),
        );

        return array_values($ordered->map(fn (Player $player): array => [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'seat' => $player->seat_number,
            'isHost' => $player->is_host,
            'cardCount' => $player->cardCount(),
            'connected' => $player->isConnected(),
        ])->all());
    }

    /**
     * Seating order before the game starts, turn order once it has.
     */
    private function turnPosition(Game $game, Player $player): int
    {
        $order = $game->player_order ?? [];
        $position = array_search($player->id, $order, true);

        return $position === false ? $player->seat_number : $position;
    }

    /**
     * Once a game is won the winning hand is revealed, grouped into the melds
     * that justified it.
     *
     * @return list<list<string>>|null
     */
    private function winningMelds(Game $game): ?array
    {
        if (! $game->isCompleted() || $game->winner_player_id === null) {
            return null;
        }

        $winner = $game->players->firstWhere('id', $game->winner_player_id);

        if (! $winner instanceof Player) {
            return null;
        }

        $melds = $this->engine->findMelds(Card::fromCodes($winner->hand ?? []));

        if ($melds === null) {
            return null;
        }

        return array_map(fn (array $meld): array => Card::toCodes($meld), $melds);
    }
}
