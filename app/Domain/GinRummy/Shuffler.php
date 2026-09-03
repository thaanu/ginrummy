<?php

declare(strict_types=1);

namespace App\Domain\GinRummy;

interface Shuffler
{
    /**
     * @param  list<Card>  $cards
     * @return list<Card>
     */
    public function shuffle(array $cards): array;
}
