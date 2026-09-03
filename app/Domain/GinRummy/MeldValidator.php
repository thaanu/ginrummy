<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

/**
 * Finds the melds hidden in a hand.
 *
 * A meld is either a RUN (three or more cards of one suit in consecutive rank
 * order, ace low, no wrapping) or a SET (three or four cards of the same rank
 * in different suits). A hand wins only when every card belongs to exactly one
 * meld, so no deadwood is allowed.
 *
 * One search answers both questions the game asks. It enumerates every meld the
 * hand can form, encodes each as a bitmask over the hand's card positions, and
 * then finds the combination of disjoint melds covering the most cards, with
 * memoisation on the set of cards already accounted for. A winning hand is
 * simply one where that best arrangement leaves nothing over, and the same
 * arrangement is what the table highlights while a player sorts their cards.
 */
final class MeldValidator
{
    private const MINIMUM_MELD_SIZE = 3;

    /**
     * @param  list<Card>  $cards
     */
    public function isWinningHand(array $cards): bool
    {
        return $this->findMelds($cards) !== null;
    }

    /**
     * One partition of the hand into melds using every card, or null when some
     * card is left over.
     *
     * @param  list<Card>  $cards
     * @return list<list<Card>>|null
     */
    public function findMelds(array $cards): ?array
    {
        if (count($cards) < self::MINIMUM_MELD_SIZE) {
            return null;
        }

        $melds = $this->bestMelds($cards);

        $melded = array_sum(array_map(fn (array $meld): int => count($meld), $melds));

        return $melded === count($cards) ? $melds : null;
    }

    /**
     * The arrangement of melds that accounts for as many of these cards as
     * possible. Cards left out are deadwood. Returns an empty list when the hand
     * holds no meld at all.
     *
     * @param  list<Card>  $cards
     * @return list<list<Card>>
     */
    public function bestMelds(array $cards): array
    {
        if (count($cards) < self::MINIMUM_MELD_SIZE || $this->containsDuplicates($cards)) {
            return [];
        }

        $candidates = $this->candidateMelds($cards);

        if ($candidates === []) {
            return [];
        }

        /** @var array<int, list<int>> $best */
        $best = [];

        $masks = $this->bestCover(0, (1 << count($cards)) - 1, $candidates, $best);

        return array_map(fn (int $mask): array => $this->cardsForMask($cards, $mask), $masks);
    }

    /**
     * Resolves the remaining positions, always starting with the lowest one that
     * is still unaccounted for, so each arrangement is reached exactly once.
     *
     * That card either joins one of the melds containing it, or is set aside as
     * deadwood. The branch covering the most cards wins.
     *
     * @param  list<int>  $candidates
     * @param  array<int, list<int>>  $best  memoised results per resolved set
     * @return list<int>
     */
    private function bestCover(int $resolved, int $full, array $candidates, array &$best): array
    {
        if ($resolved === $full) {
            return [];
        }

        if (isset($best[$resolved])) {
            return $best[$resolved];
        }

        $lowest = $this->lowestUncoveredBit($resolved, $full);

        // Leaving the card as deadwood is always available, and is the baseline
        // the melds have to beat.
        $winner = $this->bestCover($resolved | $lowest, $full, $candidates, $best);
        $winningScore = $this->cardsCovered($winner);

        foreach ($candidates as $meld) {
            if (($meld & $lowest) === 0 || ($meld & $resolved) !== 0) {
                continue;
            }

            $rest = $this->bestCover($resolved | $meld, $full, $candidates, $best);
            $score = $this->popCount($meld) + $this->cardsCovered($rest);

            if ($score > $winningScore) {
                $winningScore = $score;
                $winner = [$meld, ...$rest];
            }
        }

        return $best[$resolved] = $winner;
    }

    /**
     * @param  list<int>  $masks
     */
    private function cardsCovered(array $masks): int
    {
        return array_sum(array_map($this->popCount(...), $masks));
    }

    private function popCount(int $mask): int
    {
        return substr_count(decbin($mask), '1');
    }

    private function lowestUncoveredBit(int $covered, int $full): int
    {
        $uncovered = $full & ~$covered;

        return $uncovered & -$uncovered;
    }

    /**
     * Every run and set the hand can form, as bitmasks over card positions.
     *
     * @param  list<Card>  $cards
     * @return list<int>
     */
    private function candidateMelds(array $cards): array
    {
        return [...$this->runMasks($cards), ...$this->setMasks($cards)];
    }

    /**
     * @param  list<Card>  $cards
     * @return list<int>
     */
    private function runMasks(array $cards): array
    {
        /** @var array<string, list<int>> $bySuit */
        $bySuit = [];

        foreach ($cards as $index => $card) {
            $bySuit[$card->suit->value][] = $index;
        }

        $masks = [];

        foreach ($bySuit as $indexes) {
            usort($indexes, fn (int $a, int $b): int => $cards[$a]->rank->order() <=> $cards[$b]->rank->order());

            $length = count($indexes);

            for ($start = 0; $start < $length; $start++) {
                $mask = 1 << $indexes[$start];

                for ($end = $start + 1; $end < $length; $end++) {
                    $previous = $cards[$indexes[$end - 1]]->rank->order();

                    if ($cards[$indexes[$end]]->rank->order() !== $previous + 1) {
                        break;
                    }

                    $mask |= 1 << $indexes[$end];

                    if ($end - $start + 1 >= self::MINIMUM_MELD_SIZE) {
                        $masks[] = $mask;
                    }
                }
            }
        }

        return $masks;
    }

    /**
     * @param  list<Card>  $cards
     * @return list<int>
     */
    private function setMasks(array $cards): array
    {
        /** @var array<string, list<int>> $byRank */
        $byRank = [];

        foreach ($cards as $index => $card) {
            $byRank[$card->rank->value][] = $index;
        }

        $masks = [];

        foreach ($byRank as $indexes) {
            if (count($indexes) < self::MINIMUM_MELD_SIZE) {
                continue;
            }

            foreach ($this->combinationMasks($indexes, self::MINIMUM_MELD_SIZE) as $mask) {
                $masks[] = $mask;
            }

            if (count($indexes) === 4) {
                $masks[] = array_reduce($indexes, fn (int $mask, int $index): int => $mask | (1 << $index), 0);
            }
        }

        return $masks;
    }

    /**
     * Bitmasks for every combination of exactly $size positions.
     *
     * @param  list<int>  $indexes
     * @return list<int>
     */
    private function combinationMasks(array $indexes, int $size): array
    {
        if ($size === 0) {
            return [0];
        }

        if (count($indexes) < $size) {
            return [];
        }

        [$head] = $indexes;
        $tail = array_slice($indexes, 1);

        $withHead = array_map(
            fn (int $mask): int => $mask | (1 << $head),
            $this->combinationMasks($tail, $size - 1),
        );

        return [...$withHead, ...$this->combinationMasks($tail, $size)];
    }

    /**
     * @param  list<Card>  $cards
     * @return list<Card>
     */
    private function cardsForMask(array $cards, int $mask): array
    {
        $meld = [];

        foreach ($cards as $index => $card) {
            if (($mask & (1 << $index)) !== 0) {
                $meld[] = $card;
            }
        }

        return $meld;
    }

    /**
     * A hand should never hold the same card twice; if it somehow does, it is
     * not a hand we are willing to call a winner.
     *
     * @param  list<Card>  $cards
     */
    private function containsDuplicates(array $cards): bool
    {
        $codes = Card::toCodes($cards);

        return count(array_unique($codes)) !== count($codes);
    }
}
