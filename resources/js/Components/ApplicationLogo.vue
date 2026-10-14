<template>
    <div class="flex items-center">
        <img class="w-12 h-12 mr-4" :src="markSrc" alt="" />
        <!-- Wordmarks are black; inverting yields the white variant slipstream.codes serves on dark. -->
        <img :class="'w-auto dark:invert ' + (props.service === 'nexus' ? 'h-10' : 'h-6 mt-1')" :src="textSrc" alt="" />
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    service: String
})

const folder = () => {
    if (props.service === 'nexus') {
        return 'parallax'
    }

    return props.service
}

const markSrc = computed(() => {
    return `/assets/${folder()}/mark.svg`
})

const textSrc = computed(() => {
    return `/assets/${folder()}/text.svg`
})
</script>
