<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ApplicationLogo from './ApplicationLogo'

const props = defineProps({
    service: String
})

// The service's public site (e.g. slipstream.codes), not the login loop.
const page = usePage()
const sharedShell = computed(() => !props.service || ['nexus', 'continuum', 'slipstream'].includes(props.service))
const href = computed(() => sharedShell.value ? page.props.shell.home : page.props.activeService?.url || '/')
</script>

<template>
    <a :href="href">
        <div v-if="sharedShell" class="shell-auth-brand"><img :src="page.props.shell.mark" alt="" /><span>{{ page.props.shell.name }}<small>Slipstream × Continuum account</small></span></div>
        <ApplicationLogo v-else :service="service" />
    </a>
</template>
