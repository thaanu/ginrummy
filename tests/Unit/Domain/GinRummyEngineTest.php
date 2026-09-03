<?php

declare(strict_types=1);

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Deck;
use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Domain\GinRummy\Exceptions\GameRuleException;
use App\Domain\GinRummy\GameState;
use App\Domain\GinRummy\GinRummyEngine;
use App\Domain\GinRummy\Hand;
use App\Domain\GinRummy\Shuffler;

/**
 * Leaves the deck in its natural order so tests can predict every deal.
 */
final class OrderedShuffler implements Shuffler
{
    public function shuffle(array $cards): array
    {
        return $cards;
    }
}

/**
 * Reverses the pile, which is enough to prove a reshuffle actually happened.
 */
final class ReversingShuffler implements Shuffler
{
    public function shuffle(array $cards): array
    {
        return array_reverse($cards);
    }
}

function engine(?Shuffler $shuffler = null): GinRummyEngine
{
    return new GinRummyEngine(shuffler: $shuffler ?? new OrderedShuffler);
}

/**
 * @param  array<int, list<string>>  $hands
 */
function state(
    array $hands,
    int $currentPlayerId,
    TurnPhase $phase,
    array $stock = [],
    array $discard = [],
): GameState {
    return new GameState(
        status: GameStatus::Playing,
        playerOrder: array_keys($hands),
        hands: array_map(fn (array $codes): Hand => Hand::fromCodes($codes), $hands),
        stock: Card::fromCodes($stock),
        discard: Card::fromCodes($discard),
        turnPhase: $phase,
        currentPlayerId: $currentPlayerId,
    );
}

describe('dealing', function (): void {
    it('deals eleven cards to the starting player and ten to everyone else', function (): void {
        $state = engine()->start([1, 2, 3], startingPlayerId: 2);

        expect($state->handFor(1)->count())->toBe(10)
            ->and($state->handFor(2)->count())->toBe(11)
            ->and($state->handFor(3)->count())->toBe(10);
    });

    it('leaves the rest of the deck as the stock', function (): void {
        $state = engine()->start([1, 2], startingPlayerId: 1);

        expect($state->stockCount())->toBe(52 - 21);
    });

    it('starts with an empty discard pile', function (): void {
        expect(engine()->start([1, 2], startingPlayerId: 1)->discard)->toBe([]);
    });

    it('opens on the starting player in the discard phase', function (): void {
        $state = engine()->start([1, 2], startingPlayerId: 2);

        expect($state->currentPlayerId)->toBe(2)
            ->and($state->turnPhase)->toBe(TurnPhase::Discard)
            ->and($state->status)->toBe(GameStatus::Playing);
    });

    it('puts exactly the fifty two cards of one deck into play', function (): void {
        $codes = engine()->start([1, 2, 3, 4], startingPlayerId: 1)->allCardCodes();

        expect($codes)->toHaveCount(52)
            ->and(array_unique($codes))->toHaveCount(52)
            ->and(array_diff(Card::toCodes(Deck::standard()->cards()), $codes))->toBeEmpty();
    });

    it('chooses a starting player from the table when none is given', function (): void {
        expect(engine()->start([7, 8, 9])->currentPlayerId)->toBeIn([7, 8, 9]);
    });

    it('preserves the seating order given to it', function (): void {
        expect(engine()->start([4, 1, 3], startingPlayerId: 1)->playerOrder)->toBe([4, 1, 3]);
    });

    it('refuses to start with fewer than two players', function (): void {
        engine()->start([1]);
    })->throws(GameRuleException::class, 'At least two players are needed to start.');

    it('refuses to start with more than four players', function (): void {
        engine()->start([1, 2, 3, 4, 5]);
    })->throws(GameRuleException::class, 'A game holds at most four players.');

    it('refuses to seat the same player twice', function (): void {
        engine()->start([1, 2, 2]);
    })->throws(GameRuleException::class, 'Each player may only be seated once.');

    it('refuses a starting player who is not seated', function (): void {
        engine()->start([1, 2], startingPlayerId: 9);
    })->throws(GameRuleException::class, 'The starting player is not seated in this game.');
});

describe('drawing', function (): void {
    it('moves the top of the stock into the hand and switches to discarding', function (): void {
        $before = state([1 => ['2H'], 2 => []], 1, TurnPhase::Draw, stock: ['9C', '4D']);

        $after = engine()->drawFromStock($before, 1);

        expect($after->handFor(1)->codes())->toBe(['2H', '4D'])
            ->and($after->stockCount())->toBe(1)
            ->and($after->turnPhase)->toBe(TurnPhase::Discard)
            ->and($after->currentPlayerId)->toBe(1);
    });

    it('moves the top of the discard pile into the hand', function (): void {
        $before = state([1 => ['2H'], 2 => []], 1, TurnPhase::Draw, stock: ['9C'], discard: ['KS', '4D']);

        $after = engine()->drawFromDiscard($before, 1);

        expect($after->handFor(1)->codes())->toBe(['2H', '4D'])
            ->and($after->topOfDiscard()?->code())->toBe('KS')
            ->and($after->stockCount())->toBe(1);
    });

    it('leaves the discard pile alone when drawing from the stock', function (): void {
        $before = state([1 => ['2H'], 2 => []], 1, TurnPhase::Draw, stock: ['9C'], discard: ['KS', '4D']);

        expect(engine()->drawFromStock($before, 1)->topOfDiscard()?->code())->toBe('4D');
    });

    it('rejects a second draw in the same turn', function (): void {
        $before = state([1 => ['2H'], 2 => []], 1, TurnPhase::Draw, stock: ['9C', '4D']);

        engine()->drawFromStock(engine()->drawFromStock($before, 1), 1);
    })->throws(GameRuleException::class, 'You have already drawn a card this turn.');

    it('rejects a draw from a player whose turn it is not', function (): void {
        engine()->drawFromStock(state([1 => [], 2 => []], 1, TurnPhase::Draw, stock: ['9C']), 2);
    })->throws(GameRuleException::class, 'It is not your turn yet.');

    it('rejects a draw from an empty discard pile', function (): void {
        engine()->drawFromDiscard(state([1 => [], 2 => []], 1, TurnPhase::Draw, stock: ['9C']), 1);
    })->throws(GameRuleException::class, 'The discard pile is empty.');

    it('rejects any action once the game is over', function (): void {
        $completed = state([1 => [], 2 => []], 1, TurnPhase::Draw, stock: ['9C'])->completedWith(1);

        engine()->drawFromStock($completed, 1);
    })->throws(GameRuleException::class, 'This game is not in play.');
});

describe('an exhausted stock', function (): void {
    it('reshuffles the discard pile back into the stock, keeping its top card', function (): void {
        $before = state([1 => [], 2 => []], 1, TurnPhase::Draw, stock: [], discard: ['2H', '3H', '4H', '5H']);

        $after = engine(new ReversingShuffler)->drawFromStock($before, 1);

        expect($after->topOfDiscard()?->code())->toBe('5H')
            ->and($after->handFor(1)->codes())->toBe(['2H'])
            ->and($after->stockCount())->toBe(2)
            ->and(Card::toCodes($after->stock))->toBe(['4H', '3H']);
    });

    it('ends the game without a winner when nothing is left to draw', function (): void {
        $before = state([1 => [], 2 => []], 1, TurnPhase::Draw, stock: [], discard: ['5H']);

        $after = engine()->drawFromStock($before, 1);

        expect($after->status)->toBe(GameStatus::Completed)
            ->and($after->winnerPlayerId)->toBeNull();
    });
});

describe('discarding', function (): void {
    it('puts the card on the pile and passes the turn on', function (): void {
        $before = state([1 => ['2H', '9C'], 2 => []], 1, TurnPhase::Discard);

        $after = engine()->discard($before, 1, Card::fromCode('9C'));

        expect($after->handFor(1)->codes())->toBe(['2H'])
            ->and($after->topOfDiscard()?->code())->toBe('9C')
            ->and($after->currentPlayerId)->toBe(2)
            ->and($after->turnPhase)->toBe(TurnPhase::Draw);
    });

    it('wraps the turn back to the first seat', function (): void {
        $before = state([1 => [], 2 => [], 3 => ['9C']], 3, TurnPhase::Discard);

        expect(engine()->discard($before, 3, Card::fromCode('9C'))->currentPlayerId)->toBe(1);
    });

    it('rejects discarding a card the player does not hold', function (): void {
        engine()->discard(state([1 => ['2H'], 2 => []], 1, TurnPhase::Discard), 1, Card::fromCode('9C'));
    })->throws(GameRuleException::class, 'You do not hold that card.');

    it('rejects a discard before the player has drawn', function (): void {
        engine()->discard(state([1 => ['2H'], 2 => []], 1, TurnPhase::Draw), 1, Card::fromCode('2H'));
    })->throws(GameRuleException::class, 'You must draw a card before discarding.');

    it('rejects a discard from a player whose turn it is not', function (): void {
        engine()->discard(state([1 => [], 2 => ['2H']], 1, TurnPhase::Discard), 2, Card::fromCode('2H'));
    })->throws(GameRuleException::class, 'It is not your turn yet.');
});

describe('declaring done', function (): void {
    $winning = ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'];

    it('wins when the ten remaining cards all meld', function () use ($winning): void {
        $before = state([1 => [...$winning, 'KS'], 2 => []], 1, TurnPhase::Discard);

        $after = engine()->declareDone($before, 1, Card::fromCode('KS'));

        expect($after->status)->toBe(GameStatus::Completed)
            ->and($after->winnerPlayerId)->toBe(1)
            ->and($after->currentPlayerId)->toBeNull()
            ->and($after->topOfDiscard()?->code())->toBe('KS')
            ->and($after->handFor(1)->count())->toBe(10);
    });

    it('wins on a ten card hand held at the start of a turn', function () use ($winning): void {
        $before = state([1 => $winning, 2 => []], 1, TurnPhase::Draw, stock: ['2D']);

        expect(engine()->declareDone($before, 1)->winnerPlayerId)->toBe(1);
    });

    it('leaves the game untouched when the hand does not fully meld', function (): void {
        $before = state([1 => ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', 'KS', '2D'], 2 => []], 1, TurnPhase::Discard);

        try {
            engine()->declareDone($before, 1, Card::fromCode('2D'));
        } catch (GameRuleException $exception) {
            expect($exception->getMessage())->toBe('This hand is not a valid winning hand.');
        }

        expect($before->status)->toBe(GameStatus::Playing)
            ->and($before->handFor(1)->count())->toBe(11)
            ->and($before->winnerPlayerId)->toBeNull();
    });

    it('requires a card to be named when the player still holds eleven', function () use ($winning): void {
        engine()->declareDone(state([1 => [...$winning, 'KS'], 2 => []], 1, TurnPhase::Discard), 1);
    })->throws(GameRuleException::class, 'Select the card you are putting down before going gin.');

    it('rejects a declaration from a player whose turn it is not', function () use ($winning): void {
        engine()->declareDone(state([1 => [], 2 => $winning], 1, TurnPhase::Draw), 2);
    })->throws(GameRuleException::class, 'It is not your turn yet.');

    it('rejects a declaration on a hand that is not ten cards', function (): void {
        engine()->declareDone(state([1 => ['3H', '4H', '5H'], 2 => []], 1, TurnPhase::Draw), 1);
    })->throws(GameRuleException::class, 'This hand is not a valid winning hand.');
});

describe('card conservation', function (): void {
    it('never creates or loses a card across a full round of turns', function (): void {
        $engine = engine(new ReversingShuffler);
        $state = $engine->start([1, 2], startingPlayerId: 1);

        $state = $engine->discard($state, 1, $state->handFor(1)->cards()[0]);
        $state = $engine->drawFromStock($state, 2);
        $state = $engine->discard($state, 2, $state->handFor(2)->cards()[0]);
        $state = $engine->drawFromDiscard($state, 1);
        $state = $engine->discard($state, 1, $state->handFor(1)->cards()[0]);

        $codes = $state->allCardCodes();

        expect($codes)->toHaveCount(52)
            ->and(array_unique($codes))->toHaveCount(52);
    });
});

describe('sorting a hand', function (): void {
    it('rearranges the same cards', function (): void {
        $before = state([1 => ['3H', '9C', '4H'], 2 => []], 1, TurnPhase::Draw);

        $after = engine()->sortHand($before, 1, Card::fromCodes(['3H', '4H', '9C']));

        expect($after->handFor(1)->codes())->toBe(['3H', '4H', '9C']);
    });

    it('is allowed when it is somebody else\'s turn', function (): void {
        $before = state([1 => [], 2 => ['3H', '9C']], 1, TurnPhase::Draw);

        expect(engine()->sortHand($before, 2, Card::fromCodes(['9C', '3H']))->handFor(2)->codes())
            ->toBe(['9C', '3H']);
    });

    it('refuses an order that drops a card', function (): void {
        engine()->sortHand(
            state([1 => ['3H', '9C', '4H'], 2 => []], 1, TurnPhase::Draw),
            1,
            Card::fromCodes(['3H', '4H']),
        );
    })->throws(GameRuleException::class, 'That is not the hand you are holding.');

    it('refuses an order that smuggles in a card', function (): void {
        engine()->sortHand(
            state([1 => ['3H', '9C'], 2 => []], 1, TurnPhase::Draw),
            1,
            Card::fromCodes(['3H', '9C', 'AS']),
        );
    })->throws(GameRuleException::class, 'That is not the hand you are holding.');

    it('refuses to sort once the game is over', function (): void {
        engine()->sortHand(
            state([1 => ['3H', '9C'], 2 => []], 1, TurnPhase::Draw)->completedWith(1),
            1,
            Card::fromCodes(['9C', '3H']),
        );
    })->throws(GameRuleException::class, 'This game is not in play.');

    it('refuses to sort a hand that is not yours', function (): void {
        engine()->sortHand(state([1 => ['3H'], 2 => []], 1, TurnPhase::Draw), 9, []);
    })->throws(GameRuleException::class, 'You are not seated at this table.');
});

describe('going gin', function (): void {
    $winning = ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'];

    it('names the card that would complete a winning hand', function () use ($winning): void {
        $discards = engine()->winningDiscards(Card::fromCodes([...$winning, 'KS']));

        expect(Card::toCodes($discards))->toBe(['KS']);
    });

    it('names every card that would do, when more than one would', function (): void {
        // Three melds with a card to spare in two of them: any of the four nines
        // can go, and so can either end of the club run.
        $hand = ['3H', '4H', '5H', '9C', '9D', '9S', '9H', '5C', '6C', '7C', '8C'];

        expect(Card::toCodes(engine()->winningDiscards(Card::fromCodes($hand))))
            ->toEqualCanonicalizing(['9C', '9D', '9S', '9H', '5C', '8C']);
    });

    it('names nothing when no single discard can win', function (): void {
        $hand = ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', 'KS', '2D'];

        expect(engine()->winningDiscards(Card::fromCodes($hand)))->toBe([]);
    });

    it('names nothing for a hand that is not eleven cards', function () use ($winning): void {
        expect(engine()->winningDiscards(Card::fromCodes($winning)))->toBe([]);
    });
});
