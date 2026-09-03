<?php

declare(strict_types=1);

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\MeldValidator;

function hand(string ...$codes): array
{
    return Card::fromCodes($codes);
}

beforeEach(function (): void {
    $this->validator = new MeldValidator;
});

describe('runs', function (): void {
    it('accepts three consecutive cards of one suit', function (): void {
        $melds = $this->validator->findMelds(hand('3H', '4H', '5H'));

        expect($melds)->toHaveCount(1);
    });

    it('accepts an ace low run', function (): void {
        expect($this->validator->isWinningHand(hand('AS', '2S', '3S')))->toBeTrue();
    });

    it('rejects a run wrapping through the ace', function (): void {
        expect($this->validator->isWinningHand(hand('QD', 'KD', 'AD')))->toBeFalse();
    });

    it('rejects a run wrapping from king to two', function (): void {
        expect($this->validator->isWinningHand(hand('KC', 'AC', '2C')))->toBeFalse();
    });

    it('rejects consecutive ranks across different suits', function (): void {
        expect($this->validator->isWinningHand(hand('3H', '4D', '5H')))->toBeFalse();
    });

    it('rejects a two card run', function (): void {
        expect($this->validator->isWinningHand(hand('3H', '4H')))->toBeFalse();
    });

    it('accepts a ten card run', function (): void {
        expect($this->validator->isWinningHand(
            hand('3H', '4H', '5H', '6H', '7H', '8H', '9H', '10H', 'JH', 'QH'),
        ))->toBeTrue();
    });
});

describe('sets', function (): void {
    it('accepts three of a kind', function (): void {
        expect($this->validator->isWinningHand(hand('7H', '7D', '7C')))->toBeTrue();
    });

    it('accepts four of a kind', function (): void {
        expect($this->validator->isWinningHand(hand('KH', 'KD', 'KC', 'KS')))->toBeTrue();
    });

    it('rejects a pair', function (): void {
        expect($this->validator->isWinningHand(hand('7H', '7D')))->toBeFalse();
    });
});

describe('mixed melds', function (): void {
    it('accepts a run, a set and a longer run', function (): void {
        expect($this->validator->isWinningHand(
            hand('3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'),
        ))->toBeTrue();
    });

    it('accepts three sets and a run', function (): void {
        expect($this->validator->isWinningHand(
            hand('2H', '2D', '2C', '5H', '5D', '5C', '9H', '10H', 'JH', 'QH'),
        ))->toBeTrue();
    });

    it('accepts a hand only solvable by splitting a four card set', function (): void {
        // 8H completes the heart run, so the four eights must give one card up
        // and still leave a valid set behind.
        expect($this->validator->isWinningHand(
            hand('6H', '7H', '8H', '8D', '8C', '8S', '2D', '3D', '4D', '5D'),
        ))->toBeTrue();
    });

    it('accepts a hand only solvable by shortening a long run', function (): void {
        expect($this->validator->isWinningHand(
            hand('4S', '5S', '6S', '7S', '8S', '9S', '10S', 'QC', 'QD', 'QH'),
        ))->toBeTrue();
    });

    it('rejects a hand with a single deadwood card', function (): void {
        expect($this->validator->isWinningHand(
            hand('3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', 'KS'),
        ))->toBeFalse();
    });

    it('rejects a hand of nine melded cards and one spare', function (): void {
        expect($this->validator->isWinningHand(
            hand('3H', '4H', '5H', '9C', '9D', '9S', 'AC', '2C', '3C', 'KS'),
        ))->toBeFalse();
    });

    it('rejects a hand where every card pairs but nothing melds', function (): void {
        expect($this->validator->isWinningHand(
            hand('2H', '2D', '4H', '4D', '6H', '6D', '8H', '8D', '10H', '10D'),
        ))->toBeFalse();
    });
});

describe('input handling', function (): void {
    it('rejects an empty hand', function (): void {
        expect($this->validator->findMelds([]))->toBeNull();
    });

    it('rejects a hand holding the same card twice', function (): void {
        expect($this->validator->isWinningHand(
            hand('7H', '7H', '7H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'),
        ))->toBeFalse();
    });

    it('returns melds that together use every card exactly once', function (): void {
        $cards = hand('3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C');

        $melds = $this->validator->findMelds($cards);

        expect($melds)->not->toBeNull();

        $used = array_merge(...array_map(fn (array $meld): array => Card::toCodes($meld), $melds));

        expect($used)->toHaveCount(10)
            ->and(array_unique($used))->toHaveCount(10)
            ->and(array_diff(Card::toCodes($cards), $used))->toBeEmpty();
    });
});

describe('partial melds', function (): void {
    it('finds the melds inside a hand that cannot go out', function (): void {
        $melds = $this->validator->bestMelds(
            hand('3H', '4H', '5H', '9C', '9D', '9S', 'KS', '2D', '7C', 'JH'),
        );

        $found = array_map(fn (array $meld): array => Card::toCodes($meld), $melds);

        expect($found)->toHaveCount(2)
            ->and(array_merge(...$found))->toHaveCount(6);
    });

    it('accounts for as many cards as it possibly can', function (): void {
        // The four eights can only be split one way that also leaves the heart
        // run intact, and a greedy pass would take the wrong branch first.
        $melds = $this->validator->bestMelds(
            hand('6H', '7H', '8H', '8D', '8C', '8S', '2D', '3D', '4D', 'KS'),
        );

        expect(array_sum(array_map(fn (array $meld): int => count($meld), $melds)))->toBe(9);
    });

    it('returns nothing when the hand holds no meld at all', function (): void {
        expect($this->validator->bestMelds(
            hand('2H', '4D', '6C', '8S', '10H', 'QD', 'AC', '3S', '5H', '7D'),
        ))->toBe([]);
    });

    it('returns every card of a hand that fully melds', function (): void {
        $melds = $this->validator->bestMelds(
            hand('3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'),
        );

        expect(array_sum(array_map(fn (array $meld): int => count($meld), $melds)))->toBe(10);
    });

    it('never puts one card in two melds', function (): void {
        $melds = $this->validator->bestMelds(
            hand('7H', '8H', '9H', '7D', '7C', '7S', '2S', '3S', '4S', 'KD'),
        );

        $used = array_merge(...array_map(fn (array $meld): array => Card::toCodes($meld), $melds));

        expect(array_unique($used))->toHaveCount(count($used));
    });
});
