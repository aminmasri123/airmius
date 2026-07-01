<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthVisualSlider from '@/Components/AuthVisualSlider.vue';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    loginImages: { type: Array, default: () => [] },
});

const page = usePage()
const redirectTarget = new URLSearchParams(page.url.split('?')[1] || '').get('redirect')
const authRouteParams = redirectTarget ? { redirect: redirectTarget } : {}
const socialRouteParams = (provider) => ({ provider, ...authRouteParams })

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const images = props.loginImages

const goBack = () => {
    window.history.back()
}
</script>

<template>
    <Head :title="$t('Anmelden')" />

    <button type="button" @click="goBack" class="absolute top-4 left-4 md:top-16 md:left-24 text-primary text-sm">
        <i class="las la-chevron-circle-left la-lg"></i>
    </button>

    <div class="min-h-screen flex bg-bg text-primary">
        <div class="w-full md:w-1/2 flex items-center justify-center px-6 sm:px-10">
            <div class="surface-card w-full max-w-md px-6 py-8 sm:px-8">
                <div class="w-36 h-36 md:w-48 md:h-48 context-center mx-auto mt-10">
                    <AuthenticationCardLogo />
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-success">
                    {{ status }}
                </div>

                <div class="mb-4 rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                    {{ $t('Eltern/Erziehungsberechtigte?') }}
                    <Link :href="route('guardian-access.create')" class="font-semibold text-primary underline">
                        {{ $t('Elternbereich ohne Konto öffnen') }}
                    </Link>
                </div>

                <div class="mb-4 grid gap-2">
                    <a
                        :href="route('social-auth.redirect', socialRouteParams('google'))"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-google text-lg"></i>
                        {{ $t('Mit Google anmelden') }}
                    </a>
                    <a
                        :href="route('social-auth.redirect', socialRouteParams('microsoft'))"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-microsoft text-lg"></i>
                        {{ $t('Mit Outlook anmelden') }}
                    </a>
                </div>

                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" :value="$t('Email')" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" :value="$t('Passwort')" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="block mt-4">
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            <span class="ms-2 text-sm text-secondary">{{ $t('Angemeldet bleiben') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-start my-4">
                        <SecondaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <Link :href="route('register', authRouteParams)">{{ $t('Registrieren') }}?</Link>
                        </SecondaryButton>

                        <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ $t('Anmelden') }}
                        </PrimaryButton>
                    </div>

                    <Link v-if="canResetPassword" :href="route('password.request')" class="underline text-sm text-secondary hover:text-primary">
                        {{ $t('Passwort vergessen?') }}
                    </Link>
                </form>
            </div>
        </div>

        <AuthVisualSlider :slides="images" />
    </div>
</template>

