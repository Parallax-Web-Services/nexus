<template>
    <div>
        <img class="w-10 h-10" :src="src" alt="" />
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

const src = computed(() => {
    return `/assets/${folder()}/mark.svg`
})
</script>
