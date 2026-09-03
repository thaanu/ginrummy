<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Domain\GinRummy\Exceptions\GameRuleException;

/**
 * The rules of Gin Rummy, expressed purely in terms of {@see GameState}.
 *
 * Nothing here touches the database, HTTP or the broadcaster, so every rule can
 * be exercised directly from a test.
 */
final readonly class GinRummyEngine
{
    public const HAND_SIZE = 10;

    public const MINIMUM_PLAYERS = 2;

    public const MAXIMUM_PLAYERS = 4;

    public function __construct(
        private MeldValidator $melds = new MeldValidator,
        private Shuffler $shuffler = new SecureShuffler,
    ) {}

    /**
     * Shuffles a fresh deck, seats the players in the given order and deals.
     * The starting player receives an extra card and therefore opens the game
     * in the discard phase.
     *
     * @param  list<int>  $playerIds  in seating order
     * @param  int|null  $startingPlayerId  chosen at random when omitted
     */
    public function start(array $playerIds, ?int $startingPlayerId = null): GameState
    {
        $count = count($playerIds);

        if ($count < self::MINIMUM_PLAYERS) {
            throw new GameRuleException('At least two players are needed to start.');
        }

        if ($count > self::MAXIMUM_PLAYERS) {
            throw new GameRuleException('A game holds at most four players.');
        }

        if (count(array_unique($playerIds)) !== $count) {
            throw new GameRuleException('Each player may only be seated once.');
        }

        $startingPlayerId ??= $playerIds[random_int(0, $count - 1)];

        if (! in_array($startingPlayerId, $playerIds, true)) {
            throw new GameRuleException('The starting player is not seated in this game.');
        }

        $stock = Deck::standard()->shuffled($this->shuffler)->cards();

        $hands = [];

        foreach ($playerIds as $playerId) {
            $size = $playerId === $startingPlayerId ? self::HAND_SIZE + 1 : self::HAND_SIZE;

            $hands[$playerId] = new Hand(array_splice($stock, -$size));
        }

        return new GameState(
            status: GameStatus::Playing,
            playerOrder: $playerIds,
            hands: $hands,
            stock: $stock,
            discard: [],
            turnPhase: TurnPhase::Discard,
            currentPlayerId: $startingPlayerId,
        );
    }

    public function drawFromStock(GameState $state, int $playerId): GameState
    {
        $this->assertCanAct($state, $playerId, TurnPhase::Draw);

        $state = $this->replenishStockIfEmpty($state);

        if ($state->status === GameStatus::Completed) {
            return $state;
        }

        $stock = $state->stock;
        $card = array_pop($stock);

        if ($card === null) {
            throw GameRuleException::stockExhausted();
        }

        return $state
            ->withPiles($stock, $state->discard)
            ->withHand($playerId, $state->handFor($playerId)->add($card))
            ->withTurn($playerId, TurnPhase::Discard);
    }

    public function drawFromDiscard(GameState $state, int $playerId): GameState
    {
        $this->assertCanAct($state, $playerId, TurnPhase::Draw);

        $discard = $state->discard;
        $card = array_pop($discard);

        if ($card === null) {
            throw GameRuleException::discardPileEmpty();
        }

        return $state
            ->withPiles($state->stock, $discard)
            ->withHand($playerId, $state->handFor($playerId)->add($card))
            ->withTurn($playerId, TurnPhase::Discard);
    }

    public function discard(GameState $state, int $playerId, Card $card): GameState
    {
        $this->assertCanAct($state, $playerId, TurnPhase::Discard);

        $hand = $state->handFor($playerId)->remove($card);

        return $state
            ->withHand($playerId, $hand)
            ->withPiles($state->stock, [...$state->discard, $card])
            ->withTurn($state->playerAfter($playerId), TurnPhase::Draw);
    }

    /**
     * Claims a win. A player holding eleven cards must name the card they are
     * putting down; the remaining ten are then validated. The declaration is
     * all or nothing, so a rejected claim leaves the game exactly as it was.
     *
     * @throws GameRuleException when the resulting hand does not fully meld.
     */
    public function declareDone(GameState $state, int $playerId, ?Card $discarding = null): GameState
    {
        if (! $state->isPlaying()) {
            throw GameRuleException::gameNotPlaying();
        }

        if (! $state->isCurrentPlayer($playerId)) {
            throw GameRuleException::notYourTurn();
        }

        $hand = $state->handFor($playerId);

        if ($state->turnPhase === TurnPhase::Discard) {
            if ($discarding === null) {
                throw new GameRuleException('Select the card you are putting down before going gin.');
            }

            $hand = $hand->remove($discarding);
        }

        if ($hand->count() !== self::HAND_SIZE) {
            throw GameRuleException::invalidWinningHand();
        }

        if (! $this->melds->isWinningHand($hand->cards())) {
            throw GameRuleException::invalidWinningHand();
        }

        $discardPile = $discarding === null
            ? $state->discard
            : [...$state->discard, $discarding];

        return $state
            ->withHand($playerId, $hand)
            ->withPiles($state->stock, $discardPile)
            ->completedWith($playerId);
    }

    /**
     * Rearranges a player's own cards.
     *
     * Allowed at any point during play, whoever's turn it is, because the order
     * of a hand has no effect on the game.
     *
     * @param  list<Card>  $order
     */
    public function sortHand(GameState $state, int $playerId, array $order): GameState
    {
        if (! $state->isPlaying()) {
            throw GameRuleException::gameNotPlaying();
        }

        if (! array_key_exists($playerId, $state->hands)) {
            throw new GameRuleException('You are not seated at this table.');
        }

        return $state->withHand($playerId, $state->handFor($playerId)->reorder($order));
    }

    /**
     * The melds these cards currently form, covering as many of them as
     * possible. Used to show a player what they have without telling them what
     * to do with it.
     *
     * @param  list<Card>  $cards
     * @return list<list<Card>>
     */
    public function meldsIn(array $cards): array
    {
        return $this->melds->bestMelds($cards);
    }

    /**
     * Which of these cards a player could put down to go gin, given they are
     * holding one more than a full hand.
     *
     * @param  list<Card>  $cards
     * @return list<Card>
     */
    public function winningDiscards(array $cards): array
    {
        if (count($cards) !== self::HAND_SIZE + 1) {
            return [];
        }

        $winners = [];

        foreach ($cards as $index => $card) {
            $remaining = $cards;
            unset($remaining[$index]);

            if ($this->melds->isWinningHand(array_values($remaining))) {
                $winners[] = $card;
            }
        }

        return $winners;
    }

    /**
     * @param  list<Card>  $cards
     */
    public function isWinningHand(array $cards): bool
    {
        return count($cards) === self::HAND_SIZE && $this->melds->isWinningHand($cards);
    }

    /**
     * @param  list<Card>  $cards
     * @return list<list<Card>>|null
     */
    public function findMelds(array $cards): ?array
    {
        return $this->melds->findMelds($cards);
    }

    public function canDraw(GameState $state, int $playerId): bool
    {
        return $state->isPlaying()
            && $state->isCurrentPlayer($playerId)
            && $state->turnPhase === TurnPhase::Draw;
    }

    public function canDiscard(GameState $state, int $playerId): bool
    {
        return $state->isPlaying()
            && $state->isCurrentPlayer($playerId)
            && $state->turnPhase === TurnPhase::Discard;
    }

    /**
     * When the stock runs out, everything below the top discard is shuffled
     * back into a new stock. If that leaves nothing to draw, the game ends
     * without a winner rather than deadlocking.
     */
    private function replenishStockIfEmpty(GameState $state): GameState
    {
        if ($state->stock !== []) {
            return $state;
        }

        $discard = $state->discard;
        $top = array_pop($discard);

        if ($discard === []) {
            return $state->completedWith(null);
        }

        return $state->withPiles(
            $this->shuffler->shuffle($discard),
            $top === null ? [] : [$top],
        );
    }

    private function assertCanAct(GameState $state, int $playerId, TurnPhase $phase): void
    {
        if (! $state->isPlaying()) {
            throw GameRuleException::gameNotPlaying();
        }

        if (! $state->isCurrentPlayer($playerId)) {
            throw GameRuleException::notYourTurn();
        }

        if ($state->turnPhase !== $phase) {
            throw GameRuleException::wrongPhase($phase->value);
        }
    }
}
