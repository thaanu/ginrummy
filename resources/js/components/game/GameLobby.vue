<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue';
import type { GamePlayer } from '@/types/game';
import { computed, ref } from 'vue';

const props = defineProps<{
    code: string;
    invitationUrl: string;
    players: GamePlayer[];
    isHost: boolean;
    canStart: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{ start: [] }>();

const MAX_PLAYERS = 4;

const emptySeats = computed(() =>
    Math.max(0, MAX_PLAYERS - props.players.length),
);

const copied = ref<'code' | 'link' | null>(null);

async function copy(what: 'code' | 'link'): Promise<void> {
    const value = what === 'code' ? props.code : props.invitationUrl;

    try {
        await navigator.clipboard.writeText(value);
        copied.value = what;
        setTimeout(() => (copied.value = null), 1800);
    } catch {
        // Clipboard access can be refused; the value stays selectable on screen.
    }
}
</script>

<template>
    <div class="grid gap-6 md:grid-cols-[1.1fr_1fr]">
        <section
            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_12px_32px_-16px_rgba(16,24,40,0.18)] sm:p-8"
        >
            <p
                class="text-xs font-medium tracking-[0.2em] text-slate-500 uppercase"
            >
                Game code
            </p>

            <p
                class="mt-3 font-mono text-4xl font-semibold tracking-[0.18em] text-slate-900 tabular-nums sm:text-5xl"
            >
                {{ code }}
            </p>

            <p
                class="mt-6 text-xs font-medium tracking-[0.2em] text-slate-500 uppercase"
            >
                Invitation link
            </p>

            <p
                class="mt-2 truncate rounded-xl bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-600 ring-1 ring-slate-200"
            >
                {{ invitationUrl }}
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <AppButton variant="secondary" @click="copy('link')">
                    {{ copied === 'link' ? 'Link copied' : 'Copy link' }}
                </AppButton>
                <AppButton variant="secondary" @click="copy('code')">
                    {{ copied === 'code' ? 'Code copied' : 'Copy code' }}
                </AppButton>
            </div>
        </section>

        <section
            class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_12px_32px_-16px_rgba(16,24,40,0.18)] sm:p-8"
        >
            <h2 class="text-sm font-semibold text-slate-900">
                Waiting for others to join…
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Two to four players. The list updates as people arrive.
            </p>

            <ol class="mt-5 flex-1 space-y-2">
                <TransitionGroup name="seat">
                    <li
                        v-for="(player, index) in players"
                        :key="player.id"
                        class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-200"
                    >
                        <span
                            class="w-4 text-xs font-semibold text-slate-400 tabular-nums"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="flex-1 text-sm font-medium text-slate-900">
                            {{ player.nickname }}
                        </span>
                        <span
                            v-if="player.isHost"
                            class="rounded-full bg-teal-100 px-2 py-0.5 text-[0.6rem] font-semibold tracking-wide text-teal-800 uppercase"
                        >
                            Host
                        </span>
                    </li>
                    <li
                        v-for="seat in emptySeats"
                        :key="`empty-${seat}`"
                        class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 px-3 py-2.5"
                    >
                        <span
                            class="w-4 text-xs font-semibold text-slate-300 tabular-nums"
                        >
                            {{ players.length + seat }}
                        </span>
                        <span class="text-sm text-slate-400">Empty</span>
                    </li>
                </TransitionGroup>
            </ol>

            <div v-if="isHost" class="mt-6">
                <AppButton
                    block
                    :disabled="!canStart || busy"
                    @click="emit('start')"
                >
                    {{ busy ? 'Dealing…' : 'Start game' }}
                </AppButton>
                <p
                    v-if="!canStart"
                    class="mt-2 text-center text-xs text-slate-500"
                >
                    At least one more player is needed.
                </p>
            </div>
            <p v-else class="mt-6 text-center text-xs text-slate-500">
                The host will start the game.
            </p>
        </section>
    </div>
</template>

<style scoped>
.seat-enter-active,
.seat-leave-active {
    transition: all 0.25s ease;
}

.seat-enter-from,
.seat-leave-to {
    opacity: 0;
    transform: translateX(-0.5rem);
}
</style>
