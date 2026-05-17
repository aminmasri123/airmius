<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, ref } from 'vue'

defineProps({
    email: {
        type: String,
        default: '',
    },
})

const page = usePage()
const confirmForm = useForm({
    code: '',
})
const resendForm = useForm({
    email: '',
})

const isSubmitting = computed(() => confirmForm.processing)
const isResending = computed(() => resendForm.processing)
const resendError = ref('')
const backendError = computed(() => page.props.errors?.code || page.props.errors?.email || resendError.value)

const submit = () => {
    if (isSubmitting.value) {
        return
    }

    confirmForm.clearErrors()
    confirmForm.post(route('guardian-access.confirm'))
}

const resendCode = () => {
    if (isResending.value || isSubmitting.value) {
        return
    }

    resendForm.email = page.props.email || ''
    resendError.value = ''

    resendForm.post(route('guardian-access.store'), {
        preserveScroll: true,
        onError: (errors) => {
            resendError.value = errors?.email || 'Der Code konnte nicht erneut gesendet werden. Bitte versuche es erneut.'
        },
        onSuccess: () => {
            resendError.value = ''
        },
    })
}
</script>

<template>
    <Head title="Eltern-Code bestaetigen" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="rounded-lg border border-border bg-card p-6">
            <h1 class="text-xl font-semibold text-primary">Code bestaetigen</h1>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Wir haben einen 6-stelligen Code an
                <span class="font-semibold text-primary">{{ email || 'deine E-Mail' }}</span>
                gesendet, wenn diese Adresse bei uns gespeichert ist.
            </p>

            <div v-if="page.props.flash?.status || page.props.status" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success" role="status" aria-live="polite">
                {{ page.props.flash?.status || page.props.status }}
            </div>

            <div v-if="confirmForm.errors.code || backendError" class="mt-4 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error" role="status" aria-live="polite">
                {{ confirmForm.errors.code || backendError }}
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="code" value="Code" />
                    <TextInput
                        id="code"
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        class="mt-1 block w-full text-center text-2xl tracking-widest"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        :disabled="isSubmitting"
                        @input="confirmForm.clearErrors('code')"
                    />
                    <InputError class="mt-2" :message="confirmForm.errors.code" />
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <PrimaryButton
                        type="submit"
                        :disabled="isSubmitting"
                        :class="{ 'opacity-60': isSubmitting }"
                        :aria-busy="isSubmitting"
                    >
                        Zugang oeffnen
                    </PrimaryButton>

                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary transition hover:bg-inputBg disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isResending || isSubmitting"
                        :aria-busy="isResending"
                        @click="resendCode"
                    >
                        Code erneut senden
                    </button>
                </div>
            </form>

            <div class="mt-5 text-sm text-secondary">
                <Link :href="route('guardian-access.create')" class="underline hover:text-primary">Andere E-Mail verwenden</Link>
            </div>
        </div>
    </AuthenticationCard>
</template>
