<script setup>
import { ref, reactive, nextTick } from 'vue';
import DialogModal from './DialogModal.vue';
import InputError from './InputError.vue';
import PrimaryButton from './PrimaryButton.vue';
import SecondaryButton from './SecondaryButton.vue';
import TextInput from './TextInput.vue';
import { useI18n } from 'vue-i18n'

const emit = defineEmits(['confirmed']);

defineProps({
    title: {
        type: String,
        default: 'settings.security.confirm_password',
    },
    content: {
        type: String,
        default: 'settings.security.confirm_password_prompt',
    },
    button: {
        type: String,
        default: 'settings.security.confirm',
    },
});

const confirmingPassword = ref(false);
const { t, te } = useI18n()

const localize = (value) => te(value) ? t(value) : value

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref(null);

const startConfirmingPassword = () => {
    axios.get(route('password.confirmation')).then(response => {
        if (response.data.confirmed) {
            emit('confirmed');
        } else {
            confirmingPassword.value = true;

            setTimeout(() => passwordInput.value.focus(), 250);
        }
    });
};

const confirmPassword = () => {
    form.processing = true;

    axios.post(route('password.confirm'), {
        password: form.password,
    }, {
        headers: {
            Accept: 'application/json',
        },
    }).then(() => {
        return axios.get(route('password.confirmation'), {
            headers: {
                Accept: 'application/json',
            },
        });
    }).then((response) => {
        form.processing = false;

        if (!response.data?.confirmed) {
            form.error = localize('settings.security.confirm_password_failed');
            passwordInput.value?.focus();
            return;
        }

        closeModal();
        nextTick().then(() => emit('confirmed'));
    }).catch(error => {
        form.processing = false;
        form.error = error.response?.data?.errors?.password?.[0]
            ?? localize('settings.security.confirm_password_failed');
        passwordInput.value?.focus();
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    form.password = '';
    form.error = '';
};
</script>

<template>
    <span>
        <span @click="startConfirmingPassword">
            <slot />
        </span>

        <DialogModal :show="confirmingPassword" @close="closeModal">
            <template #title>
                {{ localize(title) }}
            </template>

            <template #content>
                {{ localize(content) }}

                <div class="mt-4">
                    <TextInput
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        :placeholder="$t('Password')"
                        autocomplete="current-password"
                        @keyup.enter="confirmPassword"
                    />

                    <InputError :message="form.error" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    {{ $t('Cancel') }}
                </SecondaryButton>

                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmPassword"
                >
                    {{ localize(button) }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </span>
</template>
