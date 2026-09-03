<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum GameStatus: string
{
    case Waiting = 'waiting';
    case Playing = 'playing';
    case Completed = 'completed';
}
