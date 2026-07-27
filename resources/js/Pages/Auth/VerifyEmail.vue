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
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PrimaryButton
                    class="justify-center sm:flex-none"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Bestätigungs-E-Mail erneut senden') }}
                </PrimaryButton>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Link
                        :href="route('profile.show')"
                        class="inline-flex items-center justify-center rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:border-borderHover focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        {{ $t('Profil bearbeiten') }}</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex items-center justify-center rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-primary hover:border-borderHover focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        {{ $t('Abmelden') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
