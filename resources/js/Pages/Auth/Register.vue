<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    birth_date: '',
    guardian_email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const requiresGuardianConsent = computed(() => {
    if (! form.birth_date) {
        return false;
    }

    const birthDate = new Date(`${form.birth_date}T00:00:00`);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDifference = today.getMonth() - birthDate.getMonth();

    if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }

    return age < 16;
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthenticationCard>
            <div class="w-36 h-36 md:w-48 md:h-48 context-center mx-auto ">
                    <AuthenticationCardLogo />

                </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
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

            <div v-if="requiresGuardianConsent" class="mt-4">
                <InputLabel for="guardian_email" value="E-Mail des Erziehungsberechtigten" />
                <TextInput
                    id="guardian_email"
                    v-model="form.guardian_email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="email"
                />
                <p class="mt-2 text-sm text-secondary">
                    Unter 16 Jahren ist eine Zustimmung eines Erziehungsberechtigten erforderlich.
                </p>
                <InputError class="mt-2" :message="form.errors.guardian_email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
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

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />
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

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-borderHover">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-borderHover">Privacy Policy</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Registrieren') }}
                </PrimaryButton>

                <SecondaryButton class="ms-4" :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing">
                    <Link :href="route('login') ">{{ $t('Anmelden') }}</Link>
                </SecondaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
