<script setup lang="ts">
import CardHand from '@/components/game/CardHand.vue';
import DeckPile from '@/components/game/DeckPile.vue';
import DiscardPile from '@/components/game/DiscardPile.vue';
import PlayerSeat from '@/components/game/PlayerSeat.vue';
import PlayingCard from '@/components/game/PlayingCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import type {
    CardCode,
    DrawSource,
    GamePlayer,
    PublicGameState,
} from '@/types/game';
import { usePileDraw } from '@/composables/usePileDraw';
import { computed, ref, toRef, watch } from 'vue';

const props = defineProps<{
    state: PublicGameState;
    hand: CardCode[];
    opponents: GamePlayer[];
    me: GamePlayer | undefined;
    currentPlayer: GamePlayer | undefined;
    winner: GamePlayer | undefined;
    selectedCard: CardCode | null;
    spotlitCard: CardCode | null;
    meldGroupOf: (card: CardCode) => number;
    busy: boolean;
    isMyTurn: boolean;
    isCompleted: boolean;
    canDraw: boolean;
    isDiscardPhase: boolean;
    canDeclare: boolean;
    ginIsWaitingOnSelection: boolean;
    ginIsWaitingOnTurn: boolean;
    completesGin: (card: CardCode) => boolean;
}>();

const emit = defineEmits<{
    select: [card: CardCode];
    reorder: [order: CardCode[]];
    draw: [source: DrawSource];
    discard: [card: CardCode];
    declare: [card: CardCode | null];
}>();

/** The pile doubles as the place a card is dropped to play it. */
const discardZone = ref<HTMLElement | null>(null);
const holdingOverPile = ref(false);

/**
 * Putting down the card that completes a hand is going gin, not discarding it.
 * Treating it as an ordinary discard would end the turn and quietly throw the
 * win away, which is exactly what a player would not expect.
 */
function play(card: CardCode): void {
    if (props.completesGin(card)) {
        emit('declare', card);
    } else {
        emit('discard', card);
    }
}

function playSelected(): void {
    if (props.selectedCard !== null) {
        play(props.selectedCard);
    }
}

/**
 * Cards can be carried out of either pile as well as clicked. The card is taken
 * the moment the drag begins, so a stock card can be turned face up in the
 * player's hand and cannot be put back.
 */
const pile = usePileDraw({
    enabled: toRef(props, 'canDraw'),
    onDraw: (from) => emit('draw', from),
});

function drawFrom(source: DrawSource): void {
    if (!pile.consumedClick()) {
        emit('draw', source);
    }
}

/**
 * The card currently in the player's grip on its way out of a pile. It is
 * already theirs, but showing it in the hand as well as under the pointer would
 * look like they had drawn two, so the hand holds it back until they let go.
 */
const carried = ref<CardCode | null>(null);

watch(
    () => props.hand,
    (after, before) => {
        if (pile.drawn.value && after.length === before.length + 1) {
            carried.value =
                after.find((card) => !before.includes(card)) ?? null;
        }
    },
);

watch(pile.drawn, (drawing) => {
    if (!drawing) {
        carried.value = null;
    }
});

const cardsInHand = computed(() =>
    carried.value === null
        ? props.hand
        : props.hand.filter((card) => card !== carried.value),
);

const selectionGoesGin = computed(
    () => props.selectedCard !== null && props.completesGin(props.selectedCard),
);

const turnMessage = computed(() => {
    if (props.isCompleted) {
        return props.winner
            ? `${props.winner.nickname} went gin`
            : 'The game ended with no winner';
    }

    if (!props.isMyTurn) {
        return `Waiting for ${props.currentPlayer?.nickname ?? 'the next player'}`;
    }

    return props.state.turnPhase === 'draw'
        ? 'Your turn — draw a card'
        : 'Your turn — discard a card';
});

const hint = computed(() => {
    if (props.isCompleted) {
        return null;
    }

    if (props.ginIsWaitingOnTurn) {
        return 'You have gin. Declare it as soon as your turn comes round.';
    }

    if (props.ginIsWaitingOnSelection) {
        return 'You can go gin — put your odd card on the pile to win.';
    }

    if (props.isMyTurn && props.state.turnPhase === 'discard') {
        return 'Drag a card onto the pile to play it, or tap a card and then the pile.';
    }

    if (props.isMyTurn) {
        return 'Take a card from either pile — click it, or drag it into your hand.';
    }

    return 'Drag your cards to rearrange them while you wait.';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 sm:gap-6">
        <section
            class="flex flex-wrap items-center justify-center gap-2 sm:gap-3"
            aria-label="Opponents"
        >
            <PlayerSeat
                v-for="opponent in opponents"
                :key="opponent.id"
                :player="opponent"
                :is-current="state.currentPlayerId === opponent.id"
                :is-winner="state.winnerPlayerId === opponent.id"
            />
        </section>

        <section
            class="relative flex flex-col items-center gap-4 rounded-3xl border border-slate-200 bg-white/80 px-4 py-5 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_16px_40px_-24px_rgba(16,24,40,0.25)] sm:gap-6 sm:px-8 sm:py-8"
        >
            <p
                :class="[
                    'rounded-full px-4 py-1.5 text-center text-[0.7rem] font-semibold tracking-[0.15em] uppercase transition-colors sm:text-xs',
                    isCompleted
                        ? 'bg-amber-100 text-amber-800'
                        : isMyTurn
                          ? 'bg-teal-600 text-white'
                          : 'bg-slate-100 text-slate-500',
                ]"
            >
                {{ turnMessage }}
            </p>

            <div class="flex items-start gap-8 sm:gap-12">
                <DeckPile
                    :count="state.stockCount"
                    :enabled="canDraw && !busy"
                    @draw="drawFrom('stock')"
                    @pointer-down="pile.start($event, 'stock')"
                />
                <div ref="discardZone">
                    <DiscardPile
                        :top="state.discardTop"
                        :count="state.discardCount"
                        :can-draw="
                            canDraw && !busy && state.discardTop !== null
                        "
                        :can-discard="isDiscardPhase && !busy"
                        :drop-active="holdingOverPile"
                        :has-selection="selectedCard !== null"
                        :selection-goes-gin="selectionGoesGin"
                        @draw="drawFrom('discard')"
                        @pointer-down="pile.start($event, 'discard')"
                        @discard="playSelected"
                    />
                </div>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="pile.position.value"
                class="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-1/2"
                :style="{
                    left: `${pile.position.value.x}px`,
                    top: `${pile.position.value.y}px`,
                }"
                aria-hidden="true"
            >
                <PlayingCard
                    :card="carried"
                    :face-down="carried === null"
                    lifted
                />
            </div>
        </Teleport>

        <section
            v-if="isCompleted && state.winningMelds"
            class="rounded-3xl border border-amber-200 bg-amber-50/70 px-3 py-5 text-center sm:px-8"
        >
            <h2
                class="text-sm font-semibold tracking-wide text-amber-900 uppercase"
            >
                {{ winner?.nickname }} wins
            </h2>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <div
                    v-for="(meld, index) in state.winningMelds"
                    :key="index"
                    class="flex gap-1 rounded-xl bg-white/70 p-2 ring-1 ring-amber-200"
                >
                    <PlayingCard
                        v-for="card in meld"
                        :key="card"
                        :card="card"
                        size="sm"
                    />
                </div>
            </div>
        </section>

        <section
            class="sticky bottom-0 z-20 -mx-4 mt-auto border-t border-slate-200 bg-[#F7F6F3]/95 px-4 pb-2 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:pb-0"
            aria-label="Your hand"
        >
            <div class="flex items-center justify-between px-1">
                <p
                    class="text-[0.65rem] font-medium tracking-[0.2em] text-slate-500 uppercase sm:text-xs"
                >
                    {{ me?.nickname ?? 'Your hand' }} · {{ hand.length }} cards
                </p>
                <span
                    v-if="me && !me.connected"
                    class="text-xs font-medium text-amber-600"
                >
                    Reconnecting…
                </span>
            </div>

            <CardHand
                :cards="cardsInHand"
                :selected="selectedCard"
                :spotlit="spotlitCard"
                :meld-group-of="meldGroupOf"
                :interactive="isMyTurn && !isCompleted && !busy"
                :sortable="!isCompleted && carried === null"
                :drop-zone="discardZone"
                :can-drop="isDiscardPhase && !busy"
                @select="(card) => emit('select', card)"
                @reorder="(order) => emit('reorder', order)"
                @drop="play"
                @drop-hover="(over) => (holdingOverPile = over)"
            />

            <div
                v-if="!isCompleted"
                class="flex flex-wrap items-center justify-center gap-3"
            >
                <AppButton
                    :disabled="!canDeclare || busy"
                    @click="emit('declare', selectedCard)"
                >
                    Gin
                </AppButton>
            </div>

            <p
                v-if="hint"
                class="mt-2 text-center text-xs"
                :class="
                    ginIsWaitingOnSelection
                        ? 'font-medium text-teal-700'
                        : 'text-slate-500'
                "
            >
                {{ hint }}
            </p>
        </section>
    </div>
</template>
