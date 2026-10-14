<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import JetBanner from '@/Components/Banner.vue';

defineProps({ title: String, dark: { type: Boolean, default: true } });
const page = usePage();
const shell = computed(() => page.props.shell);
const user = computed(() => page.props.user);
const navigationOpen = ref(false);
const menuToggle = ref(null);
const teamTrigger = ref(null);
const rail = ref(null);
const teamPanel = ref(null);
const teamOpen = ref(false);
const teamSearch = ref('');
const switching = ref(false);
const teamError = ref('');
const currentTeam = computed(() => user.value?.current_team);
const initials = name => (name || '').trim().split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase();
const teams = computed(() => Object.values(user.value?.all_teams || {})
    .sort((a, b) => Number(b.id === currentTeam.value?.id) - Number(a.id === currentTeam.value?.id) || a.name.localeCompare(b.name))
    .filter(team => team.name.toLocaleLowerCase().includes(teamSearch.value.trim().toLocaleLowerCase())));
const accountLinks = computed(() => [
    { label: 'Overview', href: route('dashboard'), active: route().current('dashboard'), icon: '◈' },
    { label: 'Profile & security', href: route('profile.show'), active: route().current('profile.show'), icon: '◎' },
    ...(page.props.jetstream.hasTeamFeatures && currentTeam.value ? [{ label: 'Team settings', href: route('teams.show', currentTeam.value.id), active: route().current('teams.*'), icon: '⊞' }] : []),
    { label: 'Billing', href: route('billing.show'), active: route().current('billing.*') || route().current('council.*'), icon: '▤' },
    ...(page.props.jetstream.hasApiFeatures ? [{ label: 'API tokens', href: route('api-tokens.index'), active: route().current('api-tokens.*'), icon: '⌘' }] : []),
]);
const selectWorkspace = key => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('workspace', key);
    router.get(url.pathname + url.search, {}, { preserveScroll: true });
};
const switchToTeam = team => {
    if (switching.value || team.id === currentTeam.value?.id) return;
    teamError.value = '';
    router.put(route('current-team.update'), { team_id: team.id }, {
        preserveState: false,
        onStart: () => { switching.value = true; },
        onSuccess: () => { teamOpen.value = false; },
        onError: () => { teamError.value = 'Unable to switch teams. Please try again.'; },
        onFinish: () => { switching.value = false; },
    });
};
const closeMenus = () => {
    if (teamOpen.value) { teamOpen.value = false; teamTrigger.value?.focus(); }
    else if (navigationOpen.value) { navigationOpen.value = false; menuToggle.value?.focus(); }
};
const trapFocus = (event, element) => {
    const items = [...element.querySelectorAll('a[href], button:not(:disabled), input')].filter(item => item.getClientRects().length);
    const first = items[0], last = items.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
};
watch(teamOpen, async open => { if (open) { await nextTick(); teamPanel.value?.querySelector('input')?.focus(); } });
watch(navigationOpen, async open => { if (open) { await nextTick(); rail.value?.querySelector('a')?.focus(); } });
const applyDocumentTheme = () => {
    document.documentElement.classList.add('dark');
    document.documentElement.dataset.accountWorkspace = shell.value.workspace;
};
onMounted(applyDocumentTheme);
watch(() => shell.value.workspace, applyDocumentTheme);
onUnmounted(() => { document.documentElement.classList.remove('dark'); delete document.documentElement.dataset.accountWorkspace; });
</script>

<template>
    <div class="nexus-shell" :class="{ dark }" :data-workspace="shell.workspace" @keydown.esc="closeMenus">
        <Head :title="title" />
        <JetBanner />
        <a class="shell-skip-link" href="#main-content">Skip to content</a>
        <button v-if="navigationOpen" type="button" class="shell-mobile-overlay" aria-label="Close navigation" @click="navigationOpen = false" />
        <aside id="account-navigation" ref="rail" class="shell-rail" @keydown.tab="navigationOpen && trapFocus($event, rail)" :class="{ 'is-open': navigationOpen }" aria-label="Workspace and account navigation">
            <a :href="shell.home" class="shell-brand"><img :src="shell.mark" alt="" /><span>{{ shell.name }}<small>{{ shell.role }} workspace</small></span></a>
            <div v-if="$page.props.jetstream.hasTeamFeatures" class="shell-team">
                <button type="button" class="shell-team-trigger" ref="teamTrigger" :aria-expanded="teamOpen" aria-controls="team-options" @click="teamOpen = !teamOpen; teamSearch = ''">
                    <span class="shell-avatar">{{ initials(currentTeam?.name) || 'T' }}</span>
                    <span class="shell-team-name">{{ currentTeam?.name || 'Choose a team' }}<small>Current team</small></span><span aria-hidden="true">⌄</span>
                </button>
                <template v-if="teamOpen">
                    <button type="button" class="shell-team-overlay" aria-label="Close team switcher" @click="teamOpen = false" />
                    <div id="team-options" ref="teamPanel" class="shell-team-panel" @keydown.tab.stop="trapFocus($event, teamPanel)">
                        <div class="shell-team-search"><label for="team-search">Switch team</label><input id="team-search" v-model="teamSearch" type="search" placeholder="Find a team…" autocomplete="off" /></div>
                        <div class="shell-team-options">
                            <button v-for="team in teams" :key="team.id" type="button" :aria-current="team.id === currentTeam?.id ? 'true' : undefined" :disabled="switching" @click="switchToTeam(team)">
                                <span class="shell-avatar">{{ initials(team.name) }}</span><span>{{ team.name }}</span><span v-if="team.id === currentTeam?.id" class="shell-check" aria-hidden="true">✓</span>
                            </button>
                            <p v-if="!teams.length" class="shell-empty" role="status">No teams match your search.</p>
                        </div>
                        <p v-if="teamError" class="shell-empty" role="alert">{{ teamError }}</p>
                        <div class="shell-team-actions">
                            <Link v-if="currentTeam" :href="route('teams.show', currentTeam.id)" @click="closeMenus">Team settings <span aria-hidden="true">→</span></Link>
                            <Link v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" @click="closeMenus">Create a team <span aria-hidden="true">＋</span></Link>
                        </div>
                    </div>
                </template>
            </div>
            <div class="shell-section-label">Workspace</div>
            <div class="shell-products" aria-label="Account workspace theme">
                <button v-for="workspace in shell.workspaces" :key="workspace.key" type="button" :aria-pressed="workspace.key === shell.workspace" @click="selectWorkspace(workspace.key)"><img :src="workspace.mark" alt="" /><span>{{ workspace.name }}</span></button>
            </div>
            <a :href="shell.home" class="shell-return"><span aria-hidden="true">←</span> Return to {{ shell.name }}</a>
            <div class="shell-section-label">Account</div>
            <nav class="shell-account-links">
                <Link v-for="item in accountLinks" :key="item.label" :href="item.href" :aria-current="item.active ? 'page' : undefined" @click="closeMenus"><span class="shell-nav-icon" aria-hidden="true">{{ item.icon }}</span>{{ item.label }}</Link>
            </nav>
            <div class="shell-rail-footer">
                <Link :href="route('profile.show')" class="shell-user" @click="closeMenus"><span class="shell-avatar">{{ initials(user?.name) }}</span><span>{{ user?.name }}<small>{{ user?.email }}</small></span></Link>
                <button type="button" class="shell-signout" @click="router.post(route('logout'))">Sign out <span aria-hidden="true">↪</span></button>
                <div class="shell-powered">Slipstream × Continuum <span>Nexus account</span></div>
            </div>
        </aside>
        <div class="shell-body">
            <header class="shell-topbar">
                <button type="button" class="shell-menu-toggle" ref="menuToggle" :aria-expanded="navigationOpen" aria-controls="account-navigation" aria-label="Toggle account navigation" @click="navigationOpen = !navigationOpen">☰</button>
                <nav class="shell-breadcrumb" aria-label="Breadcrumb"><Link :href="route('dashboard')">Account</Link><span aria-hidden="true">/</span><span>{{ title }}</span></nav>
                <span class="shell-context">{{ shell.name }} <span aria-hidden="true">·</span> {{ currentTeam?.name || 'Your account' }}</span>
            </header>
            <main id="main-content" tabindex="-1" class="shell-content">
                <header v-if="$slots.header" class="shell-page-header"><span class="shell-eyebrow">{{ shell.name }} / Account</span><slot name="header" /></header>
                <slot />
            </main>
        </div>
    </div>
</template>
