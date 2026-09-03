<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum Suit: string
{
    case Hearts = 'H';
    case Diamonds = 'D';
    case Clubs = 'C';
    case Spades = 'S';

    public function label(): string
    {
        return match ($this) {
            self::Hearts => 'Hearts',
            self::Diamonds => 'Diamonds',
            self::Clubs => 'Clubs',
            self::Spades => 'Spades',
        };
    }

    public function isRed(): bool
    {
        return $this === self::Hearts || $this === self::Diamonds;
    }
}
