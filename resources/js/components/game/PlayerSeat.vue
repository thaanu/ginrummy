<script setup lang="ts">
import PlayingCard from '@/components/game/PlayingCard.vue';
import { cn } from '@/lib/utils';
import type { GamePlayer } from '@/types/game';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        player: GamePlayer;
        isCurrent?: boolean;
        isWinner?: boolean;
        showCards?: boolean;
    }>(),
    { isCurrent: false, isWinner: false, showCards: true },
);

const initials = computed(() =>
    props.player.nickname
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const fannedCards = computed(() => Math.min(props.player.cardCount, 6));
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center gap-2 rounded-2xl border px-4 py-3 transition-all duration-300',
                isCurrent
                    ? 'border-teal-500/60 bg-teal-50/80 shadow-[0_0_0_4px_rgba(20,184,166,0.10)]'
                    : 'border-slate-200 bg-white/70',
                isWinner && 'border-amber-400 bg-amber-50',
            )
        "
    >
        <div v-if="showCards" class="flex h-11 items-end" aria-hidden="true">
            <PlayingCard
                v-for="index in fannedCards"
                :key="index"
                face-down
                size="xs"
                class="-mr-3 shadow-none ring-1 ring-white/50 last:mr-0"
            />
        </div>

        <div class="flex items-center gap-2">
            <span
                :class="
                    cn(
                        'flex size-7 items-center justify-center rounded-full text-[0.7rem] font-semibold',
                        isCurrent
                            ? 'bg-teal-600 text-white'
                            : 'bg-slate-200 text-slate-600',
                    )
                "
            >
                {{ initials }}
            </span>

            <div class="text-left leading-tight">
                <p
                    class="flex items-center gap-1.5 text-sm font-semibold text-slate-900"
                >
                    {{ player.nickname }}
                    <span
                        v-if="player.isHost"
                        class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[0.6rem] font-medium tracking-wide text-slate-500 uppercase"
                    >
                        Host
                    </span>
                </p>
                <p class="text-xs text-slate-500">
                    <span v-if="showCards">{{ player.cardCount }} cards</span>
                    <span v-if="showCards && !player.connected"> · </span>
                    <span v-if="!player.connected" class="text-amber-600">
                        Disconnected
                    </span>
                </p>
            </div>
        </div>

        <p
            v-if="isCurrent"
            class="text-[0.65rem] font-semibold tracking-widest text-teal-700 uppercase"
        >
            Their turn
        </p>
    </div>
</template>
