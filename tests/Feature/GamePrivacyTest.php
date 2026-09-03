<?php

declare(strict_types=1);

use App\Domain\GinRummy\Enums\GameEventType;
use App\Events\GameStateChanged;
use App\Events\PlayerHandChanged;
use App\Models\Game;
use App\Services\GamePresenter;

beforeEach(function (): void {
    $this->game = Game::factory()->playing()->create();

    [$this->ahmed, $this->ahmedToken] = seatPlayer($this->game, 'Ahmed', 1, host: true);
    [$this->john, $this->johnToken] = seatPlayer($this->game, 'John', 2);

    $this->ahmed->forceFill(['hand' => ['2H', '5S', 'KD']])->save();
    $this->john->forceFill(['hand' => ['AC', '7D', 'QS']])->save();

    $this->game->forceFill([
        'turn_phase' => 'draw',
        'current_player_id' => $this->ahmed->id,
        'player_order' => [$this->ahmed->id, $this->john->id],
        'stock' => ['9C'],
        'discard' => ['4D'],
    ])->save();

    $this->game->load('players');
});

it('describes opponents by card count and never by card', function (): void {
    $state = app(GamePresenter::class)->publicState($this->game);

    expect($state['players'])->toHaveCount(2)
        ->and($state['players'][1]['cardCount'])->toBe(3)
        ->and(json_encode($state))->not->toContain('QS')
        ->and(json_encode($state))->not->toContain('AC')
        ->and($state['discardTop'])->toBe('4D')
        ->and($state['stockCount'])->toBe(1);
});

it('never leaks another hand through a move response', function (): void {
    $response = asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.draw', $this->game->code), ['source' => 'stock']);

    $body = $response->getContent();

    expect($response->json('private.hand'))->toBe(['2H', '5S', 'KD', '9C'])
        ->and($body)->not->toContain('QS')
        ->and($body)->not->toContain('7D');
});

it('sends a hand only on that player\'s own channel', function (): void {
    $event = new PlayerHandChanged($this->john);

    expect($event->broadcastOn()[0]->name)
        ->toBe("private-game.{$this->game->code}.player.{$this->john->id}")
        ->and($event->broadcastWith()['hand'])->toBe(['AC', '7D', 'QS']);
});

it('broadcasts no hand at all on the table channel', function (): void {
    $event = new GameStateChanged($this->game, GameEventType::CardDrawn);

    expect($event->broadcastOn()[0]->name)->toBe("private-game.{$this->game->code}")
        ->and(json_encode($event->broadcastWith()))->not->toContain('QS');
});

it('hides the session token from any serialized player', function (): void {
    expect(json_encode($this->ahmed))->not->toContain($this->ahmed->session_token_hash)
        ->and(json_encode($this->ahmed))->not->toContain('5S');
});

it('never writes a raw token into the audit trail', function (): void {
    asPlayer($this->game->code, $this->ahmedToken)
        ->postJson(route('games.draw', $this->game->code), ['source' => 'stock']);

    $payloads = $this->game->events()->pluck('payload')->toJson();

    expect($payloads)->not->toContain($this->ahmedToken);
});
