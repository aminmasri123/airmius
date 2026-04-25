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
</script>

<template>

    <Head :title="$t('Anmelden')" />

    <div class="min-h-screen flex">

        <!-- LINKS: LOGIN -->
        <div class="w-full md:w-1/2 flex items-center justify-center bg-card px-10">
            <div class="w-full max-w-md">

                <div class="w-64 h-64 context-center mx-auto mt-10">
                    <AuthenticationCardLogo />

                </div>
                <div v-if="status" class="mb-4 font-medium text-sm text-success">
                    {{ status }}
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
                            <span class="ms-2 text-sm text-gray-600">{{ $t('Angemeldet bleiben') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <Link v-if="canResetPassword" :href="route('password.request')"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ $t('Passwort vergessen?') }}
                        </Link>


                        <SecondaryButton class="ms-4" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing">
                            <Link :href="route('register') ">{{ $t('Registrieren') }}?</Link>
                        </SecondaryButton>

                        <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing">
                            {{ $t('Anmelden') }}
                        </PrimaryButton>
                    </div>
                </form>


            </div>
        </div>

        <!-- RECHTS: SLIDER -->


       <div class="w-1/2">
         <div
            class="hidden h-screen md:flex  text-white items-center justify-center">

            <div class="w-full h-full relative overflow-hidden">
                <!-- Slider Container -->
                <div class="h-full w-4/6 relative">

                    <!-- SLIDES -->
                    <div v-for="(img, index) in images" :key="index" :class="[
                        'absolute inset-0 transition-all duration-700',
                        current === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105'
                        ]">
                        <img
                        :src="img"
                        class="w-full h-full object-cover transition-transform duration-[8000ms] ease-in-out"
                        :class="current === index ? 'scale-110' : 'scale-300'"
                        />
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
                    <div class="absolute text-center bottom-10  text-white bg-black/50 p-8 rounded-md">
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
