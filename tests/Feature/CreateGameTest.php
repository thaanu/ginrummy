<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\GameEventType;
use App\Domain\GinRummy\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;

it('shows the create screen', function (): void {
    $this->get(route('home'))->assertOk();
});

it('creates a game, seats the host and redirects to the table', function (): void {
    $response = $this->post(route('games.store'), ['nickname' => 'Ahmed']);

    $game = Game::sole();
    $player = Player::sole();

    $response->assertRedirect(route('games.show', $game->code));

    expect($game->status)->toBe(GameStatus::Waiting)
        ->and($game->code)->toMatch('/^\d{8}$/')
        ->and($game->host_player_id)->toBe($player->id)
        ->and($player->nickname)->toBe('Ahmed')
        ->and($player->seat_number)->toBe(1)
        ->and($player->is_host)->toBeTrue();
});

it('records the creation in the audit trail', function (): void {
    $this->post(route('games.store'), ['nickname' => 'Ahmed']);

    $event = GameEvent::sole();

    expect($event->event_type)->toBe(GameEventType::GameCreated)
        ->and($event->sequence_number)->toBe(1);
});

it('hands the browser an identity cookie rather than a player id', function (): void {
    $response = $this->post(route('games.store'), ['nickname' => 'Ahmed']);

    $cookie = $response->getCookie(config('ginrummy.identity_cookie'), false);

    expect($cookie)->not->toBeNull()
        ->and($cookie?->isHttpOnly())->toBeTrue();
});

it('never stores the raw session token', function (): void {
    $this->post(route('games.store'), ['nickname' => 'Ahmed']);

    expect(Player::sole()->session_token_hash)->toHaveLength(64);
});

it('gives every game a different code', function (): void {
    $this->post(route('games.store'), ['nickname' => 'One']);
    $this->post(route('games.store'), ['nickname' => 'Two']);

    expect(Game::query()->distinct()->count('code'))->toBe(2);
});

describe('nickname validation', function (): void {
    it('rejects a missing nickname', function (): void {
        $this->post(route('games.store'), [])->assertInvalid('nickname');

        expect(Game::query()->count())->toBe(0);
    });

    it('rejects a nickname that is too short', function (): void {
        $this->post(route('games.store'), ['nickname' => 'A'])->assertInvalid('nickname');
    });

    it('rejects a nickname that is too long', function (): void {
        $this->post(route('games.store'), ['nickname' => str_repeat('a', 21)])->assertInvalid('nickname');
    });

    it('rejects markup in a nickname', function (): void {
        $this->post(route('games.store'), ['nickname' => '<script>x</script>'])->assertInvalid('nickname');
    });

    it('collapses surrounding and repeated whitespace', function (): void {
        $this->post(route('games.store'), ['nickname' => '  Ahmed   K  ']);

        expect(Player::sole()->nickname)->toBe('Ahmed K');
    });
});
