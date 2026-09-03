<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use App\Domain\GinRummy\Enums\Rank;
use App\Domain\GinRummy\Enums\Suit;
use InvalidArgumentException;
use Stringable;

/**
 * An immutable playing card, identified by a short stable code such as "7H",
 * "QS" or "10D".
 */
final readonly class Card implements Stringable
{
    public function __construct(
        public Rank $rank,
        public Suit $suit,
    ) {}

    public static function fromCode(string $code): self
    {
        $code = strtoupper(trim($code));

        $rank = Rank::tryFrom(substr($code, 0, -1));
        $suit = Suit::tryFrom(substr($code, -1));

        if ($rank === null || $suit === null) {
            throw new InvalidArgumentException("Unknown card code [{$code}].");
        }

        return new self($rank, $suit);
    }

    /**
     * @param  iterable<int, string>  $codes
     * @return list<self>
     */
    public static function fromCodes(iterable $codes): array
    {
        $cards = [];

        foreach ($codes as $code) {
            $cards[] = self::fromCode($code);
        }

        return $cards;
    }

    /**
     * @param  iterable<int, self>  $cards
     * @return list<string>
     */
    public static function toCodes(iterable $cards): array
    {
        $codes = [];

        foreach ($cards as $card) {
            $codes[] = $card->code();
        }

        return $codes;
    }

    public function code(): string
    {
        return $this->rank->value.$this->suit->value;
    }

    public function is(self $other): bool
    {
        return $this->rank === $other->rank && $this->suit === $other->suit;
    }

    public function __toString(): string
    {
        return $this->code();
    }
}
