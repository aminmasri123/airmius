<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    profile_visibility: 'public',
})

const submit = () => {
    form.post(route('members.store'))
}
</script>

<template>
    <AppLayout>
        <Head title="Neuen Nutzer erstellen" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">Neuen Nutzer erstellen</h1>
                <p class="mt-2 text-sm text-secondary">Erstelle einen neuen Nutzer für die Plattform.</p>
            </div>

            <div class="surface-card max-w-lg p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-primary">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-primary">E-Mail</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                        <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-primary">Passwort</label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                        <div v-if="form.errors.password" class="mt-1 text-sm text-error">{{ form.errors.password }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-primary">Passwort bestätigen</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-primary">Profil-Sichtbarkeit</label>
                        <select
                            v-model="form.profile_visibility"
                            class="mt-1 block w-full px-3 py-2 border border-border rounded-lg bg-inputBg text-primary focus:outline-none focus:ring-borderHover focus:border-borderHover"
                        >
                            <option value="public">Öffentlich</option>
                            <option value="private">Privat</option>
                        </select>
                        <div v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</div>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-lg hover:bg-buttonPrimaryHover transition"
                        >
                            Erstellen
                        </button>
                        <button
                            type="button"
                            @click="$inertia.visit(route('members.index'))"
                            class="px-4 py-2 bg-card border border-border text-primary rounded-lg hover:border-borderHover transition"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
