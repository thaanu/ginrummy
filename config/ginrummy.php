<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Player identity cookie
    |--------------------------------------------------------------------------
    |
    | Players are identified by an unguessable token stored in this http-only,
    | encrypted cookie. It maps game codes to raw tokens so a single browser can
    | hold identities for several games at once.
    |
    */

    'identity_cookie' => env('GIN_RUMMY_IDENTITY_COOKIE', 'gin_rummy_identity'),

    'identity_cookie_lifetime_minutes' => (int) env('GIN_RUMMY_IDENTITY_COOKIE_LIFETIME', 60 * 24 * 7),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated proxy addresses, or "*" behind a load balancer. Behind
    | Nginx the application otherwise sees every request as plain HTTP from
    | 127.0.0.1, which turns invitation links into http:// and collapses the
    | per-IP rate limits into a single shared bucket.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Housekeeping
    |--------------------------------------------------------------------------
    |
    | Abandoned games are pruned by the `games:prune` command, which the
    | scheduler runs hourly. Values are in hours.
    |
    */

    'ttl' => [
        'waiting' => (int) env('GIN_RUMMY_WAITING_GAME_TTL_HOURS', 2),
        'playing' => (int) env('GIN_RUMMY_PLAYING_GAME_TTL_HOURS', 12),
        'completed' => (int) env('GIN_RUMMY_COMPLETED_GAME_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency
    |--------------------------------------------------------------------------
    |
    | Every mutating game action takes a named lock before opening its database
    | transaction, so two simultaneous requests can never interleave.
    |
    */

    'lock' => [
        'seconds' => (int) env('GIN_RUMMY_LOCK_SECONDS', 10),
        'wait_seconds' => (int) env('GIN_RUMMY_LOCK_WAIT_SECONDS', 5),
    ],

];
