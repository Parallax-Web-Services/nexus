<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import JetAuthenticationCard from '@/Components/AuthenticationCard.vue';
import JetAuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import JetButton from '@/Components/Button.vue';
import JetValidationErrors from '@/Components/ValidationErrors.vue';

const props = defineProps({
    status: String,
    email: String,
    activeService: Object,
});

const form = useForm();

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Verify your email" />

    <JetAuthenticationCard>
        <template #logo>
            <JetAuthenticationCardLogo :service="activeService?.name || 'nexus'" />
        </template>

        <h1 class="mb-3 text-xl font-semibold text-gray-900 dark:text-slate-100">Verify your email</h1>

        <p class="mb-4 text-sm text-gray-600 dark:text-slate-400">
            Verify <span class="font-medium text-gray-900 dark:text-slate-200">{{ email }}</span>
            to open your Parallax workspaces. Send a verification link, then open it from your inbox.
        </p>

        <p v-if="verificationLinkSent" role="status" class="mb-4 text-sm text-green-700 dark:text-emerald-300">
            Verification link sent. Check your inbox.
        </p>

        <JetValidationErrors class="mb-4" />

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <JetButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ verificationLinkSent ? 'Send another link' : 'Send verification link' }}
                </JetButton>

                <div>
                    <Link
                        :href="route('profile.show')"
                        class="underline text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-cyan-200"
                    >
                        Edit email</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="underline text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-cyan-200 ml-2"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </form>
    </JetAuthenticationCard>
</template>
