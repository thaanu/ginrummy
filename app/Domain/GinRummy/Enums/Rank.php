<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum Rank: string
{
    case Ace = 'A';
    case Two = '2';
    case Three = '3';
    case Four = '4';
    case Five = '5';
    case Six = '6';
    case Seven = '7';
    case Eight = '8';
    case Nine = '9';
    case Ten = '10';
    case Jack = 'J';
    case Queen = 'Q';
    case King = 'K';

    /**
     * Ordinal position used for runs. The ace is always low: A-2-3 is a run,
     * Q-K-A is not.
     */
    public function order(): int
    {
        return match ($this) {
            self::Ace => 1,
            self::Two => 2,
            self::Three => 3,
            self::Four => 4,
            self::Five => 5,
            self::Six => 6,
            self::Seven => 7,
            self::Eight => 8,
            self::Nine => 9,
            self::Ten => 10,
            self::Jack => 11,
            self::Queen => 12,
            self::King => 13,
        };
    }
}
