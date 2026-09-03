<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * In production Nginx terminates TLS and forwards a plain HTTP request from
 * localhost. Unless that proxy is trusted, every player sees an http:// link to
 * share and every player looks like the same IP address to the rate limiter.
 */
// Whatever the local .env says, each test starts from nothing trusted and
// opts in explicitly, so the suite behaves the same on every machine.
beforeEach(function (): void {
    TrustProxies::flushState();
});

afterEach(function (): void {
    TrustProxies::flushState();
});

it('hands out an https invitation link when the proxy is trusted', function (): void {
    TrustProxies::at('*');

    $game = Game::factory()->create();
    [, $token] = seatPlayer($game, 'Ahmed', 1, host: true);

    asPlayer($game->code, $token)
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('games.show', $game->code))
        ->assertInertia(fn ($page) => $page->where(
            'invitationUrl',
            str_replace('http://', 'https://', route('games.join', $game->code)),
        ));
});

it('tells players apart by their real address when the proxy is trusted', function (): void {
    TrustProxies::at('*');

    Route::middleware('web')->get('/test-client-ip', fn (Request $request) => $request->ip());

    $this->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/test-client-ip')
        ->assertOk()
        ->assertSee('203.0.113.9');
});

it('ignores forwarded headers from an untrusted client', function (): void {
    Route::middleware('web')->get('/test-client-ip', fn (Request $request) => $request->ip());

    $this->withHeader('X-Forwarded-For', '203.0.113.9')
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get('/test-client-ip')
        ->assertOk()
        ->assertDontSee('203.0.113.9');
});

it('keeps the link on http when nothing is trusted', function (): void {
    $game = Game::factory()->create();
    [, $token] = seatPlayer($game, 'Ahmed', 1, host: true);

    asPlayer($game->code, $token)
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('games.show', $game->code))
        ->assertInertia(fn ($page) => $page->where(
            'invitationUrl',
            route('games.join', $game->code),
        ));
});
