<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:ring focus:ring-blue-200 active:text-gray-800 active:bg-gray-50 dark:bg-white/5 dark:border-white/10 dark:text-slate-200 dark:shadow-none dark:hover:text-slate-100 dark:hover:bg-zinc-700 dark:focus:border-[var(--shell-accent)] dark:focus:ring-[var(--shell-accent)] dark:active:bg-zinc-700 disabled:opacity-25 transition">
        <slot />
    </button>
</template>
