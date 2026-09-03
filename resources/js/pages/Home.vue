<script setup lang="ts">
import AppAlert from '@/components/ui/AppAlert.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppShell from '@/components/ui/AppShell.vue';
import { store } from '@/routes/games';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ nickname: '' });

function submit(): void {
    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Play Gin Rummy" />

    <AppShell subtitle="Create a game to start">
        <div class="flex flex-1 items-center justify-center">
            <section
                class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_16px_40px_-24px_rgba(16,24,40,0.25)] sm:p-8"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    Pick a nickname
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    No account needed. You will get a code to share with up to
                    three friends.
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label
                            for="nickname"
                            class="block text-xs font-medium tracking-[0.15em] text-slate-500 uppercase"
                        >
                            Nickname
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
                            placeholder="e.g. Ahmed"
                            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 transition-colors placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none"
                        />
                    </div>

                    <AppAlert v-if="form.errors.nickname">
                        {{ form.errors.nickname }}
                    </AppAlert>

                    <AppButton
                        type="submit"
                        block
                        :disabled="
                            form.processing || form.nickname.trim().length < 2
                        "
                    >
                        {{ form.processing ? 'Creating…' : 'Create' }}
                    </AppButton>
                </form>
            </section>
        </div>
    </AppShell>
</template>
