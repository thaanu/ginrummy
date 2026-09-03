<script setup lang="ts">
import PlayingCard from '@/components/game/PlayingCard.vue';
import { moveItem, useHandReorder } from '@/composables/useHandReorder';
import type { CardCode } from '@/types/game';
import { computed, ref, toRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        cards: CardCode[];
        selected?: CardCode | null;
        spotlit?: CardCode | null;
        /** Which meld each card belongs to, or -1 for deadwood. */
        meldGroupOf?: (card: CardCode) => number;
        interactive?: boolean;
        sortable?: boolean;
        /** Where a card can be dropped to play it. */
        dropZone?: HTMLElement | null;
        canDrop?: boolean;
    }>(),
    {
        selected: null,
        spotlit: null,
        meldGroupOf: () => -1,
        interactive: false,
        sortable: false,
        dropZone: null,
        canDrop: false,
    },
);

const emit = defineEmits<{
    select: [card: CardCode];
    reorder: [order: CardCode[]];
    drop: [card: CardCode];
    'drop-hover': [over: boolean];
}>();

const row = ref<HTMLElement | null>(null);

const reorder = useHandReorder({
    count: computed(() => props.cards.length),
    enabled: toRef(props, 'sortable'),
    onReorder: (from, to) => emit('reorder', moveItem(props.cards, from, to)),
    onTap: (index) => props.interactive && emit('select', props.cards[index]),
    dropZone: computed(() => props.dropZone),
    canDrop: toRef(props, 'canDrop'),
    onDrop: (index) => emit('drop', props.cards[index]),
});

watch(reorder.overDropZone, (over) => emit('drop-hover', over));

/**
 * Moves a card with the keyboard, so rearranging does not depend on being able
 * to drag.
 */
function nudge(index: number, direction: -1 | 1): void {
    const to = index + direction;

    if (!props.sortable || to < 0 || to >= props.cards.length) {
        return;
    }

    emit('reorder', moveItem(props.cards, index, to));
}

/**
 * How far the card being dragged is raised out of the fan, so it stays readable
 * and clear of the finger holding it.
 */
const DRAG_LIFT_PX = 30;

function isBeingDragged(index: number): boolean {
    return reorder.draggingIndex.value === index && reorder.isDragging.value;
}

/**
 * Each slot carries its own transform, which makes it a stacking context, so a
 * z-index on the card inside can never lift it over the next slot. Raising the
 * slot itself is what actually brings a card to the front of the fan.
 */
function layerFor(index: number): number | undefined {
    if (isBeingDragged(index)) {
        return 50;
    }

    if (props.spotlit !== null && props.cards[index] === props.spotlit) {
        return 40;
    }

    return props.selected !== null && props.cards[index] === props.selected
        ? 30
        : undefined;
}

function transformFor(index: number): string {
    if (isBeingDragged(index)) {
        const { x, y } = reorder.offset.value;

        return `translate(${x}px, ${y - DRAG_LIFT_PX}px)`;
    }

    if (reorder.overDropZone.value) {
        // While the card is held over the pile it is leaving the hand rather
        // than moving within it, so the others stay put and its own slot is
        // left standing open behind it.
        return '';
    }

    const shift = reorder.shiftFor(index);

    return shift.x === 0 && shift.y === 0
        ? ''
        : `translate(${shift.x}px, ${shift.y}px)`;
}
</script>

<template>
    <div
        class="landscape-phone:min-w-0 landscape-phone:flex-1 landscape-phone:px-0 landscape-phone:pt-4 landscape-phone:pb-1 w-full px-2 pt-6 pb-2 sm:px-3 sm:pb-3"
    >
        <div
            ref="row"
            class="landscape-phone:flex-nowrap landscape-phone:gap-y-0 mx-auto flex flex-wrap items-end justify-center gap-y-3 sm:flex-nowrap sm:gap-y-0"
            :class="reorder.isDragging.value && 'select-none'"
        >
            <div
                v-for="(card, index) in cards"
                :key="card"
                data-card-slot
                class="landscape-phone:-mr-4 -mr-4 last:mr-0 sm:-mr-2"
                :style="{
                    transform: transformFor(index),
                    zIndex: layerFor(index),
                    transition:
                        reorder.draggingIndex.value === index
                            ? 'none'
                            : 'transform 180ms ease',
                    touchAction: sortable ? 'pan-y' : undefined,
                }"
                @pointerdown="reorder.start($event, index, row)"
                @pointermove="reorder.move"
                @pointerup="reorder.end"
                @pointercancel="reorder.cancel"
            >
                <PlayingCard
                    :card="card"
                    :selected="selected === card"
                    :spotlit="spotlit === card"
                    :meld-group="meldGroupOf(card)"
                    :lifted="isBeingDragged(index)"
                    :interactive="interactive || sortable"
                    @keydown.left.alt.prevent="nudge(index, -1)"
                    @keydown.right.alt.prevent="nudge(index, 1)"
                    @keydown.enter.prevent="interactive && emit('select', card)"
                    @keydown.space.prevent="interactive && emit('select', card)"
                />
            </div>
        </div>
    </div>
</template>
