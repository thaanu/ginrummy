<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\GinRummy\Enums\GameEventType;
use App\Models\Game;
use App\Services\GamePresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcasts the sanitized, table-wide view of a game to every seated player.
 *
 * This payload deliberately never carries a hand: opponents only ever learn how
 * many cards someone holds.
 */
final class GameStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Game $game,
        public readonly GameEventType $reason,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->game->code}")];
    }

    public function broadcastAs(): string
    {
        return 'game.state';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason->value,
            'state' => app(GamePresenter::class)->publicState($this->game),
        ];
    }
}
