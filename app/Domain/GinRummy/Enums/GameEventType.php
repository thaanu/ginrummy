<?php

declare(strict_types=1);

namespace App\Domain\GinRummy\Enums;

enum GameEventType: string
{
    case GameCreated = 'game_created';
    case PlayerJoined = 'player_joined';
    case PlayerLeft = 'player_left';
    case GameStarted = 'game_started';
    case CardDrawn = 'card_drawn';
    case CardDiscarded = 'card_discarded';
    case TurnChanged = 'turn_changed';
    case PlayerDisconnected = 'player_disconnected';
    case PlayerReconnected = 'player_reconnected';
    case StockReshuffled = 'stock_reshuffled';
    case DeclareRejected = 'declare_rejected';
    case PlayerWon = 'player_won';
    case GameCompleted = 'game_completed';
}
