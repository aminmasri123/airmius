<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const codeInput = ref(null);
const step = ref('identity');
const page = usePage();
const confirmsWithEmail = page.props.auth.user?.has_social_login;

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
            Delete Account
        </template>

        <template #description>
            Permanently delete your account.
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-secondary">
                Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
            </div>

            <div class="mt-5">
                <DangerButton @click="confirmUserDeletion">
                    Delete Account
                </DangerButton>
            </div>

            <!-- Delete Account Confirmation Modal -->
            <DialogModal :show="confirmingUserDeletion" @close="closeModal">
                <template #title>
                    Delete Account
                </template>

                <template #content>
                    <template v-if="step === 'identity'">
                        <span v-if="confirmsWithEmail">
                            Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your email address. We will then send you a confirmation code.
                        </span>
                        <span v-else>
                            Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password. We will then send you a confirmation code by email.
                        </span>
                    </template>
                    <template v-else>
                        We sent a confirmation code to your email address. Please enter the code to permanently delete your account.
                    </template>

                    <div v-if="step === 'identity'" class="mt-4">
                        <TextInput
                            ref="passwordInput"
                            v-model="form.password"
                            :type="confirmsWithEmail ? 'email' : 'password'"
                            class="mt-1 block w-3/4"
                            :placeholder="confirmsWithEmail ? page.props.auth.user.email : 'Password'"
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
                            placeholder="Confirmation code"
                            autocomplete="one-time-code"
                            @keyup.enter="deleteUser"
                        />

                        <InputError :message="form.errors.code" class="mt-2" />
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="step === 'identity' ? closeModal() : returnToIdentityStep()">
                        {{ step === 'identity' ? 'Cancel' : 'Back' }}
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="step === 'identity' ? requestDeletionCode() : deleteUser()"
                    >
                        {{ step === 'identity' ? 'Send code' : 'Delete Account' }}
                    </DangerButton>
                </template>
            </DialogModal>
        </template>
    </ActionSection>
</template>
