<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { CardCode } from '@/types/game';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        card?: CardCode | null;
        faceDown?: boolean;
        selected?: boolean;
        interactive?: boolean;
        size?: 'xs' | 'sm' | 'md';
        /** Which meld this card belongs to, or -1 when it is deadwood. */
        meldGroup?: number;
        /** Lights the card up, for the card that was just picked up. */
        spotlit?: boolean;
        lifted?: boolean;
    }>(),
    {
        card: null,
        faceDown: false,
        selected: false,
        interactive: false,
        size: 'md',
        meldGroup: -1,
        spotlit: false,
        lifted: false,
    },
);

/**
 * One tint per meld, so two runs sitting next to each other stay tellable
 * apart. Kept muted: this is a hint, not the main event.
 */
const MELD_TINTS = [
    { edge: 'bg-teal-500', ring: 'ring-teal-400/70' },
    { edge: 'bg-violet-500', ring: 'ring-violet-400/70' },
    { edge: 'bg-amber-500', ring: 'ring-amber-400/70' },
    { edge: 'bg-sky-500', ring: 'ring-sky-400/70' },
] as const;

const meld = computed(() =>
    props.meldGroup >= 0 && !props.faceDown
        ? MELD_TINTS[props.meldGroup % MELD_TINTS.length]
        : null,
);

const SUITS: Record<string, { glyph: string; name: string }> = {
    H: { glyph: '♥', name: 'hearts' },
    D: { glyph: '♦', name: 'diamonds' },
    C: { glyph: '♣', name: 'clubs' },
    S: { glyph: '♠', name: 'spades' },
};

const rank = computed(() => props.card?.slice(0, -1) ?? '');
const suit = computed(() => SUITS[props.card?.slice(-1) ?? ''] ?? null);
const isRed = computed(() => {
    const code = props.card?.slice(-1);

    return code === 'H' || code === 'D';
});

const sizes = {
    xs: { box: 'h-11 w-8 rounded', index: 'text-[0.55rem]', pip: 'text-base' },
    sm: { box: 'h-16 w-11 rounded-md', index: 'text-[0.7rem]', pip: 'text-xl' },
    md: {
        box: 'h-24 w-[4.25rem] rounded-xl sm:h-28 sm:w-20 landscape-phone:h-[5.25rem] landscape-phone:w-[3.75rem]',
        index: 'text-sm landscape-phone:text-xs',
        pip: 'text-3xl sm:text-4xl landscape-phone:text-2xl',
    },
} as const;

const label = computed(() =>
    props.faceDown || !props.card
        ? 'Face down card'
        : `${rank.value} of ${suit.value?.name ?? 'unknown'}`,
);
</script>

<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        :aria-label="label"
        :aria-pressed="interactive ? selected : undefined"
        :class="
            cn(
                'relative block shrink-0 overflow-hidden border shadow-sm transition-transform duration-200 ease-out select-none',
                sizes[size].box,
                faceDown
                    ? 'border-teal-900/40 bg-teal-700 bg-[repeating-linear-gradient(135deg,transparent,transparent_5px,rgba(255,255,255,0.14)_5px,rgba(255,255,255,0.14)_10px)]'
                    : 'border-slate-200 bg-white',
                interactive &&
                    'cursor-grab focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
                interactive && !lifted && 'hover:-translate-y-2',
                meld && `ring-2 ${meld.ring}`,
                selected &&
                    '-translate-y-3 border-teal-500 shadow-lg ring-2 ring-teal-500/80',
                spotlit &&
                    '-translate-y-4 scale-110 border-teal-400 shadow-xl ring-4 ring-teal-400/70',
                lifted &&
                    'scale-110 cursor-grabbing border-teal-500 shadow-2xl ring-2 ring-teal-500/70',
            )
        "
    >
        <span
            v-if="meld"
            :class="cn('absolute inset-x-0 bottom-0 h-1', meld.edge)"
            aria-hidden="true"
        />

        <template v-if="!faceDown && card">
            <span
                :class="
                    cn(
                        'absolute top-1 left-1.5 text-left leading-none font-semibold tabular-nums',
                        sizes[size].index,
                        isRed ? 'text-rose-600' : 'text-slate-900',
                    )
                "
            >
                {{ rank }}
                <span class="block text-[0.9em]">{{ suit?.glyph }}</span>
            </span>

            <span
                :class="
                    cn(
                        'absolute inset-0 flex items-center justify-center leading-none',
                        sizes[size].pip,
                        isRed ? 'text-rose-500/85' : 'text-slate-800/85',
                    )
                "
                aria-hidden="true"
            >
                {{ suit?.glyph }}
            </span>

            <span
                v-if="size === 'md'"
                :class="
                    cn(
                        'absolute right-1.5 bottom-1.5 rotate-180 text-left leading-none font-semibold tabular-nums',
                        sizes[size].index,
                        isRed ? 'text-rose-600' : 'text-slate-900',
                    )
                "
                aria-hidden="true"
            >
                {{ rank }}
                <span class="block text-[0.9em]">{{ suit?.glyph }}</span>
            </span>
        </template>
    </component>
</template>
