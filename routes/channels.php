<?php

declare(strict_types=1);

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Both channels are private. The "player" guard has already proven that the
| request carries a valid token for the game named in the channel; these
| callbacks confirm the player is allowed on that specific channel.
|
*/

Broadcast::channel('game.{code}', function (Player $player, string $code): bool {
    return $player->game->code === $code;
}, ['guards' => ['player']]);

Broadcast::channel('game.{code}.player.{playerId}', function (Player $player, string $code, string $playerId): bool {
    return $player->game->code === $code && $player->id === (int) $playerId;
}, ['guards' => ['player']]);
