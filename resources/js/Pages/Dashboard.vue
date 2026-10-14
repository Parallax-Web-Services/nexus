<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const page = usePage();
const user = computed(() => page.props.user);
const shell = computed(() => page.props.shell);
const team = computed(() => user.value?.current_team);
</script>

<template>
    <AppLayout title="Overview">
        <template #header><h1>Your account. Every workspace.</h1><p>Manage your profile, team, and billing across Slipstream and Continuum.</p></template>
        <div class="account-overview">
            <section class="account-identity" aria-label="Current account">
                <div><span class="shell-eyebrow">Signed in as</span><h2>{{ user.name }}</h2><p>{{ user.email }}</p></div>
                <span class="account-badge">{{ team?.name || 'Personal account' }}</span>
            </section>
            <section aria-labelledby="settings-heading"><h2 id="settings-heading" class="account-section-title">Account settings</h2>
                <div class="account-cards">
                    <Link :href="route('profile.show')" class="account-card"><span class="account-card-icon" aria-hidden="true">◎</span><h3>Profile & security</h3><p>Your name, email, password, two-factor authentication, and active sessions.</p><span class="account-card-action">Manage profile <span aria-hidden="true">→</span></span></Link>
                    <Link v-if="$page.props.jetstream.hasTeamFeatures && team" :href="route('teams.show', team.id)" class="account-card"><span class="account-card-icon" aria-hidden="true">⊞</span><h3>Team settings</h3><p>Manage {{ team.name }}, invite members, and set their roles.</p><span class="account-card-action">Manage team <span aria-hidden="true">→</span></span></Link>
                    <Link :href="route('billing.show')" class="account-card"><span class="account-card-icon" aria-hidden="true">▤</span><h3>Plans & billing</h3><p>View available plans and manage subscriptions, payment methods, and invoices.</p><span class="account-card-action">Open billing <span aria-hidden="true">→</span></span></Link>
                </div>
            </section>
            <section aria-labelledby="workspaces-heading"><div class="account-section-heading"><h2 id="workspaces-heading" class="account-section-title">Back to work</h2><span>Your shared account travels with you.</span></div>
                <div class="account-workspaces"><a v-for="workspace in shell.workspaces" :key="workspace.key" :href="workspace.home" class="account-workspace" :data-product="workspace.key"><img :src="workspace.mark" alt="" /><div><h3>{{ workspace.name }}<span>{{ workspace.role }}</span></h3><p>{{ workspace.description }}</p></div><span aria-hidden="true">→</span></a></div>
            </section>
        </div>
    </AppLayout>
</template>
