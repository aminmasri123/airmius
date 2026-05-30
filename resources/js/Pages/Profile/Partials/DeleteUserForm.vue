<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from 'vue-i18n';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const codeInput = ref(null);
const step = ref('identity');
const page = usePage();
const confirmsWithEmail = page.props.auth.user?.has_social_login;
const { t } = useI18n();

const form = useForm({
    password: '',
    code: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value?.focus(), 250);
};

const requestDeletionCode = () => {
    form.post(route('current-user.deletion-code'), {
        preserveScroll: true,
        onSuccess: () => {
            step.value = 'code';
            form.reset('password');
            setTimeout(() => codeInput.value?.focus(), 250);
        },
        onError: () => passwordInput.value?.focus(),
    });
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => codeInput.value?.focus(),
    });
};

const returnToIdentityStep = () => {
    step.value = 'identity';
    form.reset('code');
    form.clearErrors();
    setTimeout(() => passwordInput.value?.focus(), 250);
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    step.value = 'identity';

    form.reset();
    form.clearErrors();
};
</script>

<template>
    <ActionSection>
        <template #title>
            {{ t('settings.delete_account.title') }}
        </template>

        <template #description>
            {{ t('settings.delete_account.description') }}
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-secondary">
                {{ t('settings.delete_account.body') }}
            </div>

            <div class="mt-5">
                <DangerButton @click="confirmUserDeletion">
                    {{ t('settings.delete_account.title') }}
                </DangerButton>
            </div>

            <!-- Delete Account Confirmation Modal -->
            <DialogModal :show="confirmingUserDeletion" @close="closeModal">
                <template #title>
                    {{ t('settings.delete_account.modal_title') }}
                </template>

                <template #content>
                    <template v-if="step === 'identity'">
                        <span v-if="confirmsWithEmail">
                            {{ t('settings.delete_account.identity_email') }}
                        </span>
                        <span v-else>
                            {{ t('settings.delete_account.identity_password') }}
                        </span>
                    </template>
                    <template v-else>
                        {{ t('settings.delete_account.code_sent') }}
                    </template>

                    <div v-if="step === 'identity'" class="mt-4">
                        <TextInput
                            ref="passwordInput"
                            v-model="form.password"
                            :type="confirmsWithEmail ? 'email' : 'password'"
                            class="mt-1 block w-3/4"
                            :placeholder="confirmsWithEmail ? page.props.auth.user.email : t('settings.delete_account.password_placeholder')"
                            :autocomplete="confirmsWithEmail ? 'email' : 'current-password'"
                            @keyup.enter="requestDeletionCode"
                        />

                        <InputError :message="form.errors.password" class="mt-2" />
                    </div>

                    <div v-else class="mt-4">
                        <TextInput
                            ref="codeInput"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            class="mt-1 block w-3/4"
                            :placeholder="t('settings.delete_account.code_placeholder')"
                            autocomplete="one-time-code"
                            @keyup.enter="deleteUser"
                        />

                        <InputError :message="form.errors.code" class="mt-2" />
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="step === 'identity' ? closeModal() : returnToIdentityStep()">
                        {{ step === 'identity' ? t('settings.actions.cancel') : t('settings.actions.back') }}
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="step === 'identity' ? requestDeletionCode() : deleteUser()"
                    >
                        {{ step === 'identity' ? t('settings.delete_account.send_code') : t('settings.delete_account.title') }}
                    </DangerButton>
                </template>
            </DialogModal>
        </template>
    </ActionSection>
</template>
