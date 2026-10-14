<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ selection: Object, executiveNames: Array, ready: Boolean, checkoutState: String, csrfToken: String });
const page = usePage();
const errors = computed(() => Object.values(page.props.errors || {}).flat());
</script>

<template>
    <AppLayout title="Council billing">
        <template #header><h1>Review your council</h1><p>One shared account. Access follows the executives in your plan.</p></template>
        <section class="council-review">
            <span class="shell-eyebrow">Your plan</span><h2>{{ selection.name }}</h2>
            <ul class="council-executives"><li v-for="name in executiveNames" :key="name">{{ name }}</li></ul>
            <p class="council-price">${{ (selection.amount / 100).toLocaleString('en-US', { maximumFractionDigits: 0 }) }}<small>/ {{ selection.interval === 'annual' ? 'year' : 'month' }}</small></p>
            <p>{{ selection.interval === 'annual' ? 'Billed annually. Two months free compared with monthly billing.' : 'Billed monthly.' }}</p>
            <p class="council-note">Your council subscription renews automatically. Manage or cancel in the billing portal. AI execution, usage and external services are additional.</p>
            <template v-if="checkoutState === 'pending'"><div class="council-status" role="status">Checkout returned. Your executive access activates once Stripe confirms your subscription.</div><Link :href="route('billing.show')" class="council-button">View account & billing →</Link></template>
            <template v-else>
                <div v-if="!ready" class="council-status" role="status">Council checkout is being connected. Your selection is saved in this link.</div>
                <div v-if="errors.length" class="council-status" role="alert"><p v-for="error in errors" :key="error">{{ error }}</p></div>
                <!-- A browser form follows the existing hosted-checkout redirect. -->
                <form method="post" :action="route('council.purchase')">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="plan" :value="selection.key" />
                    <input type="hidden" name="interval" :value="selection.interval" />
                    <template v-if="selection.key === 'custom'"><input v-for="executive in selection.executives" :key="executive" type="hidden" name="executives[]" :value="executive" /></template>
                    <button class="council-button" type="submit" :disabled="!ready">Continue to secure checkout →</button>
                </form>
            </template>
        </section>
    </AppLayout>
</template>
