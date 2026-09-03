<?php

declare(strict_types=1);

use App\Models\Player;
use Illuminate\Support\Facades\Cookie;

/**
 * A player's identity *is* their cookie, so on a public site it must never
 * travel over plain HTTP or be readable from JavaScript.
 */
it('marks the identity cookie secure when the site is configured for https', function (): void {
    // The cookie jar reads its defaults from the session config when it is
    // first resolved, so it has to be rebuilt after changing them.
    config()->set('session.secure', true);
    config()->set('session.same_site', 'lax');
    app()->forgetInstance('cookie');
    Cookie::clearResolvedInstances();

    $cookie = $this->post(route('games.store'), ['nickname' => 'Ahmed'])
        ->getCookie(config('ginrummy.identity_cookie'), false);

    expect($cookie?->isSecure())->toBeTrue()
        ->and($cookie?->isHttpOnly())->toBeTrue()
        ->and($cookie?->getSameSite())->toBe('lax');
});

it('never puts the raw token anywhere a page can read it', function (): void {
    $response = $this->post(route('games.store'), ['nickname' => 'Ahmed']);

    $player = Player::sole();
    $raw = $response->getCookie(config('ginrummy.identity_cookie'), false);

    expect($response->getContent())->not->toContain($player->session_token_hash)
        ->and($raw?->getValue())->not->toBe('');
});
