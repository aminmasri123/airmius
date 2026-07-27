<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

const editProfile = () => {
    router.visit(route('profile.show'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="$t('E-Mail-Verifizierung')" />

    <AuthenticationCard>
        <div class="mb-5 flex justify-center">
            <AuthenticationCardLogo />
        </div>

        <div class="mb-4 text-sm text-gray-600">
            {{ $t('Bevor du fortfährst, bestätige bitte deine E-Mail-Adresse über den Link, den wir dir gerade gesendet haben. Wenn du keine E-Mail erhalten hast, senden wir dir gerne eine neue.') }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600">
            {{ $t('Ein neuer Bestätigungslink wurde an die E-Mail-Adresse gesendet, die du in deinem Profil angegeben hast.') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-5 grid gap-3">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Bestätigungs-E-Mail erneut senden') }}
                </PrimaryButton>

                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        @click="editProfile"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-border bg-card px-3 py-2 text-center text-sm font-semibold text-primary hover:border-borderHover focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        {{ $t('Profil bearbeiten') }}
                    </button>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-border bg-card px-3 py-2 text-center text-sm font-semibold text-primary hover:border-borderHover focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        {{ $t('Abmelden') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
