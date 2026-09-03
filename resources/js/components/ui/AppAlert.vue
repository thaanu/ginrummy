<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(defineProps<{ tone?: 'error' | 'info' }>(), { tone: 'error' });
</script>

<template>
    <Transition name="alert">
        <p
            role="alert"
            :class="
                cn(
                    'rounded-xl px-4 py-3 text-sm',
                    tone === 'error'
                        ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200'
                        : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                )
            "
        >
            <slot />
        </p>
    </Transition>
</template>

<style scoped>
.alert-enter-active,
.alert-leave-active {
    transition: all 0.2s ease;
}

.alert-enter-from,
.alert-leave-to {
    opacity: 0;
    transform: translateY(-0.25rem);
}
</style>
