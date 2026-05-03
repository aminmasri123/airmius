<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    user: { type: Object, default: () => ({}) },
})

const form = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    country: props.user.country || 'DE',
    birth_date: props.user.birth_date || '',
    guardian_email: props.user.guardian_email || '',
})

const requiresGuardianConsent = computed(() => {
    if (!form.birth_date) return false

    const birthDate = new Date(`${form.birth_date}T00:00:00`)
    const today = new Date()
    let age = today.getFullYear() - birthDate.getFullYear()
    const monthDifference = today.getMonth() - birthDate.getMonth()

    if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1
    }

    return age < 16
})

const submit = () => {
    form.put(route('auth.profile-completion.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Profil vervollstaendigen" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="mb-5 rounded-lg border border-border bg-inputBg p-4 text-sm text-secondary">
            Bitte vervollstaendige dein Profil. Das ist wichtig fuer Jugendschutz, Elternzustimmung und faire Nutzung der Plattform.
        </div>

        <form @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="first_name" value="Vorname" />
                    <TextInput id="first_name" v-model="form.first_name" type="text" class="mt-1 block w-full" required autocomplete="given-name" />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" value="Nachname" />
                    <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full" required autocomplete="family-name" />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="birth_date" value="Geburtsdatum" />
                    <TextInput id="birth_date" v-model="form.birth_date" type="date" class="mt-1 block w-full" required autocomplete="bday" />
                    <InputError class="mt-2" :message="form.errors.birth_date" />
                </div>

                <div>
                    <InputLabel for="country" value="Land" />
                    <select id="country" v-model="form.country" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="country">
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

            <div v-if="requiresGuardianConsent" class="mt-4">
                <InputLabel for="guardian_email" value="E-Mail des Erziehungsberechtigten" />
                <TextInput id="guardian_email" v-model="form.guardian_email" type="email" class="mt-1 block w-full" required autocomplete="email" />
                <p class="mt-2 text-sm text-secondary">
                    Unter 16 Jahren ist eine Zustimmung eines Erziehungsberechtigten erforderlich.
                </p>
                <InputError class="mt-2" :message="form.errors.guardian_email" />
            </div>

            <div class="mt-5 flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Profil speichern
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
