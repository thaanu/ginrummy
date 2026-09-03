<?php

declare(strict_types=1);

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->game = Game::factory()->create();
    [$this->host] = seatPlayer($this->game, 'Ahmed', 1, host: true);
});

it('seats a second player at the next free seat', function (): void {
    $response = $this->post(route('games.join.store', $this->game->code), ['nickname' => 'John']);

    $response->assertRedirect(route('games.show', $this->game->code));

    $john = Player::query()->where('nickname', 'John')->sole();

    expect($john->seat_number)->toBe(2)
        ->and($john->is_host)->toBeFalse()
        ->and($john->game_id)->toBe($this->game->id);
});

it('tells the table someone joined', function (): void {
    Event::fake([GameStateChanged::class]);

    $this->post(route('games.join.store', $this->game->code), ['nickname' => 'John']);

    Event::assertDispatched(GameStateChanged::class);
});

it('rejects a nickname already used in the same game, ignoring case', function (): void {
    $this->post(route('games.join.store', $this->game->code), ['nickname' => 'ahMED'])
        ->assertSessionHasErrors('game');

    expect(Player::query()->count())->toBe(1);
});

it('allows the same nickname in a different game', function (): void {
    $other = Game::factory()->create();
    seatPlayer($other, 'Someone', 1, host: true);

    $this->post(route('games.join.store', $other->code), ['nickname' => 'Ahmed'])
        ->assertRedirect(route('games.show', $other->code));
});

it('refuses a fifth player', function (): void {
    seatPlayer($this->game, 'B', 2);
    seatPlayer($this->game, 'C', 3);
    seatPlayer($this->game, 'D', 4);

    $this->post(route('games.join.store', $this->game->code), ['nickname' => 'Eve'])
        ->assertSessionHasErrors('game');

    expect(Player::query()->count())->toBe(4);
});

it('refuses to join a game that has already started', function (): void {
    $this->game->forceFill(['status' => 'playing'])->save();

    $this->post(route('games.join.store', $this->game->code), ['nickname' => 'John'])
        ->assertSessionHasErrors('game');
});

it('refuses to join a game that has finished', function (): void {
    $this->game->forceFill(['status' => 'completed'])->save();

    $this->post(route('games.join.store', $this->game->code), ['nickname' => 'John'])
        ->assertSessionHasErrors('game');
});

it('returns a friendly page for an unknown game code', function (): void {
    $this->get(route('games.join', '12345678'))->assertNotFound();
});

it('does not accept a non numeric game code', function (): void {
    $this->get('/join/abcdefgh')->assertNotFound();
});

it('sends a returning player straight to the table instead of asking again', function (): void {
    [, $token] = seatPlayer($this->game, 'John', 2);

    asPlayer($this->game->code, $token)
        ->get(route('games.join', $this->game->code))
        ->assertRedirect(route('games.show', $this->game->code));
});

it('does not seat a duplicate player when an existing player posts the join form again', function (): void {
    [, $token] = seatPlayer($this->game, 'John', 2);

    asPlayer($this->game->code, $token)
        ->post(route('games.join.store', $this->game->code), ['nickname' => 'John Again'])
        ->assertRedirect(route('games.show', $this->game->code));

    expect(Player::query()->count())->toBe(2);
});
