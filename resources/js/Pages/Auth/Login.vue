<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, onMounted } from 'vue'

defineProps({
    canResetPassword: Boolean,
    status: String,
});

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

const images = [
    '/img/login/slide1.png',
    '/img/login/slide2.png',

    /*  '/img/login/slide2.jpeg',
     '/img/login/slide3.jpeg',
     '/img/login/slide4.jpeg', */
]

const current = ref(0)

let interval

onMounted(() => {
    interval = setInterval(() => {
        current.value = (current.value + 1) % images.length
    }, 8000)
})


const goBack = () => {
  if (window.history.length > 1) {
    window.history.back()
  } else {
    window.location.href = route('dashboard')
  }
}
</script>

<template>

    <Head :title="$t('Anmelden')" />
    <button type="button" @click="goBack" class="absolute top-4 left-4 md:top-16 md:left-24 text-primary text-sm">
        <i class="las la-chevron-circle-left la-lg"></i>
    </button>
    <div class="min-h-screen flex bg-bg text-primary">

        <!-- LINKS: LOGIN -->
        <div class="w-full md:w-1/2 flex items-center justify-center px-6 sm:px-10">
            <div class="surface-card w-full max-w-md px-6 py-8 sm:px-8">

                <div class="w-36 h-36 md:w-48 md:h-48 context-center mx-auto mt-10">
                    <AuthenticationCardLogo />

                </div>
                <div v-if="status" class="mb-4 font-medium text-sm text-success">
                    {{ status }}
                </div>
                <div class="mb-4 rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                    Eltern/Erziehungsberechtigte?
                    <Link :href="route('guardian-access.create')" class="font-semibold text-primary underline">
                        Elternbereich ohne Konto oeffnen
                    </Link>
                </div>

                <div class="mb-4 grid gap-2">
                    <a
                        :href="route('social-auth.redirect', 'google')"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-google text-lg"></i>
                        Mit Google anmelden
                    </a>
                    <a
                        :href="route('social-auth.redirect', 'microsoft')"
                        class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                    >
                        <i class="lab la-microsoft text-lg"></i>
                        Mit Outlook anmelden
                    </a>
                </div>

                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" :value="$t('Email')" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                            autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" :value="$t('Passwort')" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full"
                            required autocomplete="current-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="block mt-4">
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            <span class="ms-2 text-sm text-secondary">{{ $t('Angemeldet bleiben') }}</span>
                        </label>
                    </div>

                        <div class="flex items-center justify-start my-4">
                            <SecondaryButton class="" :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing">
                                <Link :href="route('register')">{{ $t('Registrieren') }}?</Link>
                            </SecondaryButton>

                            <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing">
                                {{ $t('Anmelden') }}
                            </PrimaryButton>
                        </div>

                        <Link v-if="canResetPassword" :href="route('password.request')"
                            class="underline  text-sm text-secondary hover:text-primary">
                            {{ $t('Passwort vergessen?') }}
                        </Link>
                </form>
            </div>
        </div>

        <!-- RECHTS: SLIDER -->


        <div class="hidden md:block md:w-1/2 bg-bg">
            <div class="hidden h-screen md:flex  text-white items-center justify-center">

                <div class="w-full h-full relative overflow-hidden">
                    <!-- Slider Container -->
                    <div class="h-full w-4/6 relative">

                        <!-- SLIDES -->
                        <div v-for="(img, index) in images" :key="index" :class="[
                            'absolute inset-0 transition-all duration-700',
                            current === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105'
                        ]">
                            <img :src="img"
                                class="w-full h-full object-cover transition-transform duration-[8000ms] ease-in-out"
                                :class="current === index ? 'scale-110' : 'scale-300'" />
                        </div>

                        <!-- LEFT BUTTON -->
                        <button @click="current = (current - 1 + images.length) % images.length"
                            class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 p-3 rounded-full z-20">
                            ‹
                        </button>

                        <!-- RIGHT BUTTON -->
                        <button @click="current = (current + 1) % images.length"
                            class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 p-3 rounded-full z-20">
                            ›
                        </button>

                        <!-- Text Overlay -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div
                                class="absolute text-center bottom-10 text-white bg-black/55 p-8 rounded-xl backdrop-blur">
                                <h2 class="text-2xl font-bold">{{ $t('Sport Plattform') }}</h2>
                                <p class="text-sm opacity-80">{{ $t('Team Management · Kommunikation · Events') }}</p>


                                <div class="absolute  left-1/2 -translate-x-1/2 flex gap-2 z-20">
                                    <button v-for="(img, index) in images" :key="index" @click="current = index"
                                        class="w-2.5 h-2.5 rounded-full transition-all mt-2 duration-300"
                                        :class="current === index ? 'bg-primary scale-125' : 'bg-primary/40 hover:bg-primary/70'" />
                                </div>
                            </div>
                        </div>


                    </div>


                </div>
            </div>
        </div>
    </div>


</template>
