<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    loginImages: { type: Array, default: () => [] },
});

const page = usePage()
const redirectTarget = new URLSearchParams(page.url.split('?')[1] || '').get('redirect')
const authRouteParams = redirectTarget ? { redirect: redirectTarget } : {}

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

const fallbackImages = [
    { src: '/img/login/bild1.png', alt: 'Airmius Neueröffnung Sport-Plattform' },
    { src: '/img/login/bild2.png', alt: 'Airmius Neueröffnung Marketplace' },
    { src: '/img/login/bild3.png', alt: 'Airmius Community Gamification' },
    { src: '/img/login/bild4.png', alt: 'Airmius Gemeinsam aktiv' },
]

const images = props.loginImages.length ? props.loginImages : fallbackImages

const current = ref(0)

let interval

onMounted(() => {
    interval = setInterval(() => {
        current.value = (current.value + 1) % images.length
    }, 8000)
})

onUnmounted(() => {
    if (interval) {
        clearInterval(interval)
    }
})

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
                        :href="route('social-auth.redirect', 'google')"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-google text-lg"></i>
                        {{ $t('Mit Google anmelden') }}
                    </a>
                    <a
                        :href="route('social-auth.redirect', 'microsoft')"
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

        <div class="hidden md:block md:w-1/2 bg-bg">
            <div class="hidden h-screen md:flex text-white items-center justify-center p-6 lg:p-10">
                <div class="relative flex h-full w-full items-center justify-center overflow-hidden rounded-xl border border-border bg-card">
                    <div
                        class="absolute inset-0 scale-110 bg-cover bg-center opacity-35 blur-2xl transition-all duration-700"
                        :style="{ backgroundImage: `url(${images[current].src})` }"
                    ></div>

                    <div class="relative z-10 aspect-[9/16] h-[min(92vh,56rem)] overflow-hidden rounded-xl bg-black shadow-2xl">
                        <div
                            v-for="(img, index) in images"
                            :key="index"
                            :class="[
                                'absolute inset-0 transition-all duration-700',
                                current === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105',
                            ]"
                        >
                            <img
                                :src="img.src"
                                :alt="img.alt"
                                class="h-full w-full object-contain transition-transform duration-[8000ms] ease-in-out"
                                :class="current === index ? 'scale-105' : 'scale-110'"
                            />
                        </div>

                        <button
                            type="button"
                            @click="current = (current - 1 + images.length) % images.length"
                            class="absolute left-4 top-1/2 z-20 rounded-full bg-black/40 p-3 hover:bg-black/60"
                        >
                            <i class="las la-angle-left"></i>
                        </button>

                        <button
                            type="button"
                            @click="current = (current + 1) % images.length"
                            class="absolute right-4 top-1/2 z-20 rounded-full bg-black/40 p-3 hover:bg-black/60"
                        >
                            <i class="las la-angle-right"></i>
                        </button>

                        <div class="pointer-events-none absolute inset-0 flex items-end justify-center p-5">
                            <div class="w-full rounded-lg bg-black/55 p-5 text-center text-white backdrop-blur">
                                <h2 class="text-2xl font-bold">{{ $t('Sport Plattform') }}</h2>
                                <p class="text-sm opacity-80">{{ $t('Team Management - Kommunikation - Events') }}</p>

                                <div class="pointer-events-auto mt-3 flex justify-center gap-2">
                                    <button
                                        v-for="(img, index) in images"
                                        :key="index"
                                        type="button"
                                        @click="current = index"
                                        class="h-2.5 w-2.5 rounded-full transition-all duration-300"
                                        :class="current === index ? 'bg-primary scale-125' : 'bg-primary/40 hover:bg-primary/70'"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

