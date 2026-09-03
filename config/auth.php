<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This application has no user accounts. The only identity it knows about is
    | the temporary "player" seated at a game, resolved from the unguessable
    | token in the browser's cookie. That guard exists purely so Laravel's
    | broadcasting channel authorization has something to authorize.
    |
    */

    'defaults' => [
        'guard' => 'player',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | The "player-token" driver is registered in the AppServiceProvider. It
    | never reads a session, a password or a database id supplied by the client.
    |
    */

    'guards' => [
        'player' => [
            'driver' => 'player-token',
        ],
    ],

    'providers' => [],

    'passwords' => [],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => 10800,

];
