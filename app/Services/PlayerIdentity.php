<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Issues and resolves the temporary identities players use instead of accounts.
 *
 * A token is a 64 character random string. The browser keeps the raw value in a
 * single encrypted, http-only cookie holding a game code to token map; the
 * server only ever stores the SHA-256 hash. Neither a player id nor a nickname
 * can be used to act as a player.
 */
final class PlayerIdentity
{
    public function issueToken(): string
    {
        return Str::random(64);
    }

    /**
     * Resolves the player the request belongs to for the given game, or null
     * when the browser holds no valid token for it.
     */
    public function resolve(Request $request, Game $game): ?Player
    {
        $token = $this->tokenFor($request, $game->code);

        if ($token === null) {
            return null;
        }

        return Player::query()
            ->where('game_id', $game->id)
            ->where('session_token_hash', Player::hashToken($token))
            ->first();
    }

    public function tokenFor(Request $request, string $gameCode): ?string
    {
        $token = $this->tokens($request)[$gameCode] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Returns the cookie that adds this identity to whatever the browser already
     * holds. Queue it on the response with `Cookie::queue()`.
     */
    public function rememberCookie(Request $request, string $gameCode, string $token): SymfonyCookie
    {
        $tokens = $this->tokens($request);
        $tokens[$gameCode] = $token;

        return $this->cookieFor($tokens);
    }

    public function forgetCookie(Request $request, string $gameCode): SymfonyCookie
    {
        $tokens = $this->tokens($request);
        unset($tokens[$gameCode]);

        return $this->cookieFor($tokens);
    }

    /**
     * @return array<string, string>
     */
    private function tokens(Request $request): array
    {
        $raw = $request->cookie($this->cookieName());

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $tokens = [];

        foreach ($decoded as $code => $token) {
            // Game codes are numeric, so PHP hands them back as integer keys.
            if (is_string($token)) {
                $tokens[(string) $code] = $token;
            }
        }

        return $tokens;
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function cookieFor(array $tokens): SymfonyCookie
    {
        return Cookie::make(
            name: $this->cookieName(),
            value: (string) json_encode($tokens),
            minutes: (int) config('ginrummy.identity_cookie_lifetime_minutes'),
            httpOnly: true,
        );
    }

    private function cookieName(): string
    {
        return (string) config('ginrummy.identity_cookie');
    }
}
