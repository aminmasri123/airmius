<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (value, params = {}) => t(value, params)

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
    { value: '', label: tx('users_edit.suspension.no_change') },
    { value: 'lift', label: tx('users_edit.suspension.lift') },
    { value: '1', label: tx('users_edit.suspension.one_day') },
    { value: '3', label: tx('users_edit.suspension.three_days') },
    { value: '7', label: tx('users_edit.suspension.seven_days') },
    { value: '10', label: tx('users_edit.suspension.ten_days') },
    { value: '14', label: tx('users_edit.suspension.fourteen_days') },
    { value: '30', label: tx('users_edit.suspension.thirty_days') },
    { value: '60', label: tx('users_edit.suspension.sixty_days') },
    { value: '90', label: tx('users_edit.suspension.ninety_days') },
]

const submit = () => {
    form.put(route('members.update', props.user.id))
}
</script>

<template>
    <AppLayout>
        <Head :title="tx('Nutzer bearbeiten')" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">{{ tx('Nutzer bearbeiten') }}</h1>
                <p class="mt-2 text-sm text-secondary">{{ tx('Bearbeiten Sie Stammdaten, Profilstatus, Rollen und Kontosperren des Nutzers.') }}</p>
            </div>

            <div class="surface-card max-w-3xl p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-primary">{{ tx('Vorname') }}</label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            />
                            <div v-if="form.errors.first_name" class="mt-1 text-sm text-error">{{ form.errors.first_name }}</div>
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-primary">{{ tx('Nachname') }}</label>
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
                            <label for="name" class="block text-sm font-medium text-primary">{{ tx('Anzeigename') }}</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                required
                            />
                            <p class="mt-1 text-xs text-secondary">{{ tx('Wird beim Speichern aus Vor- und Nachname gesetzt, wenn diese ausgefüllt sind.') }}</p>
                            <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-primary">{{ tx('E-Mail') }}</label>
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
                            <label for="birth_date" class="block text-sm font-medium text-primary">{{ tx('Geburtsdatum') }}</label>
                            <input
                                id="birth_date"
                                v-model="form.birth_date"
                                type="date"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            />
                            <div v-if="form.errors.birth_date" class="mt-1 text-sm text-error">{{ form.errors.birth_date }}</div>
                        </div>

                        <div>
                            <label for="profile_visibility" class="block text-sm font-medium text-primary">{{ tx('Profil-Sichtbarkeit') }}</label>
                            <select
                                id="profile_visibility"
                                v-model="form.profile_visibility"
                                class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                            >
                                <option value="public">{{ tx('Öffentlich') }}</option>
                                <option value="private">{{ tx('Privat') }}</option>
                            </select>
                            <div v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</div>
                        </div>
                    </div>

                    <div>
                        <label for="bio" class="block text-sm font-medium text-primary">{{ tx('Bio') }}</label>
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
                            <h2 class="text-sm font-semibold text-primary">{{ tx('Kontosperre') }}</h2>
                            <p class="text-xs text-secondary">
                                {{ tx('Aktueller Status:') }}
                                <span class="font-semibold text-primary">{{ user.account_status || 'active' }}</span>
                                <span v-if="user.suspended_until"> · {{ tx('gesperrt bis') }} {{ user.suspended_until }}</span>
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="suspension_action" class="block text-sm font-medium text-primary">{{ tx('Aktion') }}</label>
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
                                <label for="suspension_reason" class="block text-sm font-medium text-primary">{{ tx('Grund optional') }}</label>
                                <input
                                    id="suspension_reason"
                                    v-model="form.suspension_reason"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary focus:border-borderHover focus:outline-none focus:ring-borderHover"
                                    :placeholder="tx('z. B. Regelverstoss')"
                                />
                                <div v-if="form.errors.suspension_reason" class="mt-1 text-sm text-error">{{ form.errors.suspension_reason }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="canManageRoles" class="space-y-3 border-t border-border pt-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary">{{ tx('Rollen') }}</h2>
                            <p class="text-xs text-secondary">{{ tx('Nur Administratoren können Rollen ändern.') }}</p>
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
                            <h2 class="text-sm font-semibold text-primary">{{ tx('Berechtigungen') }}</h2>
                            <div class="mt-2 max-h-32 overflow-auto rounded border border-border bg-inputBg p-3 text-xs text-secondary">
                                <span v-if="user.permissions?.length">{{ user.permissions.join(', ') }}</span>
                                <span v-else>{{ tx('Keine direkten oder rollenbasierten Berechtigungen.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition-colors hover:bg-primary/80 disabled:opacity-50"
                        >
                            {{ form.processing ? tx('Speichert...') : tx('Speichern') }}
                        </button>
                        <button
                            type="button"
                            @click="$inertia.visit(route('members.index'))"
                            class="rounded-lg border border-border bg-card px-4 py-2 text-primary transition-colors hover:border-borderHover"
                        >
                            {{ tx('Abbrechen') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>



