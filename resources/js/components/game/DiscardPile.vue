<script setup lang="ts">
import PlayingCard from '@/components/game/PlayingCard.vue';
import { cn } from '@/lib/utils';
import type { CardCode } from '@/types/game';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        top: CardCode | null;
        count: number;
        /** The top card can be taken into the hand. */
        canDraw?: boolean;
        /** A card from the hand can be played onto the pile. */
        canDiscard?: boolean;
        /** A card is currently being held over the pile. */
        dropActive?: boolean;
        /** A card has been chosen, so a click here would play it. */
        hasSelection?: boolean;
        /** Playing the chosen card would win the game. */
        selectionGoesGin?: boolean;
    }>(),
    {
        canDraw: false,
        canDiscard: false,
        dropActive: false,
        hasSelection: false,
        selectionGoesGin: false,
    },
);

const emit = defineEmits<{
    draw: [];
    discard: [];
    'pointer-down': [event: PointerEvent];
}>();

const interactive = computed(
    () => props.canDraw || (props.canDiscard && props.hasSelection),
);

const label = computed(() => {
    if (props.canDiscard) {
        if (props.selectionGoesGin) {
            return 'Put the chosen card down and go gin';
        }

        return props.hasSelection
            ? 'Play the chosen card onto the discard pile'
            : 'Drop a card here to discard it';
    }

    return props.canDraw ? 'Take the top of the discard pile' : 'Discard pile';
});

function activate(): void {
    if (props.canDiscard && props.hasSelection) {
        emit('discard');
    } else if (props.canDraw) {
        emit('draw');
    }
}
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <component
            :is="interactive ? 'button' : 'div'"
            :type="interactive ? 'button' : undefined"
            :aria-label="label"
            :class="
                cn(
                    'relative block rounded-xl transition-all duration-200',
                    interactive &&
                        'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600',
                    canDiscard && 'ring-2 ring-teal-500/50 ring-offset-4',
                    canDiscard && !dropActive && 'animate-pulse',
                    dropActive &&
                        'scale-110 ring-4 ring-teal-500 ring-offset-4',
                )
            "
            @click="activate"
            @pointerdown="canDraw && emit('pointer-down', $event)"
        >
            <Transition name="flip" mode="out-in">
                <PlayingCard
                    v-if="top"
                    :key="top"
                    :card="top"
                    :interactive="interactive"
                />
                <div
                    v-else
                    class="flex h-24 w-[4.25rem] items-center justify-center rounded-xl border-2 border-dashed border-slate-300 px-2 text-center text-[0.65rem] leading-tight text-slate-400 sm:h-28 sm:w-20"
                >
                    No discards yet
                </div>
            </Transition>
        </component>

        <p
            :class="
                cn(
                    'text-xs font-medium tracking-wide uppercase transition-colors',
                    canDiscard ? 'text-teal-700' : 'text-slate-500',
                )
            "
        >
            {{
                canDiscard
                    ? selectionGoesGin
                        ? 'Drop here to go gin'
                        : 'Drop here'
                    : `Discard · ${count}`
            }}
        </p>
    </div>
</template>

<style scoped>
.flip-enter-active,
.flip-leave-active {
    transition: all 0.2s ease;
}

.flip-enter-from {
    opacity: 0;
    transform: rotateY(90deg) scale(0.92);
}

.flip-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
