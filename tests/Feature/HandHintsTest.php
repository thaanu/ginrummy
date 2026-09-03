<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\TurnPhase;
use App\Models\Game;
use App\Models\Player;
use App\Services\GamePresenter;

/**
 * @param  list<string>  $hand
 */
function playerHolding(array $hand): Player
{
    $game = Game::factory()->playing()->create();
    [$player] = seatPlayer($game, 'Ahmed', 1, host: true);
    seatPlayer($game, 'John', 2);

    $player->forceFill(['hand' => $hand])->save();

    $game->forceFill([
        'turn_phase' => TurnPhase::Discard,
        'current_player_id' => $player->id,
        'player_order' => [$player->id],
    ])->save();

    return $player->refresh();
}

it('reports the melds a hand already holds', function (): void {
    $state = app(GamePresenter::class)->privateState(
        playerHolding(['3H', '4H', '5H', '9C', '9D', '9S', 'KS', '2D', '7C', 'JH']),
    );

    expect($state['melds'])->toHaveCount(2)
        ->and(array_merge(...$state['melds']))->toHaveCount(6);
});

it('reports no melds for a hand that has none', function (): void {
    $state = app(GamePresenter::class)->privateState(
        playerHolding(['2H', '4D', '6C', '8S', '10H', 'QD', 'AC', '3S', '5H', '7D']),
    );

    expect($state['melds'])->toBe([])
        ->and($state['canGoGin'])->toBeFalse()
        ->and($state['ginDiscards'])->toBe([]);
});

it('says a complete ten card hand can go gin', function (): void {
    $state = app(GamePresenter::class)->privateState(
        playerHolding(['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C']),
    );

    expect($state['canGoGin'])->toBeTrue()
        ->and($state['melds'])->toHaveCount(3)
        ->and($state['ginDiscards'])->toBe([]);
});

it('names the card to put down when eleven cards are one discard from gin', function (): void {
    $state = app(GamePresenter::class)->privateState(
        playerHolding(['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C', 'KS']),
    );

    expect($state['ginDiscards'])->toBe(['KS'])
        ->and($state['canGoGin'])->toBeFalse();
});

it('names nothing when no single discard would win', function (): void {
    $state = app(GamePresenter::class)->privateState(
        playerHolding(['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', 'KS', '2D']),
    );

    expect($state['ginDiscards'])->toBe([]);
});

it('reaches the player through a move response', function (): void {
    $game = Game::factory()->playing()->create();
    [$ahmed, $token] = seatPlayer($game, 'Ahmed', 1, host: true);
    [$john] = seatPlayer($game, 'John', 2);

    $ahmed->forceFill(['hand' => ['3H', '4H', '9C', '9D', '9S', 'KS']])->save();
    $john->forceFill(['hand' => ['QS']])->save();
    $game->forceFill([
        'turn_phase' => TurnPhase::Draw,
        'current_player_id' => $ahmed->id,
        'player_order' => [$ahmed->id, $john->id],
        'stock' => ['5H'],
        'discard' => ['2C'],
    ])->save();

    $response = asPlayer($game->code, $token)
        ->postJson(route('games.draw', $game->code), ['source' => 'stock']);

    // Drawing the 5H completes a second meld, so the table can light both up.
    $response->assertOk();

    expect($response->json('private.melds'))->toHaveCount(2);
});

it('keeps these hints out of the public state', function (): void {
    $player = playerHolding(['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C']);

    $public = app(GamePresenter::class)->publicState($player->game->load('players'));

    expect($public)->not->toHaveKey('melds')
        ->and($public)->not->toHaveKey('ginDiscards')
        ->and(json_encode($public))->not->toContain('9C');
});
