<script setup lang="ts">
import PlayingCard from '@/components/game/PlayingCard.vue';

defineProps<{
    count: number;
    enabled?: boolean;
}>();

const emit = defineEmits<{
    draw: [];
    'pointer-down': [event: PointerEvent];
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <!-- Two static cards sit behind the top one to suggest depth. -->
        <div class="relative h-24 w-[4.25rem] sm:h-28 sm:w-20">
            <div
                v-if="count > 2"
                class="absolute inset-0 translate-x-1.5 translate-y-1.5 opacity-60"
                aria-hidden="true"
            >
                <PlayingCard face-down />
            </div>
            <div
                v-if="count > 1"
                class="absolute inset-0 translate-x-[3px] translate-y-[3px] opacity-80"
                aria-hidden="true"
            >
                <PlayingCard face-down />
            </div>

            <div class="absolute inset-0">
                <PlayingCard
                    v-if="count > 0"
                    face-down
                    :interactive="enabled"
                    aria-label="Draw from the stock"
                    @click="enabled && emit('draw')"
                    @pointerdown="emit('pointer-down', $event)"
                />
                <div
                    v-else
                    class="h-24 w-[4.25rem] rounded-xl border-2 border-dashed border-slate-300 sm:h-28 sm:w-20"
                />
            </div>
        </div>

        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
            Stock · {{ count }}
        </p>
    </div>
</template>
