<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum DrawSource: string
{
    case Stock = 'stock';
    case Discard = 'discard';
}
