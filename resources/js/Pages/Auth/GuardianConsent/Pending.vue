<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
    guardianEmail: { type: String, default: '' },
    requestedAt: { type: String, default: null },
    rejectedAt: { type: String, default: null },
    approvedAt: { type: String, default: null },
    resendAvailableIn: { type: Number, default: 0 },
})

const page = usePage()
const resendForm = useForm({})
const wholeSeconds = (value) => Math.max(0, Math.ceil(Number(value) || 0))
const resendCooldown = ref(wholeSeconds(props.resendAvailableIn))
let resendTimer = null

const resendDisabled = computed(() => resendForm.processing || resendCooldown.value > 0 || Boolean(props.approvedAt))
const resendLabel = computed(() => {
    if (resendForm.processing) return 'E-Mail wird gesendet...'
    if (resendCooldown.value > 0) return `Erneut senden in ${resendCooldown.value}s`

    return 'E-Mail erneut senden'
})

const formatDateTime = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const logout = () => {
    router.post(route('logout'))
}

const resendGuardianEmail = () => {
    if (resendDisabled.value) return

    resendForm.post(route('guardian-consent.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            resendCooldown.value = 60
        },
    })
}

watch(() => props.resendAvailableIn, (value) => {
    resendCooldown.value = wholeSeconds(value)
})

onMounted(() => {
    document.body.style.overflow = null
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close())

    resendTimer = window.setInterval(() => {
        if (resendCooldown.value > 0) {
            resendCooldown.value -= 1
        }
    }, 1000)
})

onUnmounted(() => {
    if (resendTimer) {
        window.clearInterval(resendTimer)
    }
})
</script>

<template>
    <Head title="Zustimmung erforderlich" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="rounded-lg border border-border bg-card p-6 text-center">
            <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg"
                :class="rejectedAt ? 'bg-error/10 text-error' : 'bg-inputBg text-primary'"
            >
                <i :class="rejectedAt ? 'las la-times-circle text-3xl' : 'las la-envelope-open-text text-3xl'"></i>
            </div>

            <h1 class="mt-4 text-xl font-semibold text-primary">
                {{ rejectedAt ? 'Zustimmung wurde abgelehnt' : 'Zustimmung der Eltern ausstehend' }}
            </h1>

            <p v-if="!rejectedAt" class="mt-3 text-sm leading-6 text-secondary">
                Du kannst dich anmelden, aber das soziale Netzwerk bleibt gesperrt, bis dein Elternteil oder
                Erziehungsberechtigter zugestimmt hat.
            </p>

            <p v-else class="mt-3 text-sm leading-6 text-secondary">
                Dein Elternteil oder Erziehungsberechtigter hat die Registrierung abgelehnt.
                Die sozialen Funktionen bleiben deshalb gesperrt.
            </p>

            <div class="mt-5 rounded-lg border border-border bg-inputBg p-4 text-left text-sm text-secondary">
                <p v-if="guardianEmail">
                    E-Mail gesendet an:
                    <span class="font-semibold text-primary">{{ guardianEmail }}</span>
                </p>
                <p v-if="requestedAt" class="mt-1">
                    Gesendet am:
                    <span class="font-semibold text-primary">{{ formatDateTime(requestedAt) }}</span>
                </p>
                <p v-if="rejectedAt" class="mt-1">
                    Abgelehnt am:
                    <span class="font-semibold text-primary">{{ formatDateTime(rejectedAt) }}</span>
                </p>
                <p v-if="approvedAt" class="mt-1">
                    Bestaetigt am:
                    <span class="font-semibold text-primary">{{ formatDateTime(approvedAt) }}</span>
                </p>
            </div>

            <div class="mt-4 rounded-lg border border-border bg-inputBg p-4 text-left text-sm text-secondary">
                Eltern können den Elternbereich nutzen, um später Zustimmungen zu prüfen oder zu widerrufen:
                <Link :href="route('guardian-access.create')" class="font-semibold text-primary underline">
                    Elternbereich öffnen
                </Link>
            </div>

            <div v-if="!approvedAt" class="mt-5 space-y-2">
                <PrimaryButton
                    type="button"
                    class="w-full justify-center"
                    :disabled="resendDisabled"
                    @click="resendGuardianEmail"
                >
                    {{ resendLabel }}
                </PrimaryButton>

                <p v-if="page.props.errors?.resend" class="text-sm text-error">
                    {{ page.props.errors.resend }}
                </p>
                <p v-else class="text-xs text-secondary">
                    Du kannst die E-Mail einmal pro Minute erneut senden.
                </p>
            </div>

            <PrimaryButton class="mt-4" @click="logout">
                Abmelden
            </PrimaryButton>
        </div>
    </AuthenticationCard>
</template>
