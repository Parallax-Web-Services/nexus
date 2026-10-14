<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="border-gray-300 dark:border-white/10 dark:bg-white/5 dark:text-slate-100 dark:placeholder-slate-500 focus:border-indigo-500 dark:focus:border-[var(--shell-accent)] focus:ring-indigo-500 dark:focus:ring-[var(--shell-accent)] rounded-md shadow-sm dark:shadow-none"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
