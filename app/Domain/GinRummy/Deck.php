<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use App\Domain\GinRummy\Enums\Rank;
use App\Domain\GinRummy\Enums\Suit;

/**
 * A standard 52-card deck. The last card of {@see self::cards()} is the top of
 * the pile, so drawing is a pop from the end.
 */
final readonly class Deck
{
    /**
     * @param  list<Card>  $cards
     */
    private function __construct(private array $cards) {}

    /**
     * Every rank in every suit, in a fixed order.
     */
    public static function standard(): self
    {
        $cards = [];

        foreach (Suit::cases() as $suit) {
            foreach (Rank::cases() as $rank) {
                $cards[] = new Card($rank, $suit);
            }
        }

        return new self($cards);
    }

    /**
     * @param  list<Card>  $cards
     */
    public static function of(array $cards): self
    {
        return new self($cards);
    }

    public function shuffled(Shuffler $shuffler): self
    {
        return new self($shuffler->shuffle($this->cards));
    }

    /**
     * @return list<Card>
     */
    public function cards(): array
    {
        return $this->cards;
    }

    public function count(): int
    {
        return count($this->cards);
    }
}
