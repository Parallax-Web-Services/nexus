<script setup>
import JetApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <a :href="$page.props.activeService.url" class="dark:text-slate-300 dark:hover:text-cyan-200">
        <JetApplicationLogo class="h-12 w-auto" :service="$page.props.activeService.name || 'nexus'" />
        <p class="pt-4">&laquo; Return to Dashboard</p>
    </a>
</template>
