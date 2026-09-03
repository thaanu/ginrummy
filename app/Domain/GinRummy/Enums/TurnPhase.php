<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum TurnPhase: string
{
    case Draw = 'draw';
    case Discard = 'discard';
}
