<script setup lang="ts">
import AppAlert from '@/components/ui/AppAlert.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppShell from '@/components/ui/AppShell.vue';
import { home } from '@/routes';
import { store } from '@/routes/games/join';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    code: string;
    status: 'waiting' | 'playing' | 'completed';
    playerCount: number;
    openSeats: number;
}>();

const form = useForm({ nickname: '' });
const page = usePage();

/** Rejected joins arrive as a page level error rather than a field error. */
const gameError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.game ?? null;
});

const closedReason = computed(() => {
    if (props.status === 'playing') {
        return 'This game has already started.';
    }

    if (props.status === 'completed') {
        return 'This game has already finished.';
    }

    return props.openSeats === 0 ? 'This game is already full.' : null;
});

function submit(): void {
    form.post(store.url(props.code), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Join game ${code}`" />

    <AppShell subtitle="You have been invited">
        <div class="flex flex-1 items-center justify-center">
            <section
                class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_16px_40px_-24px_rgba(16,24,40,0.25)] sm:p-8"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-slate-500 uppercase"
                >
                    Game code
                </p>
                <p
                    class="mt-2 font-mono text-3xl font-semibold tracking-[0.18em] text-slate-900 tabular-nums"
                >
                    {{ code }}
                </p>

                <template v-if="closedReason">
                    <AppAlert class="mt-6">{{ closedReason }}</AppAlert>

                    <Link :href="home.url()" class="mt-4 block">
                        <AppButton block variant="secondary">
                            Start your own game
                        </AppButton>
                    </Link>
                </template>

                <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
                    <p class="text-sm text-slate-500">
                        {{ playerCount }} already seated · {{ openSeats }}
                        {{ openSeats === 1 ? 'seat' : 'seats' }} left
                    </p>

                    <div>
                        <label
                            for="nickname"
                            class="block text-xs font-medium tracking-[0.15em] text-slate-500 uppercase"
                        >
                            Your nickname
                        </label>
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            name="nickname"
                            autocomplete="nickname"
                            maxlength="20"
                            required
                            autofocus
                            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 transition-colors placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none"
                        />
                    </div>

                    <AppAlert v-if="form.errors.nickname">
                        {{ form.errors.nickname }}
                    </AppAlert>
                    <AppAlert v-else-if="gameError">
                        {{ gameError }}
                    </AppAlert>

                    <AppButton
                        type="submit"
                        block
                        :disabled="
                            form.processing || form.nickname.trim().length < 2
                        "
                    >
                        {{ form.processing ? 'Joining…' : 'Join game' }}
                    </AppButton>
                </form>
            </section>
        </div>
    </AppShell>
</template>
