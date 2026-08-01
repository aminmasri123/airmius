<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import DateInput from '@/Components/DateInput.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    accountType: { type: String, default: 'athlete' },
})

const accountTypes = [
    { value: 'athlete', label: 'Sportler', icon: 'las la-running' },
    { value: 'coach', label: 'Trainer / Coach', icon: 'las la-chalkboard-teacher' },
    { value: 'club', label: 'Verein', icon: 'las la-building' },
    { value: 'sponsor', label: 'Sponsor', icon: 'las la-handshake' },
]

const form = useForm({
    account_type: props.accountType,
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    country: props.user.country || 'DE',
    birth_date: props.user.birth_date || '',
    gender: props.user.gender || '',
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
    <Head :title="$t('Profil vervollständigen')" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="mb-5 rounded-lg border border-border bg-inputBg p-4 text-sm text-secondary">
            {{ $t('Bitte vervollständige dein Profil. Das ist wichtig für Jugendschutz, Elternzustimmung und faire Nutzung der Plattform.') }}
        </div>

        <form @submit.prevent="submit">
            <fieldset class="mb-5">
                <legend class="text-sm font-bold text-primary">{{ $t('Wie möchtest du Airmius nutzen?') }}</legend>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label v-for="type in accountTypes" :key="type.value" class="cursor-pointer rounded-lg border p-3" :class="form.account_type === type.value ? 'border-buttonPrimary bg-buttonPrimary/10' : 'border-border bg-inputBg'">
                        <input v-model="form.account_type" type="radio" :value="type.value" class="sr-only" />
                        <span class="flex items-center gap-2 text-sm font-bold text-primary"><i :class="[type.icon, 'text-buttonPrimary']"></i>{{ $t(type.label) }}</span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.account_type" />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="first_name" :value="$t('Vorname')" />
                    <TextInput id="first_name" v-model="form.first_name" type="text" class="mt-1 block w-full" required autocomplete="given-name" />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" :value="$t('Nachname')" />
                    <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full" required autocomplete="family-name" />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="birth_date" :value="$t('Geburtsdatum')" />
                    <DateInput id="birth_date" v-model="form.birth_date" class="mt-1 block w-full" required autocomplete="bday" />
                    <InputError class="mt-2" :message="form.errors.birth_date" />
                </div>

                <div>
                    <InputLabel for="gender" :value="$t('Geschlecht')" />
                    <select id="gender" v-model="form.gender" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">{{ $t('Bitte wählen') }}</option>
                        <option value="female">{{ $t('Weiblich') }}</option>
                        <option value="male">{{ $t('Männlich') }}</option>
                        <option value="diverse">{{ $t('Divers') }}</option>
                        <option value="not_specified">{{ $t('Keine Angabe') }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.gender" />
                </div>
            </div>

            <div class="mt-4">
                <div>
                    <InputLabel for="country" :value="$t('Land')" />
                    <select id="country" v-model="form.country" class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="country">
                        <option value="DE">{{ $t('Deutschland') }}</option>
                        <option value="AT">{{ $t('Österreich') }}</option>
                        <option value="CH">{{ $t('Schweiz') }}</option>
                        <option value="FR">{{ $t('Frankreich') }}</option>
                        <option value="NL">{{ $t('Niederlande') }}</option>
                        <option value="BE">{{ $t('Belgien') }}</option>
                        <option value="MA">{{ $t('Marokko') }}</option>
                        <option value="ES">{{ $t('Spanien') }}</option>
                        <option value="PT">{{ $t('Portugal') }}</option>
                        <option value="IT">{{ $t('Italien') }}</option>
                        <option value="GB">{{ $t('Großbritannien') }}</option>
                        <option value="TR">{{ $t('Türkei') }}</option>
                        <option value="US">{{ $t('USA') }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>
            </div>

            <div v-if="requiresGuardianConsent" class="mt-4">
                <InputLabel for="guardian_email" :value="$t('E-Mail des Erziehungsberechtigten')" />
                <TextInput id="guardian_email" v-model="form.guardian_email" type="email" class="mt-1 block w-full" required autocomplete="email" />
                <p class="mt-2 text-sm text-secondary">
                    {{ $t('Unter 16 Jahren ist eine Zustimmung eines Erziehungsberechtigten erforderlich.') }}
                </p>
                <InputError class="mt-2" :message="form.errors.guardian_email" />
            </div>

            <div class="mt-5 flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Profil speichern') }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
