import { post, ApiError } from '@/lib/api';
import * as games from '@/routes/games';
import type {
    CardCode,
    DrawSource,
    GamePlayer,
    GameStatePayload,
    PrivateGameState,
    PublicGameState,
} from '@/types/game';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    type Ref,
} from 'vue';

interface ActionResponse {
    state: PublicGameState;
    private: PrivateGameState;
}

/**
 * The heartbeat keeps a seat marked as present and quietly repairs the view if a
 * broadcast was ever missed. While the socket is up it can stay lazy; while it
 * is down it is the only thing keeping the game playable, so it speeds up.
 */
const PRESENCE_INTERVAL_MS = 15_000;

const OFFLINE_PRESENCE_INTERVAL_MS = 2_500;

/** How long a freshly drawn card stays lit up. */
const DRAWN_SPOTLIGHT_MS = 1_000;

/**
 * The client's view of a game.
 *
 * It holds no rules of its own: every move is a request to the server, and the
 * state it exposes is whatever the server last told it, whether that arrived
 * over the WebSocket or as the reply to a move.
 */
export function useGame(
    initialState: PublicGameState,
    initialPrivate: PrivateGameState,
    playerId: number,
) {
    const state = ref(initialState) as Ref<PublicGameState>;
    const hand = ref([...initialPrivate.hand]) as Ref<CardCode[]>;
    const melds = ref(initialPrivate.melds) as Ref<CardCode[][]>;
    const canGoGin = ref(initialPrivate.canGoGin);
    const ginDiscards = ref([...initialPrivate.ginDiscards]) as Ref<CardCode[]>;
    const justDrawn = ref<CardCode | null>(null);
    const selectedCard = ref<CardCode | null>(null);
    const errorMessage = ref<string | null>(null);
    const busy = ref(false);
    const lastEvent = ref<string | null>(null);
    const connection = useConnectionStatus();

    const code = computed(() => state.value.code);
    const players = computed(() => state.value.players);
    const me = computed<GamePlayer | undefined>(() =>
        state.value.players.find((player) => player.id === playerId),
    );
    const opponents = computed(() =>
        state.value.players.filter((player) => player.id !== playerId),
    );
    const currentPlayer = computed<GamePlayer | undefined>(() =>
        state.value.players.find(
            (player) => player.id === state.value.currentPlayerId,
        ),
    );
    const winner = computed<GamePlayer | undefined>(() =>
        state.value.players.find(
            (player) => player.id === state.value.winnerPlayerId,
        ),
    );

    const isHost = computed(() => state.value.hostPlayerId === playerId);
    const isMyTurn = computed(() => state.value.currentPlayerId === playerId);
    const isPlaying = computed(() => state.value.status === 'playing');
    const isCompleted = computed(() => state.value.status === 'completed');

    const canDraw = computed(
        () =>
            isPlaying.value &&
            isMyTurn.value &&
            state.value.turnPhase === 'draw',
    );
    /**
     * The player owes the table a card. A card is played by dropping it on the
     * discard pile, so this gates the drop zone rather than a button.
     */
    const isDiscardPhase = computed(
        () =>
            isPlaying.value &&
            isMyTurn.value &&
            state.value.turnPhase === 'discard',
    );
    /**
     * Gin only lights up when the hand actually goes out: on ten cards that is
     * the hand itself, and on eleven it is the hand minus the card the player
     * has picked to put down. The server decides both.
     */
    const canDeclare = computed(() => {
        if (!isPlaying.value || !isMyTurn.value) {
            return false;
        }

        return state.value.turnPhase === 'draw'
            ? canGoGin.value
            : selectedCard.value !== null &&
                  ginDiscards.value.includes(selectedCard.value);
    });

    /** True when a gin is available but the player has not picked the discard. */
    const ginIsWaitingOnSelection = computed(
        () =>
            isPlaying.value &&
            isMyTurn.value &&
            state.value.turnPhase === 'discard' &&
            ginDiscards.value.length > 0 &&
            !canDeclare.value,
    );

    /** Which meld group a card belongs to, or -1 when it is deadwood. */
    const meldGroupOf = (card: CardCode): number =>
        melds.value.findIndex((meld) => meld.includes(card));

    /** Putting this card down would leave a hand that goes out. */
    const completesGin = (card: CardCode): boolean =>
        ginDiscards.value.includes(card);

    /**
     * The hand already goes out, but the turn has moved on. The player has to
     * wait for it to come round again before they can declare.
     */
    const ginIsWaitingOnTurn = computed(
        () => isPlaying.value && !isMyTurn.value && canGoGin.value,
    );
    const canStart = computed(
        () =>
            state.value.status === 'waiting' &&
            isHost.value &&
            state.value.players.length >= 2,
    );

    function selectCard(card: CardCode): void {
        selectedCard.value = selectedCard.value === card ? null : card;
    }

    /**
     * Adopts whatever the server just told us. A selection is kept only while
     * the card it points at is still in hand.
     */
    function apply(response: ActionResponse): void {
        state.value = response.state;
        setPrivate(response.private);
    }

    function setPrivate(next: PrivateGameState): void {
        setHand(next.hand);
        melds.value = next.melds;
        canGoGin.value = next.canGoGin;
        ginDiscards.value = next.ginDiscards;
    }

    let spotlightTimer: ReturnType<typeof setTimeout> | undefined;

    function setHand(cards: CardCode[]): void {
        spotlightPickedUp(hand.value, cards);

        hand.value = cards;

        if (
            selectedCard.value !== null &&
            !cards.includes(selectedCard.value)
        ) {
            selectedCard.value = null;
        }
    }

    /**
     * A card arriving from a pile is easy to lose in a fanned hand, so the one
     * just picked up is lit briefly. Rearranging never triggers this, because
     * the cards themselves have not changed.
     */
    function spotlightPickedUp(before: CardCode[], after: CardCode[]): void {
        if (after.length !== before.length + 1) {
            return;
        }

        const picked = after.find((card) => !before.includes(card));

        if (picked === undefined) {
            return;
        }

        clearTimeout(spotlightTimer);
        justDrawn.value = picked;
        spotlightTimer = setTimeout(
            () => (justDrawn.value = null),
            DRAWN_SPOTLIGHT_MS,
        );
    }

    async function run(action: () => Promise<ActionResponse>): Promise<void> {
        if (busy.value) {
            return;
        }

        busy.value = true;
        errorMessage.value = null;

        try {
            apply(await action());
            selectedCard.value = null;
        } catch (error) {
            errorMessage.value =
                error instanceof ApiError
                    ? error.message
                    : 'Something went wrong. Please try again.';
        } finally {
            busy.value = false;
        }
    }

    const startGame = () =>
        run(() => post<ActionResponse>(games.start.url(code.value)));

    const draw = (source: DrawSource) =>
        run(() => post<ActionResponse>(games.draw.url(code.value), { source }));

    const discard = (card: CardCode) =>
        run(() =>
            post<ActionResponse>(games.discard.url(code.value), { card }),
        );

    /**
     * Claims the win, putting down the named card first when the player is
     * still holding eleven.
     */
    const declareGin = (card: CardCode | null = null) =>
        run(() =>
            post<ActionResponse>(games.declare.url(code.value), {
                card:
                    card ??
                    (state.value.turnPhase === 'discard'
                        ? selectedCard.value
                        : null),
            }),
        );

    /**
     * Saves the order the player dragged their cards into.
     *
     * Applied locally first so the cards stay under the finger that moved them,
     * then persisted. Order carries no meaning in the rules, so a failure here
     * is not worth interrupting anyone over: the next response puts the hand
     * back the way the server has it.
     */
    async function sortHand(order: CardCode[]): Promise<void> {
        hand.value = order;

        try {
            apply(
                await post<ActionResponse>(games.sort.url(code.value), {
                    hand: order,
                }),
            );
        } catch {
            // The heartbeat will restore the order the server has saved.
        }
    }

    useEcho(`game.${initialState.code}`, '.game.state', (payload) => {
        const update = payload as GameStatePayload;

        state.value = update.state;
        lastEvent.value = update.reason;
    });

    useEcho(
        `game.${initialState.code}.player.${playerId}`,
        '.player.hand',
        (payload) => {
            setPrivate(payload as PrivateGameState);
        },
    );

    let heartbeat: ReturnType<typeof setTimeout> | undefined;

    /**
     * A quiet heartbeat that keeps this seat marked as present and repairs the
     * view if a broadcast was ever missed.
     */
    async function reportPresence(): Promise<void> {
        try {
            apply(await post<ActionResponse>(games.presence.url(code.value)));
        } catch {
            // Presence is best effort; a failed beat simply shows as offline.
        }
    }

    function scheduleHeartbeat(): void {
        clearTimeout(heartbeat);

        heartbeat = setTimeout(
            () => {
                void reportPresence().finally(scheduleHeartbeat);
            },
            connection.value === 'connected'
                ? PRESENCE_INTERVAL_MS
                : OFFLINE_PRESENCE_INTERVAL_MS,
        );
    }

    onMounted(() => {
        void reportPresence();
        scheduleHeartbeat();
    });

    // Dropping offline, or coming back, changes how often it is worth asking
    // the server what happened while we were not listening.
    watch(connection, scheduleHeartbeat);

    onBeforeUnmount(() => {
        clearTimeout(heartbeat);
        clearTimeout(spotlightTimer);
    });

    return {
        state,
        hand,
        melds,
        canGoGin,
        ginDiscards,
        justDrawn,
        players,
        opponents,
        me,
        currentPlayer,
        winner,
        selectedCard,
        errorMessage,
        busy,
        connection,
        lastEvent,
        code,
        isHost,
        isMyTurn,
        isPlaying,
        isCompleted,
        canDraw,
        isDiscardPhase,
        canDeclare,
        canStart,
        ginIsWaitingOnSelection,
        ginIsWaitingOnTurn,
        meldGroupOf,
        completesGin,
        selectCard,
        startGame,
        draw,
        discard,
        declareGin,
        sortHand,
    };
}
