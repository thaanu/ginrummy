export type CardCode = string;

export type GameStatus = 'waiting' | 'playing' | 'completed';

export type TurnPhase = 'draw' | 'discard';

export type DrawSource = 'stock' | 'discard';

export interface GamePlayer {
    id: number;
    nickname: string;
    seat: number;
    isHost: boolean;
    cardCount: number;
    connected: boolean;
}

/** The table-wide state every player is allowed to see. */
export interface PublicGameState {
    code: string;
    status: GameStatus;
    turnPhase: TurnPhase | null;
    hostPlayerId: number | null;
    currentPlayerId: number | null;
    winnerPlayerId: number | null;
    stockCount: number;
    discardCount: number;
    discardTop: CardCode | null;
    winningMelds: CardCode[][] | null;
    players: GamePlayer[];
}

/**
 * One player's own cards, and what the server can tell them about those cards.
 * Never sent to anyone else.
 */
export interface PrivateGameState {
    playerId: number;
    hand: CardCode[];
    /** Groups of the player's cards that currently form a run or a set. */
    melds: CardCode[][];
    /** True when these ten cards already go out. */
    canGoGin: boolean;
    /** Cards which, if put down, would leave a winning hand. */
    ginDiscards: CardCode[];
}

export interface GameStatePayload {
    reason: string;
    state: PublicGameState;
}

export type ConnectionState = 'connecting' | 'connected' | 'disconnected';
