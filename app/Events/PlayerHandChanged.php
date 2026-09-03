<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Player;
use App\Services\GamePresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Delivers a hand to the single player who owns it, on a channel only that
 * player can subscribe to.
 */
final class PlayerHandChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly Player $player) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->player->game->code}.player.{$this->player->id}")];
    }

    public function broadcastAs(): string
    {
        return 'player.hand';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return app(GamePresenter::class)->privateState($this->player);
    }
}
