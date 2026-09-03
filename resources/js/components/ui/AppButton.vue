<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost';
        type?: 'button' | 'submit';
        disabled?: boolean;
        block?: boolean;
    }>(),
    { variant: 'primary', type: 'button', disabled: false, block: false },
);

const variants = {
    primary:
        'bg-teal-700 text-white shadow-sm hover:bg-teal-800 active:bg-teal-900',
    secondary:
        'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50',
    ghost: 'text-slate-600 hover:bg-slate-100',
} as const;
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="
            cn(
                'inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold tracking-wide transition-all duration-150',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
                'disabled:cursor-not-allowed disabled:opacity-40',
                variants[variant],
                block && 'w-full',
            )
        "
    >
        <slot />
    </button>
</template>
