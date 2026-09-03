<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

use Random\RandomException;

/**
 * Fisher-Yates shuffle driven by the platform CSPRNG, so a deck order can never
 * be predicted from previously observed games.
 */
final class SecureShuffler implements Shuffler
{
    /**
     * @param  list<Card>  $cards
     * @return list<Card>
     *
     * @throws RandomException
     */
    public function shuffle(array $cards): array
    {
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return array_values($cards);
    }
}
