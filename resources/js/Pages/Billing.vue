<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    planGroups: Object,
    portalAccounts: Array,
});
</script>

<template>
    <AppLayout title="Billing" dark>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
                        Billing
                    </h2>
                </div>

                <div v-if="portalAccounts.length" class="flex gap-2">
                    <a
                        v-for="account in portalAccounts"
                        :key="account.key"
                        :href="`/billing/portal/${account.key}`"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-white/5 dark:border-zinc-700 dark:text-slate-200 dark:shadow-none dark:hover:bg-zinc-700 dark:focus:ring-[var(--shell-accent)] dark:focus:ring-offset-zinc-900 transition"
                    >
                        Manage {{ account.name }} Billing
                    </a>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">
                <p v-if="!Object.keys(planGroups).length" class="text-gray-500 dark:text-slate-400 px-4 sm:px-0">
                    No plans are available yet.
                </p>

                <section v-for="(plans, accountName) in planGroups" :key="accountName">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-slate-200 px-4 sm:px-0">
                        {{ accountName }}
                    </h3>

                    <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="plan in plans"
                            :key="plan.id"
                            class="bg-white shadow sm:rounded-lg p-6 flex flex-col dark:bg-[var(--shell-panel)] dark:shadow-none dark:ring-1 dark:ring-zinc-800"
                        >
                            <div class="flex items-start justify-between">
                                <h4 class="font-semibold text-gray-900 dark:text-slate-100">{{ plan.name }}</h4>

                                <span
                                    v-if="plan.subscribed"
                                    class="ml-2 shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-emerald-300/10 dark:text-emerald-300"
                                >
                                    Subscribed
                                </span>
                            </div>

                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-[var(--shell-soft)]">
                                {{ plan.pricing || '—' }}
                            </p>

                            <div v-if="plan.services.length" class="mt-3 flex flex-wrap gap-1">
                                <span
                                    v-for="service in plan.services"
                                    :key="service"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-white/5 dark:text-[var(--shell-soft)]"
                                >
                                    {{ service }}
                                </span>
                            </div>

                            <p v-if="plan.subscribed && plan.expires_at" class="mt-3 text-sm text-amber-600 dark:text-amber-300">
                                Ends {{ plan.expires_at }}
                            </p>

                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800 mt-auto">
                                <a
                                    v-if="!plan.subscribed"
                                    :href="`/billing/checkout/${plan.id}`"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-[var(--shell-accent)] dark:text-slate-950 dark:hover:bg-[var(--shell-soft)] dark:focus:ring-[var(--shell-accent)] dark:focus:ring-offset-zinc-900 transition"
                                >
                                    Subscribe
                                </a>

                                <a
                                    v-else
                                    :href="`/billing/portal/${plan.account}`"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-white/5 dark:border-zinc-700 dark:text-slate-200 dark:shadow-none dark:hover:bg-zinc-700 dark:focus:ring-[var(--shell-accent)] dark:focus:ring-offset-zinc-900 transition"
                                >
                                    Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
