<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\GameStatus;
use App\Domain\GinRummy\Enums\TurnPhase;
use App\Models\Game;
use App\Models\Player;

/**
 * Deals a two player game by hand so every card in play is known.
 *
 * @param  list<string>  $currentHand
 * @param  list<string>  $opponentHand
 * @param  list<string>  $stock
 * @param  list<string>  $discard
 * @return array{game: Game, current: Player, currentToken: string, opponent: Player, opponentToken: string}
 */
function dealtGame(
    array $currentHand,
    array $opponentHand,
    TurnPhase $phase = TurnPhase::Draw,
    array $stock = ['2D', '7S'],
    array $discard = ['KH'],
): array {
    $game = Game::factory()->playing()->create();

    [$current, $currentToken] = seatPlayer($game, 'Ahmed', 1, host: true);
    [$opponent, $opponentToken] = seatPlayer($game, 'John', 2);

    $current->forceFill(['hand' => $currentHand])->save();
    $opponent->forceFill(['hand' => $opponentHand])->save();

    $game->forceFill([
        'turn_phase' => $phase,
        'current_player_id' => $current->id,
        'player_order' => [$current->id, $opponent->id],
        'stock' => $stock,
        'discard' => $discard,
    ])->save();

    return compact('game', 'current', 'currentToken', 'opponent', 'opponentToken');
}

describe('drawing', function (): void {
    it('takes the top card of the stock', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.draw', $table['game']->code), ['source' => 'stock'])
            ->assertOk()
            ->assertJsonPath('private.hand', ['3H', '4H', '7S'])
            ->assertJsonPath('state.stockCount', 1)
            ->assertJsonPath('state.turnPhase', 'discard');
    });

    it('takes the top card of the discard pile', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.draw', $table['game']->code), ['source' => 'discard'])
            ->assertOk()
            ->assertJsonPath('private.hand', ['3H', '4H', 'KH'])
            ->assertJsonPath('state.discardTop', null);
    });

    it('refuses a second draw in the same turn', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);
        $code = $table['game']->code;

        asPlayer($code, $table['currentToken'])->postJson(route('games.draw', $code), ['source' => 'stock'])->assertOk();

        asPlayer($code, $table['currentToken'])
            ->postJson(route('games.draw', $code), ['source' => 'stock'])
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already drawn a card this turn.']);

        expect($table['current']->fresh()->hand)->toHaveCount(3);
    });

    it('refuses a draw out of turn', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);

        asPlayer($table['game']->code, $table['opponentToken'])
            ->postJson(route('games.draw', $table['game']->code), ['source' => 'stock'])
            ->assertStatus(422)
            ->assertJson(['message' => 'It is not your turn yet.']);
    });

    it('rejects an unknown draw source', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.draw', $table['game']->code), ['source' => 'sleeve'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source');
    });
});

describe('discarding', function (): void {
    it('passes the turn to the next player', function (): void {
        $table = dealtGame(['3H', '4H', '5H'], ['9C'], phase: TurnPhase::Discard);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.discard', $table['game']->code), ['card' => '5H'])
            ->assertOk()
            ->assertJsonPath('private.hand', ['3H', '4H'])
            ->assertJsonPath('state.discardTop', '5H')
            ->assertJsonPath('state.currentPlayerId', $table['opponent']->id)
            ->assertJsonPath('state.turnPhase', 'draw');
    });

    it('refuses to discard a card the player does not hold', function (): void {
        $table = dealtGame(['3H', '4H', '5H'], ['9C'], phase: TurnPhase::Discard);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.discard', $table['game']->code), ['card' => '9C'])
            ->assertStatus(422)
            ->assertJson(['message' => 'You do not hold that card.']);

        expect($table['opponent']->fresh()->hand)->toBe(['9C']);
    });

    it('refuses to discard before drawing', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C']);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.discard', $table['game']->code), ['card' => '3H'])
            ->assertStatus(422)
            ->assertJson(['message' => 'You must draw a card before discarding.']);
    });

    it('rejects a card code that is not a real card', function (): void {
        $table = dealtGame(['3H', '4H', '5H'], ['9C'], phase: TurnPhase::Discard);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.discard', $table['game']->code), ['card' => '11Z'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('card');
    });
});

describe('declaring done', function (): void {
    $winning = ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', '8C'];

    it('ends the game and names the winner', function () use ($winning): void {
        $table = dealtGame([...$winning, 'KS'], ['9C'], phase: TurnPhase::Discard);

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.declare', $table['game']->code), ['card' => 'KS'])
            ->assertOk()
            ->assertJsonPath('state.status', 'completed')
            ->assertJsonPath('state.winnerPlayerId', $table['current']->id);

        $game = $table['game']->fresh();

        expect($game->status)->toBe(GameStatus::Completed)
            ->and($game->completed_at)->not->toBeNull()
            ->and($game->current_player_id)->toBeNull();
    });

    it('reveals the winning melds to the whole table', function () use ($winning): void {
        $table = dealtGame([...$winning, 'KS'], ['9C'], phase: TurnPhase::Discard);

        $response = asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.declare', $table['game']->code), ['card' => 'KS']);

        $melds = $response->json('state.winningMelds');

        expect($melds)->toHaveCount(3)
            ->and(array_merge(...$melds))->toHaveCount(10);
    });

    it('rejects a hand that does not fully meld and leaves the game running', function (): void {
        $table = dealtGame(
            ['3H', '4H', '5H', '9C', '9D', '9S', '5C', '6C', '7C', 'KS', '2D'],
            ['9H'],
            phase: TurnPhase::Discard,
        );

        asPlayer($table['game']->code, $table['currentToken'])
            ->postJson(route('games.declare', $table['game']->code), ['card' => '2D'])
            ->assertStatus(422)
            ->assertJson(['message' => 'This hand is not a valid winning hand.']);

        $game = $table['game']->fresh();

        expect($game->status)->toBe(GameStatus::Playing)
            ->and($table['current']->fresh()->hand)->toHaveCount(11);
    });

    it('refuses further moves once the game is over', function () use ($winning): void {
        $table = dealtGame([...$winning, 'KS'], ['9C'], phase: TurnPhase::Discard);
        $code = $table['game']->code;

        asPlayer($code, $table['currentToken'])
            ->postJson(route('games.declare', $code), ['card' => 'KS'])
            ->assertOk();

        asPlayer($code, $table['opponentToken'])
            ->postJson(route('games.draw', $code), ['source' => 'stock'])
            ->assertStatus(422)
            ->assertJson(['message' => 'This game is not in play.']);
    });

    it('refuses a declaration out of turn', function () use ($winning): void {
        $table = dealtGame(['3H'], $winning);

        asPlayer($table['game']->code, $table['opponentToken'])
            ->postJson(route('games.declare', $table['game']->code))
            ->assertStatus(422)
            ->assertJson(['message' => 'It is not your turn yet.']);
    });
});

describe('a full turn cycle', function (): void {
    it('alternates between the two seats', function (): void {
        $table = dealtGame(['3H', '4H'], ['9C', '9D'], stock: ['2D', '7S', 'AC', 'QH']);
        $code = $table['game']->code;

        asPlayer($code, $table['currentToken'])->postJson(route('games.draw', $code), ['source' => 'stock'])->assertOk();
        asPlayer($code, $table['currentToken'])->postJson(route('games.discard', $code), ['card' => '3H'])->assertOk();

        asPlayer($code, $table['opponentToken'])->postJson(route('games.draw', $code), ['source' => 'discard'])->assertOk();

        $response = asPlayer($code, $table['opponentToken'])
            ->postJson(route('games.discard', $code), ['card' => '9D']);

        $response->assertOk()->assertJsonPath('state.currentPlayerId', $table['current']->id);

        expect($table['opponent']->fresh()->hand)->toBe(['9C', '3H']);
    });
});
