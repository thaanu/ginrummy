<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested action is not legal for the current game state.
 *
 * The message is always safe to show to a player: it never contains internal
 * details, card identities belonging to other players, or stack information.
 */
class GameRuleException extends RuntimeException
{
    public static function notYourTurn(): self
    {
        return new self('It is not your turn yet.');
    }

    public static function wrongPhase(string $expected): self
    {
        return match ($expected) {
            'draw' => new self('You have already drawn a card this turn.'),
            default => new self('You must draw a card before discarding.'),
        };
    }

    public static function gameNotPlaying(): self
    {
        return new self('This game is not in play.');
    }

    public static function cardNotInHand(): self
    {
        return new self('You do not hold that card.');
    }

    public static function discardPileEmpty(): self
    {
        return new self('The discard pile is empty.');
    }

    public static function stockExhausted(): self
    {
        return new self('There are no cards left to draw.');
    }

    public static function invalidWinningHand(): self
    {
        return new self('This hand is not a valid winning hand.');
    }
}
