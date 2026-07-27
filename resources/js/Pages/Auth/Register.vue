<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import AuthVisualSlider from '@/Components/AuthVisualSlider.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage()
const redirectTarget = new URLSearchParams(page.url.split('?')[1] || '').get('redirect')
const authRouteParams = redirectTarget ? { redirect: redirectTarget } : {}
const socialRouteParams = (provider) => ({ provider, ...authRouteParams })
const loginImages = computed(() => page.props.loginImages || [])

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    country: 'DE',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    state: '',
    birth_date: '',
    gender: '',
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

const goBack = () => {
    window.history.back()
}
</script>

<template>
    <Head :title="$t('Registrieren')" />

    <button type="button" @click="goBack" class="absolute top-4 left-4 z-[101] md:top-16 md:left-24 text-primary text-sm">
        <i class="las la-chevron-circle-left la-lg"></i>
    </button>

    <div class="min-h-screen flex bg-bg text-primary">
        <div class="w-full md:w-1/2 flex items-start justify-center px-4 py-10 sm:px-8 lg:px-10">
            <div class="surface-card w-full max-w-md px-6 py-5 overflow-hidden">
                <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
                    <AuthenticationCardLogo />
                </div>

                <div class="mb-5 grid gap-2">
                    <a
                        :href="route('social-auth.redirect', socialRouteParams('google'))"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-google text-lg"></i>
                        {{ $t('Mit Google registrieren') }}
                    </a>
                    <a
                        :href="route('social-auth.redirect', socialRouteParams('microsoft'))"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-microsoft text-lg"></i>
                        {{ $t('Mit Outlook registrieren') }}
                    </a>
                </div>

                <form @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="first_name" :value="$t('Vorname')" />
                    <TextInput
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="given-name"
                    />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" :value="$t('Nachname')" />
                    <TextInput
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autocomplete="family-name"
                    />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="email" :value="$t('Email')" />
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

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="birth_date" :value="$t('Geburtsdatum')" />
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
                    <InputLabel for="gender" :value="$t('Geschlecht')" />
                    <select
                        id="gender"
                        v-model="form.gender"
                        class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                        required
                    >
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
                    <select
                        id="country"
                        v-model="form.country"
                        class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                        required
                        autocomplete="country"
                    >
                        <option value="AU">{{$t('Australien')}}</option>
                        <option value="BE">{{$t('Belgien')}}</option>
                        <option value="BR">{{$t('Brasilien')}}</option>
                        <option value="CN">{{$t('China')}}</option>
                        <option value="DE">{{$t('Deutschland')}}</option>
                        <option value="FR">{{$t('Frankreich')}}</option>
                        <option value="GB">{{$t('Großbritannien')}}</option>
                        <option value="IN">{{$t('Indien')}}</option>
                        <option value="IT">{{$t('Italien')}}</option>
                        <option value="CA">{{$t('Kanada')}}</option>
                        <option value="NL">{{$t('Marokko')}}</option>
                        <option value="NL">{{$t('Niederlande')}}</option>
                        <option value="AT">{{$t('Österreich')}}</option>
                        <option value="PL">{{$t('Polen')}}</option>
                        <option value="RU">{{$t('Russland')}}</option>
                        <option value="CH">{{$t('Schweiz')}}</option>
                        <option value="ES">{{$t('Spanien')}}</option>
                        <option value="TR">{{$t('Türkei')}}</option>
                        <option value="US">{{$t('USA')}}</option>
                        <option value="Other">{{$t('Anderes Land')}}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>
            </div>

            <!-- <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_8rem]">
                <div>
                    <InputLabel for="street" value="Straße" />
                    <TextInput
                        id="street"
                        v-model="form.street"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="street-address"
                    />
                    <InputError class="mt-2" :message="form.errors.street" />
                </div>

                <div>
                    <InputLabel for="house_number" value="Hausnr." />
                    <TextInput
                        id="house_number"
                        v-model="form.house_number"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="address-line2"
                    />
                    <InputError class="mt-2" :message="form.errors.house_number" />
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-[8rem_1fr]">
                <div>
                    <InputLabel for="postal_code" value="PLZ" />
                    <TextInput
                        id="postal_code"
                        v-model="form.postal_code"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="postal-code"
                    />
                    <InputError class="mt-2" :message="form.errors.postal_code" />
                </div>

                <div>
                    <InputLabel for="city" value="Stadt" />
                    <TextInput
                        id="city"
                        v-model="form.city"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="address-level2"
                    />
                    <InputError class="mt-2" :message="form.errors.city" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="state" value="Bundesland / Region" />
                <TextInput
                    id="state"
                    v-model="form.state"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="address-level1"
                />
                <InputError class="mt-2" :message="form.errors.state" />
            </div> -->

            <div v-if="requiresGuardianConsent" class="mt-4">
                <InputLabel for="guardian_email" :value="$t('E-Mail des Erziehungsberechtigten')" />
                <TextInput
                    id="guardian_email"
                    v-model="form.guardian_email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="email"
                />
                <p class="mt-2 text-sm text-secondary">
                    {{ $t('Unter 16 Jahren ist eine Zustimmung eines Erziehungsberechtigten erforderlich.') }}
                </p>
                <InputError class="mt-2" :message="form.errors.guardian_email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="$t('Password')" />
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
                <InputLabel for="password_confirmation" :value="$t('Confirm Password')" />
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

            <div class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            {{ $t('Ich akzeptiere die') }}
                            <a
                                target="_blank"
                                :href="route('terms.show')"
                                rel="noopener noreferrer"
                                class="rounded-md text-sm text-secondary underline hover:text-primary focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2"
                            >{{ $t('AGB') }}</a>
                            {{ $t('und die') }}
                            <a
                                target="_blank"
                                :href="route('policy.show')"
                                rel="noopener noreferrer"
                                class="rounded-md text-sm text-secondary underline hover:text-primary focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2"
                            >{{ $t('Datenschutzerklärung') }}</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Registrieren') }}
                </PrimaryButton>

                <SecondaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    <Link :href="route('login', authRouteParams)">{{ $t('Anmelden') }}</Link>
                </SecondaryButton>
            </div>
                </form>
            </div>
        </div>

        <AuthVisualSlider :slides="loginImages" title="Airmius starten" subtitle="Teams - Events - Marketplace" />
    </div>
</template>
