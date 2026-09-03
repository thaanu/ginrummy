<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Domain\GinRummy\Exceptions\GameRuleException;

/**
 * The complete, authoritative state of a single game.
 *
 * Instances are immutable: every transition returns a new state, which keeps
 * the engine free of hidden side effects and means a rejected action leaves the
 * original state untouched.
 *
 * Piles are ordered bottom first, so the last element of {@see $stock} and
 * {@see $discard} is the top card.
 */
final readonly class GameState
{
    /**
     * @param  list<int>  $playerOrder
     * @param  array<int, Hand>  $hands  keyed by player id
     * @param  list<Card>  $stock
     * @param  list<Card>  $discard
     */
    public function __construct(
        public GameStatus $status,
        public array $playerOrder,
        public array $hands,
        public array $stock = [],
        public array $discard = [],
        public ?TurnPhase $turnPhase = null,
        public ?int $currentPlayerId = null,
        public ?int $winnerPlayerId = null,
    ) {}

    public function handFor(int $playerId): Hand
    {
        return $this->hands[$playerId] ?? new Hand;
    }

    public function topOfDiscard(): ?Card
    {
        return $this->discard === [] ? null : $this->discard[count($this->discard) - 1];
    }

    public function stockCount(): int
    {
        return count($this->stock);
    }

    public function isPlaying(): bool
    {
        return $this->status === GameStatus::Playing;
    }

    public function isCurrentPlayer(int $playerId): bool
    {
        return $this->currentPlayerId === $playerId;
    }

    /**
     * The player who acts after the given player, wrapping around the table.
     */
    public function playerAfter(int $playerId): int
    {
        $position = array_search($playerId, $this->playerOrder, true);

        if ($position === false) {
            throw new GameRuleException('That player is not seated in this game.');
        }

        return $this->playerOrder[($position + 1) % count($this->playerOrder)];
    }

    public function withHand(int $playerId, Hand $hand): self
    {
        $hands = $this->hands;
        $hands[$playerId] = $hand;

        return new self(
            status: $this->status,
            playerOrder: $this->playerOrder,
            hands: $hands,
            stock: $this->stock,
            discard: $this->discard,
            turnPhase: $this->turnPhase,
            currentPlayerId: $this->currentPlayerId,
            winnerPlayerId: $this->winnerPlayerId,
        );
    }

    /**
     * @param  list<Card>  $stock
     * @param  list<Card>  $discard
     */
    public function withPiles(array $stock, array $discard): self
    {
        return new self(
            status: $this->status,
            playerOrder: $this->playerOrder,
            hands: $this->hands,
            stock: $stock,
            discard: $discard,
            turnPhase: $this->turnPhase,
            currentPlayerId: $this->currentPlayerId,
            winnerPlayerId: $this->winnerPlayerId,
        );
    }

    public function withTurn(?int $currentPlayerId, ?TurnPhase $turnPhase): self
    {
        return new self(
            status: $this->status,
            playerOrder: $this->playerOrder,
            hands: $this->hands,
            stock: $this->stock,
            discard: $this->discard,
            turnPhase: $turnPhase,
            currentPlayerId: $currentPlayerId,
            winnerPlayerId: $this->winnerPlayerId,
        );
    }

    /**
     * Ends the game. A null winner marks a game that could not be finished, for
     * example because no cards remain to draw.
     */
    public function completedWith(?int $winnerPlayerId): self
    {
        return new self(
            status: GameStatus::Completed,
            playerOrder: $this->playerOrder,
            hands: $this->hands,
            stock: $this->stock,
            discard: $this->discard,
            turnPhase: null,
            currentPlayerId: null,
            winnerPlayerId: $winnerPlayerId,
        );
    }

    /**
     * Every card currently accounted for anywhere in the game. The test suite
     * uses this to prove cards are never duplicated or lost.
     *
     * @return list<string>
     */
    public function allCardCodes(): array
    {
        $codes = [...Card::toCodes($this->stock), ...Card::toCodes($this->discard)];

        foreach ($this->hands as $hand) {
            $codes = [...$codes, ...$hand->codes()];
        }

        return $codes;
    }
}
