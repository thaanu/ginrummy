<script setup lang="ts">
withDefaults(defineProps<{ subtitle?: string | null }>(), { subtitle: null });
</script>

<template>
    <div class="min-h-dvh bg-[#F7F6F3] text-slate-900">
        <div
            class="pointer-events-none fixed inset-x-0 top-0 h-64 bg-[radial-gradient(60%_100%_at_50%_0%,rgba(15,118,110,0.10),transparent)]"
            aria-hidden="true"
        />

        <main
            class="relative mx-auto flex min-h-dvh w-full max-w-5xl flex-col gap-5 px-4 py-5 sm:gap-8 sm:px-6 sm:py-12"
        >
            <header class="text-center">
                <h1
                    class="text-xl font-semibold tracking-[0.3em] text-slate-900 uppercase sm:text-3xl sm:tracking-[0.35em]"
                >
                    Gin Rummy
                </h1>
                <p
                    v-if="subtitle"
                    class="mt-1.5 text-[0.65rem] font-medium tracking-[0.2em] text-teal-700 uppercase sm:mt-2 sm:text-xs"
                >
                    {{ subtitle }}
                </p>
            </header>

            <slot />
        </main>
    </div>
</template>
