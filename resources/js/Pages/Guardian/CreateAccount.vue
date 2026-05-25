<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

defineProps({
    email: {
        type: String,
        required: true,
    },
    hasExistingAccount: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()

const form = useForm({
    first_name: '',
    last_name: '',
    birth_date: '',
    country: 'DE',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    if (form.processing) {
        return
    }

    form.post(route('guardian-access.account.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Elternkonto erstellen" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="rounded-lg border border-border bg-card p-6">
            <h1 class="text-xl font-semibold text-primary">
                {{ hasExistingAccount ? 'Konto verknüpfen' : 'Elternkonto erstellen' }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Die E-Mail wurde bereits im Elternbereich bestätigt:
                <span class="font-semibold text-primary">{{ email }}</span>
            </p>

            <div v-if="hasExistingAccount" class="mt-4 rounded-lg border border-border bg-inputBg p-4 text-sm text-secondary">
                Zu dieser E-Mail existiert bereits ein Konto. Wenn du fortfährst, verknüpfen wir es als Elternkonto
                mit den Kindern, die diese Eltern-E-Mail verwenden.
            </div>

            <div v-if="page.props.errors?.email" class="mt-4 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error" role="status" aria-live="polite">
                {{ page.props.errors.email }}
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <template v-if="!hasExistingAccount">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="first_name" value="Vorname" />
                            <TextInput
                                id="first_name"
                                v-model="form.first_name"
                                class="mt-1 block w-full"
                                required
                                autocomplete="given-name"
                            />
                            <InputError class="mt-2" :message="form.errors.first_name" />
                        </div>

                        <div>
                            <InputLabel for="last_name" value="Nachname" />
                            <TextInput
                                id="last_name"
                                v-model="form.last_name"
                                class="mt-1 block w-full"
                                required
                                autocomplete="family-name"
                            />
                            <InputError class="mt-2" :message="form.errors.last_name" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="birth_date" value="Geburtsdatum" />
                            <TextInput
                                id="birth_date"
                                v-model="form.birth_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                                autocomplete="bday"
                            />
                            <InputError class="mt-2" :message="form.errors.birth_date" />
                        </div>

                        <div>
                            <InputLabel for="country" value="Land" />
                            <select id="country" v-model="form.country" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary" required>
                                <option value="DE">Deutschland</option>
                                <option value="AT">Oesterreich</option>
                                <option value="CH">Schweiz</option>
                                <option value="FR">Frankreich</option>
                                <option value="NL">Niederlande</option>
                                <option value="BE">Belgien</option>
                                <option value="TR">Tuerkei</option>
                                <option value="US">USA</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.country" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="password" value="Passwort" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Passwort bestätigen" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>
                </template>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link :href="route('guardian-access.children')" class="text-sm text-secondary underline hover:text-primary">
                        Zurück zur Kinderübersicht
                    </Link>

                    <PrimaryButton
                        :disabled="form.processing"
                        :class="{ 'opacity-60': form.processing }"
                        :aria-busy="form.processing"
                    >
                        {{ hasExistingAccount ? 'Vorhandenes Konto verknüpfen' : 'Elternkonto erstellen' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticationCard>
</template>
