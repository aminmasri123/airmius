<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    emailOtpAvailable: {
        type: Boolean,
        default: false,
    },
});

const method = ref('authenticator');
const emailSending = ref(false);
const emailSent = ref(false);
const emailError = ref('');

const form = useForm({
    code: '',
    recovery_code: '',
    email_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);
const emailCodeInput = ref(null);

const selectMethod = async (selectedMethod) => {
    method.value = selectedMethod;
    form.clearErrors();
    form.code = '';
    form.recovery_code = '';
    form.email_code = '';

    await nextTick();

    if (method.value === 'recovery') {
        recoveryCodeInput.value.focus();
    } else if (method.value === 'email') {
        emailCodeInput.value.focus();
    } else {
        codeInput.value.focus();
    }
};

const sendEmailCode = async () => {
    emailSending.value = true;
    emailError.value = '';

    try {
        await window.axios.post(route('two-factor.email.send'));
        emailSent.value = true;
        await nextTick();
        emailCodeInput.value?.focus();
    } catch (error) {
        emailError.value = error.response?.data?.errors?.email?.[0]
            || error.response?.data?.message
            || 'Der E-Mail-Code konnte nicht gesendet werden.';
    } finally {
        emailSending.value = false;
    }
};

const submit = () => {
    form.post(method.value === 'email'
        ? route('two-factor.email.login')
        : route('two-factor.login'));
};
</script>

<template>
    <Head :title="$t('Zwei-Faktor-Bestätigung')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            <template v-if="method === 'authenticator'">
                {{ $t('Bitte bestätige den Zugriff auf dein Konto, indem du den Code aus deiner Authenticator-App eingibst.') }}
            </template>
            <template v-else-if="method === 'email'">
                {{ $t('Lass dir einen einmaligen Sicherheitscode an deine verifizierte E-Mail-Adresse senden.') }}
            </template>
            <template v-else>
                {{ $t('Bitte bestätige den Zugriff auf dein Konto, indem du einen deiner Wiederherstellungscodes eingibst.') }}
            </template>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" class="rounded-md border px-3 py-2 text-sm" :class="{ 'border-indigo-500 bg-indigo-50': method === 'authenticator' }" @click="selectMethod('authenticator')">
                {{ $t('Authenticator-Code') }}
            </button>
            <button v-if="emailOtpAvailable" type="button" class="rounded-md border px-3 py-2 text-sm" :class="{ 'border-indigo-500 bg-indigo-50': method === 'email' }" @click="selectMethod('email')">
                {{ $t('E-Mail-Code') }}
            </button>
            <button type="button" class="rounded-md border px-3 py-2 text-sm" :class="{ 'border-indigo-500 bg-indigo-50': method === 'recovery' }" @click="selectMethod('recovery')">
                {{ $t('Recovery Code') }}
            </button>
        </div>

        <form @submit.prevent="submit">
            <div v-if="method === 'authenticator'">
                <InputLabel for="code" :value="$t('Code')" />
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full"
                    autofocus
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else-if="method === 'email'">
                <button
                    type="button"
                    class="mb-4 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
                    :disabled="emailSending"
                    @click="sendEmailCode"
                >
                    {{ $t(emailSent ? 'Neuen E-Mail-Code senden' : 'Code per E-Mail senden') }}
                </button>
                <p v-if="emailSent" class="mb-3 text-sm text-green-700">
                    {{ $t('Der Code wurde gesendet und ist 10 Minuten gültig.') }}
                </p>
                <p v-if="emailError" class="mb-3 text-sm text-red-600">{{ emailError }}</p>
                <InputLabel for="email_code" :value="$t('E-Mail-Code')" />
                <TextInput
                    id="email_code"
                    ref="emailCodeInput"
                    v-model="form.email_code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full"
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.email_code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" :value="$t('Recovery Code')" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('Log in') }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
