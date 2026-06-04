<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed } from 'vue'

const page = usePage()
const form = useForm({
    email: '',
})

const submitting = computed(() => form.processing)

const submit = () => {
    if (form.processing) {
        return
    }

    form.post(route('guardian-access.store'))
}
</script>

<template>
    <Head title="Eltern-Login" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="rounded-lg border border-border bg-card p-6">
            <h1 class="text-xl font-semibold text-primary">Elternbereich</h1>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Gib die E-Mail-Adresse ein, die beim Kind als Eltern-/Erziehungsberechtigten-E-Mail gespeichert wurde.
                Danach senden wir dir einen 6-stelligen Code, der 15 Minuten gültig ist.
            </p>

            <div v-if="page.props.flash?.status || page.props.status" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                {{ page.props.flash?.status || page.props.status }}
            </div>
            <div v-if="form.errors.email" class="mt-4 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error" role="status" aria-live="polite">
                {{ form.errors.email }}
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="E-Mail" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="email"
                        inputmode="email"
                        @input="form.clearErrors('email')"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <PrimaryButton
                    :disabled="submitting"
                    :class="{ 'opacity-60': submitting }"
                    :aria-busy="submitting"
                >
                    Code anfordern
                </PrimaryButton>
            </form>

            <div class="mt-5 text-sm text-secondary">
                <Link :href="route('login')" class="underline hover:text-primary">Normaler Login</Link>
            </div>
        </div>
    </AuthenticationCard>
</template>

