<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    guardianEmail: { type: String, default: '' },
    requestedAt: { type: String, default: null },
    rejectedAt: { type: String, default: null },
    approvedAt: { type: String, default: null },
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
                Eltern koennen den Elternbereich nutzen, um später Zustimmungen zu pruefen oder zu widerrufen:
                <Link :href="route('guardian-access.create')" class="font-semibold text-primary underline">
                    Elternbereich oeffnen
                </Link>
            </div>

            <PrimaryButton class="mt-6" @click="logout">
                Abmelden
            </PrimaryButton>
        </div>
    </AuthenticationCard>
</template>
