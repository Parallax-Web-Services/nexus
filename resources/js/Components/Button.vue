<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 dark:bg-[var(--shell-accent)] dark:text-slate-950 dark:hover:bg-[var(--shell-soft)] dark:active:bg-[var(--shell-soft)] dark:focus:border-[var(--shell-accent)] dark:focus:ring-[var(--shell-accent)] disabled:opacity-25 transition">
        <slot />
    </button>
</template>
