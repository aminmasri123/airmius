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
    name: props.user.name || '',
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    birth_date: props.user.birth_date || '',
    bio: props.user.bio || '',
    profile_visibility: props.user.profile_visibility || 'public',
    suspension_action: '',
    suspension_reason: props.user.suspension_reason || '',
    roles: [...(props.user.roles || [])],
})

const suspensionOptions = [
    { value: '', label: 'Nicht ?ndern' },
    { value: 'lift', label: 'Sperre aufheben' },
    { value: '1', label: '1 Tag sperren' },
    { value: '3', label: '3 Tage sperren' },
    { value: '7', label: '7 Tage sperren' },
    { value: '10', label: '10 Tage sperren' },
    { value: '14', label: '14 Tage sperren' },
    { value: '30', label: '30 Tage sperren' },
    { value: '60', label: '60 Tage sperren' },
    { value: '90', label: '90 Tage sperren' },
]

const submit = () => {
    form.put(route('members.update', props.user.id))
}
</script>

<template>
    <AppLayout>
        <Head title="Nutzer bearbeiten" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">Nutzer bearbeiten</h1>
                <p class="mt-2 text-sm text-secondary">
                    Bearbeiten Sie Stammdaten, Profilstatus, Rollen und Kontosperren des Nutzers.
                </p>
            </div>

            <div class="surface-card max-w-3xl p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-primary">Vorname</label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            />
                            <div v-if="form.errors.first_name" class="mt-1 text-sm text-error">{{ form.errors.first_name }}</div>
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-primary">Nachname</label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            />
                            <div v-if="form.errors.last_name" class="mt-1 text-sm text-error">{{ form.errors.last_name }}</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="name" class="block text-sm font-medium text-primary">Anzeigename</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                required
                            />
                            <p class="mt-1 text-xs text-secondary">Wird beim Speichern aus Vor- und Nachname gesetzt, wenn diese ausgefüllt sind.</p>
                            <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-primary">E-Mail</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                required
                            />
                            <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="birth_date" class="block text-sm font-medium text-primary">Geburtsdatum</label>
                            <input
                                id="birth_date"
                                v-model="form.birth_date"
                                type="date"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            />
                            <div v-if="form.errors.birth_date" class="mt-1 text-sm text-error">{{ form.errors.birth_date }}</div>
                        </div>

                        <div>
                            <label for="profile_visibility" class="block text-sm font-medium text-primary">Profil-Sichtbarkeit</label>
                            <select
                                id="profile_visibility"
                                v-model="form.profile_visibility"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            >
                                <option value="public">Öffentlich</option>
                                <option value="private">Privat</option>
                            </select>
                            <div v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</div>
                        </div>
                    </div>

                    <div>
                        <label for="bio" class="block text-sm font-medium text-primary">Bio</label>
                        <textarea
                            id="bio"
                            v-model="form.bio"
                            rows="4"
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                        />
                        <div v-if="form.errors.bio" class="mt-1 text-sm text-error">{{ form.errors.bio }}</div>
                    </div>

                    <div class="space-y-3 border-t border-border pt-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary">Kontosperre</h2>
                            <p class="text-xs text-secondary">
                                Aktueller Status:
                                <span class="font-semibold text-primary">{{ user.account_status || 'active' }}</span>
                                <span v-if="user.suspended_until"> · gesperrt bis {{ user.suspended_until }}</span>
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="suspension_action" class="block text-sm font-medium text-primary">Aktion</label>
                                <select
                                    id="suspension_action"
                                    v-model="form.suspension_action"
                                    class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                >
                                    <option v-for="option in suspensionOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.suspension_action" class="mt-1 text-sm text-error">{{ form.errors.suspension_action }}</div>
                            </div>

                            <div>
                                <label for="suspension_reason" class="block text-sm font-medium text-primary">Grund optional</label>
                                <input
                                    id="suspension_reason"
                                    v-model="form.suspension_reason"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                    placeholder="z. B. Regelverstoss"
                                />
                                <div v-if="form.errors.suspension_reason" class="mt-1 text-sm text-error">{{ form.errors.suspension_reason }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="canManageRoles" class="space-y-3 border-t border-border pt-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary">Rollen</h2>
                            <p class="text-xs text-secondary">Nur Administratoren können Rollen ?ndern.</p>
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

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition-colors hover:bg-primary/80 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Speichert...' : 'Speichern' }}
                        </button>
                        <button
                            type="button"
                            @click="$inertia.visit(route('members.index'))"
                            class="rounded-lg border border-border bg-card px-4 py-2 text-primary transition-colors hover:border-borderHover"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
