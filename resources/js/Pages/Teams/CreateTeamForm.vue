<template>
    <jet-form-section @submitted="createTeam">
        <template #title>
            Team Details
        </template>

        <template #description>
            Create a new team to collaborate with others on projects.
        </template>

        <template #form>
            <div class="col-span-6">
                <jet-label value="Team Owner" />

                <div class="flex items-center mt-2">
                    <img class="w-12 h-12 rounded-full object-cover" :src="$page.user.profile_photo_url" :alt="$page.user.name">

                    <div class="ml-4 leading-tight">
                        <div>{{ $page.user.name }}</div>
                        <div class="text-gray-700 dark:text-slate-300 text-sm">{{ $page.user.email }}</div>
                    </div>
                </div>
            </div>

            <div class="col-span-6 sm:col-span-4">
                <jet-label for="name" value="Team Name" />
                <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name" autofocus />
                <jet-input-error :message="form.error('name')" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <jet-action-message :on="form.recentlySuccessful" class="mr-3">
                Saved.
            </jet-action-message>

            <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </jet-button>
        </template>
    </jet-form-section>
</template>

<script>
    import JetActionMessage from '@/Components/ActionMessage'
    import JetButton from '@/Components/Button'
    import JetFormSection from '@/Components/FormSection'
    import JetInput from '@/Components/Input'
    import JetInputError from '@/Components/InputError'
    import JetLabel from '@/Components/Label'

    export default {
        components: {
            JetActionMessage,
            JetButton,
            JetFormSection,
            JetInput,
            JetInputError,
            JetLabel,
        },

        data() {
            return {
                form: this.$inertia.form({
                    name: '',
                }, {
                    bag: 'createTeam',
                    resetOnSuccess: false,
                })
            }
        },

        methods: {
            createTeam() {
                this.form.post(route('teams.store'), {
                    preserveScroll: true
                });
            },
        },
    }
</script>
