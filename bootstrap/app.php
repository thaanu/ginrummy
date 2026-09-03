<?php

use App\Domain\GinRummy\Exceptions\GameRuleException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A rejected move is an ordinary part of play, not a server fault, so
         * it is recorded as a single warning rather than reported with a stack
         * trace. The player is told what went wrong and the game carries on.
         */
        $exceptions->dontReport(GameRuleException::class);

        $exceptions->render(function (GameRuleException $exception, Request $request): Response {
            $game = $request->route('game');
            $player = $request->attributes->get('player');

            Log::warning('gin-rummy.move_rejected', [
                'game_id' => $game instanceof Game ? $game->id : null,
                'game_code' => $game instanceof Game ? $game->code : null,
                'player_id' => $player instanceof Player ? $player->id : null,
                'action' => $request->path(),
                'reason' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['game' => $exception->getMessage()]);
        });

        /*
         * Everything else is rendered through a single friendly error screen so
         * a player never sees a stack trace or a framework page.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->expectsJson() || app()->hasDebugModeEnabled()) {
                return $response;
            }

            if (! in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $response->getStatusCode(),
            ])->toResponse($request)->setStatusCode($response->getStatusCode());
        });
    })->create();
