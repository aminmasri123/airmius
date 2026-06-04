<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="$t('E-Mail-Verifizierung')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            {{ $t('Bevor du fortfährst, bestätige bitte deine E-Mail-Adresse über den Link, den wir dir gerade gesendet haben. Wenn du keine E-Mail erhalten hast, senden wir dir gerne eine neue.') }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600">
            {{ $t('Ein neuer Bestätigungslink wurde an die E-Mail-Adresse gesendet, die du in deinem Profil angegeben hast.') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Bestätigungs-E-Mail erneut senden') }}
                </PrimaryButton>

                <div>
                    <Link
                        :href="route('profile.show')"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ $t('Profil bearbeiten') }}</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ms-2"
                    >
                        {{ $t('Abmelden') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>

