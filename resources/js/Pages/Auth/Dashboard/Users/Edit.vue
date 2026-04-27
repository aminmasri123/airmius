<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

const props = defineProps({
    user: Object,
    availableRoles: {
        type: Array,
        default: () => [],
    },
    availablePermissions: {
        type: Array,
        default: () => [],
    },
    canManageRoles: Boolean,
})

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    bio: props.user.bio || '',
    profile_visibility: props.user.profile_visibility || 'public',
    roles: [...(props.user.roles || [])],
})

const submit = () => {
    form.put(route('members.update', props.user.id), {
        onSuccess: () => {
            // Optional: Handle success
        },
    })
}
</script>

<template>
    <AppLayout>
        <Head title="Nutzer bearbeiten" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">Nutzer bearbeiten</h1>
                <p class="mt-2 text-sm text-secondary">
                    Bearbeiten Sie die Details des Nutzers.
                </p>
            </div>

            <div class="surface-card max-w-md p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-primary">Name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-primary">E-Mail</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                        <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label for="profile_visibility" class="block text-sm font-medium text-primary">Profil-Sichtbarkeit</label>
                        <select
                            id="profile_visibility"
                            v-model="form.profile_visibility"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                        >
                            <option value="public">Öffentlich</option>
                            <option value="private">Privat</option>
                        </select>
                        <div v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</div>
                    </div>

                    <div>
                        <label for="bio" class="block text-sm font-medium text-primary">Bio</label>
                        <textarea
                            id="bio"
                            v-model="form.bio"
                            rows="4"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                        />
                        <div v-if="form.errors.bio" class="mt-1 text-sm text-error">{{ form.errors.bio }}</div>
                    </div>

                    <div v-if="canManageRoles" class="space-y-3 border-t border-border pt-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary">Rollen</h2>
                            <p class="text-xs text-secondary">Nur Administratoren können Rollen ändern.</p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="role in availableRoles"
                                :key="role"
                                class="flex items-center gap-2 rounded border border-border px-3 py-2 text-sm text-primary"
                            >
                                <input v-model="form.roles" type="checkbox" :value="role" class="rounded border-border" />
                                <span>{{ role }}</span>
                            </label>
                        </div>
                        <div v-if="form.errors.roles" class="mt-1 text-sm text-error">{{ form.errors.roles }}</div>

                        <div>
                            <h2 class="text-sm font-semibold text-primary">Berechtigungen</h2>
                            <div class="mt-2 max-h-32 overflow-auto rounded border border-border bg-inputBg p-3 text-xs text-secondary">
                                <span v-if="user.permissions?.length">{{ user.permissions.join(', ') }}</span>
                                <span v-else>Keine direkten oder rollenbasierten Berechtigungen.</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded hover:bg-primary/80 transition-colors disabled:opacity-50"
                        >
                            Speichern
                        </button>
                        <button
                            type="button"
                            @click="$inertia.visit(route('members.index'))"
                            class="px-4 py-2 bg-card border border-border text-primary rounded-lg hover:border-borderHover transition-colors"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
