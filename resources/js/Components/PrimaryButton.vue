<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-[var(--shell-accent)] border border-transparent rounded-md font-semibold text-xs text-white dark:text-slate-950 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-[var(--shell-soft)] focus:bg-gray-700 dark:focus:bg-[var(--shell-soft)] active:bg-gray-900 dark:active:bg-[var(--shell-soft)] focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-[var(--shell-accent)] focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
