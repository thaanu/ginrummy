<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use App\Domain\GinRummy\Exceptions\GameRuleException;

/**
 * An immutable collection of the cards a single player holds.
 */
final readonly class Hand
{
    /**
     * @param  list<Card>  $cards
     */
    public function __construct(private array $cards = []) {}

    /**
     * @param  iterable<int, string>  $codes
     */
    public static function fromCodes(iterable $codes): self
    {
        return new self(Card::fromCodes($codes));
    }

    public function add(Card $card): self
    {
        return new self([...$this->cards, $card]);
    }

    /**
     * @throws GameRuleException when the card is not held.
     */
    public function remove(Card $card): self
    {
        foreach ($this->cards as $index => $held) {
            if ($held->is($card)) {
                $remaining = $this->cards;
                unset($remaining[$index]);

                return new self(array_values($remaining));
            }
        }

        throw GameRuleException::cardNotInHand();
    }

    /**
     * Rearranges the same cards into the given order.
     *
     * Order carries no meaning in the rules; it is purely how the player likes
     * to look at their hand. Any order that is not a rearrangement of exactly
     * these cards is rejected, so this can never add, drop or swap a card.
     *
     * @param  list<Card>  $order
     *
     * @throws GameRuleException when the order is not the same set of cards.
     */
    public function reorder(array $order): self
    {
        $held = $this->codes();
        $wanted = Card::toCodes($order);

        sort($held);
        sort($wanted);

        if ($held !== $wanted) {
            throw new GameRuleException('That is not the hand you are holding.');
        }

        return new self($order);
    }

    public function contains(Card $card): bool
    {
        foreach ($this->cards as $held) {
            if ($held->is($card)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Card>
     */
    public function cards(): array
    {
        return $this->cards;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return Card::toCodes($this->cards);
    }

    public function count(): int
    {
        return count($this->cards);
    }
}
