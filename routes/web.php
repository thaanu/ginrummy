<?php

declare(strict_types=1);

use App\Http\Controllers\GameActionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JoinController;
use App\Http\Middleware\IdentifyPlayer;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::post('/games', [GameController::class, 'store'])
    ->middleware('throttle:game-create')
    ->name('games.store');

/*
| Every route below resolves {game} by its public code. IdentifyPlayer then
| attaches the player this browser holds a token for, if any.
*/
Route::middleware(IdentifyPlayer::class)
    ->whereNumber('game')
    ->group(function (): void {
        Route::get('/join/{game}', [JoinController::class, 'show'])->name('games.join');

        Route::post('/join/{game}', [JoinController::class, 'store'])
            ->middleware('throttle:game-join')
            ->name('games.join.store');

        Route::get('/game/{game}', [GameController::class, 'show'])->name('games.show');

        Route::post('/game/{game}/leave', [GameController::class, 'destroy'])
            ->middleware('throttle:game-action')
            ->name('games.leave');

        Route::middleware('throttle:game-action')->group(function (): void {
            Route::post('/game/{game}/start', [GameActionController::class, 'start'])->name('games.start');
            Route::post('/game/{game}/draw', [GameActionController::class, 'draw'])->name('games.draw');
            Route::post('/game/{game}/discard', [GameActionController::class, 'discard'])->name('games.discard');
            Route::post('/game/{game}/declare', [GameActionController::class, 'declare'])->name('games.declare');
            Route::post('/game/{game}/sort', [GameActionController::class, 'sort'])->name('games.sort');
            Route::post('/game/{game}/presence', [GameActionController::class, 'presence'])->name('games.presence');
        });
    });
