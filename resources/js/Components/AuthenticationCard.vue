<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
    <div :data-workspace="page.props.shell.workspace" class="nexus-shell nexus-auth dark min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 ">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-6 border border-white/10 bg-white/[0.03] overflow-hidden rounded-2xl">
            <slot />
        </div>
    </div>
</template>
