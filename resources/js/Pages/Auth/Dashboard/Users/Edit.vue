<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

const props = defineProps({
    user: Object,
})

const form = useForm({
    name: props.user.name,
    email: props.user.email,
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
