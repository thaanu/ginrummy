<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue';
import AppShell from '@/components/ui/AppShell.vue';
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ status: number }>();

const MESSAGES: Record<number, { title: string; body: string }> = {
    403: {
        title: 'Not your table',
        body: 'You are not seated at this game. Ask for a fresh invitation link.',
    },
    404: {
        title: 'Game not found',
        body: 'That game code does not exist, or the game has already been cleared away.',
    },
    419: {
        title: 'Session expired',
        body: 'You were away for a while. Start again from the home screen.',
    },
    429: {
        title: 'Slow down a moment',
        body: 'Too many requests arrived at once. Try again shortly.',
    },
    500: {
        title: 'Something went wrong',
        body: 'The table hit an unexpected problem. Please try again.',
    },
    503: {
        title: 'Back shortly',
        body: 'The game is briefly unavailable while we carry out maintenance.',
    },
};

const message = computed(
    () =>
        MESSAGES[props.status] ?? {
            title: 'Something went wrong',
            body: 'Please try again.',
        },
);
</script>

<template>
    <Head :title="message.title" />

    <AppShell>
        <div class="flex flex-1 items-center justify-center">
            <section class="max-w-md text-center">
                <p
                    class="font-mono text-5xl font-semibold text-slate-300 tabular-nums"
                >
                    {{ status }}
                </p>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">
                    {{ message.title }}
                </h2>
                <p class="mt-2 text-sm text-slate-500">{{ message.body }}</p>

                <Link :href="home.url()" class="mt-6 inline-block">
                    <AppButton>Back to the start</AppButton>
                </Link>
            </section>
        </div>
    </AppShell>
</template>
