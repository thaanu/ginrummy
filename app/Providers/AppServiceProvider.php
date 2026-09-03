<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Game;
use App\Models\Player;
use App\Services\PlayerIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerInertiaBladeComponents();
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->registerTrustedProxies();
        $this->registerPlayerGuard();
        $this->registerRateLimiters();
        $this->registerDevCommands();
    }

    /**
     * `php artisan dev` starts the server, queue, logs and Vite, but not the
     * WebSocket server. Without Reverb running, nothing on the table updates
     * live, so it belongs in the same set.
     */
    private function registerDevCommands(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            DevCommands::artisan('reverb:start', 'reverb');
        }
    }

    /**
     * Re-registers Inertia's Blade component namespace against the compiler that
     * is actually doing the compiling.
     *
     * `php artisan optimize` runs every cache in one process, and `config:cache`
     * builds a throwaway application to read fresh configuration from — which
     * takes over as the facade root and is never handed back. Inertia registers
     * its components through the Blade *facade*, so by the time `view:cache`
     * resolves this application's compiler, the namespace has been attached to
     * the throwaway one and `<x-inertia::app />` cannot be found.
     *
     * Binding against the resolved instance rather than the facade puts it on
     * the right compiler either way. Registering it twice is harmless.
     */
    private function registerInertiaBladeComponents(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->componentNamespace('Inertia\\View\\Components', 'inertia');
        });
    }

    /**
     * Trusting the proxy has to happen here rather than in bootstrap/app.php,
     * because the middleware there is configured before the environment file is
     * read. TrustProxies looks this up when the request arrives.
     */
    private function registerTrustedProxies(): void
    {
        $proxies = trim((string) config('ginrummy.trusted_proxies'));

        if ($proxies === '') {
            return;
        }

        TrustProxies::at($proxies === '*'
            ? '*'
            : array_map(trim(...), explode(',', $proxies)));
    }

    /**
     * Broadcasting authorizes private channels through an auth guard, so the
     * temporary player identity is exposed as one.
     *
     * The channel being requested names the game, and the token proving the
     * request belongs to a player in that game comes only from the cookie.
     */
    private function registerPlayerGuard(): void
    {
        Auth::viaRequest('player-token', function (Request $request): ?Player {
            $code = $this->gameCodeFromChannel((string) $request->input('channel_name', ''));

            if ($code === null) {
                return null;
            }

            $game = Game::query()->where('code', $code)->first();

            if (! $game instanceof Game) {
                return null;
            }

            return app(PlayerIdentity::class)->resolve($request, $game);
        });
    }

    /**
     * Channel names arrive prefixed, for example "private-game.12345678".
     */
    private function gameCodeFromChannel(string $channel): ?string
    {
        $name = preg_replace('/^(private|presence)-/', '', $channel) ?? $channel;

        return preg_match('/^game\.(\d{8})(?:\.|$)/', $name, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('game-create', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('game-join', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('game-action', fn (Request $request): Limit => Limit::perMinute(240)->by($request->ip()));
    }
}
