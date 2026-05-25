<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import ConfirmActionModal from '@/Components/ConfirmActionModal.vue'
import { computed, ref } from 'vue'

defineProps({
    email: {
        type: String,
        required: true,
    },
    children: {
        type: Array,
        default: () => [],
    },
    hasAuthenticatedAccount: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()
const pendingAction = ref(null)
const processingAction = ref(false)
const actionError = ref('')
const backendActionError = computed(() => page.props.errors?.code || page.props.errors?.message || '')

const confirmation = computed(() => {
    if (!pendingAction.value) {
        return null
    }

    const { type, child } = pendingAction.value

    if (type === 'revoke') {
        return {
            title: 'Zustimmung widerrufen',
            message: `Moechtest du die Zustimmung für ${child.name} wirklich widerrufen? Die sozialen Funktionen werden danach wieder gesperrt.`,
            confirmLabel: 'Zustimmung widerrufen',
            danger: true,
        }
    }

    if (child.revoked_at) {
        return {
            title: 'Zustimmung erneut erteilen',
            message: `Moechtest du die Zustimmung für ${child.name} erneut erteilen? Das Konto wird danach wieder freigegeben.`,
            confirmLabel: 'Erneut zustimmen',
            danger: false,
        }
    }

    return {
        title: 'Ablehnung zurücknehmen',
        message: `Moechtest du die Ablehnung für ${child.name} zurücknehmen und die Zustimmung erteilen? Das Konto wird danach freigegeben.`,
        confirmLabel: 'Zurücknehmen und zustimmen',
        danger: false,
    }
})

const formatDate = (value) => {
    if (!value) {
        return '-'
    }

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const statusBadge = (child) => {
    if (child.approved_at) {
        return {
            text: `Zugestimmt am ${formatDate(child.approved_at)}`,
            className: 'rounded border border-success/30 bg-success/10 px-2 py-1 text-success',
        }
    }

    if (child.revoked_at) {
        return {
            text: `Widerrufen am ${formatDate(child.revoked_at)}`,
            className: 'rounded border border-warning/30 bg-warning/10 px-2 py-1 text-warning',
        }
    }

    if (child.rejected_at) {
        return {
            text: `Abgelehnt am ${formatDate(child.rejected_at)}`,
            className: 'rounded border border-error/30 bg-error/10 px-2 py-1 text-error',
        }
    }

    return {
        text: 'Zustimmung offen',
        className: 'rounded border border-border bg-muted px-2 py-1 text-secondary',
    }
}

const closeConfirmation = () => {
    if (!processingAction.value) {
        pendingAction.value = null
    }
}

const openConfirmation = (type, child) => {
    if (processingAction.value) {
        return
    }

    actionError.value = ''
    pendingAction.value = { type, child }
}

const confirmAction = () => {
    if (!pendingAction.value || processingAction.value) {
        return
    }

    processingAction.value = true
    actionError.value = ''

    const { type, child } = pendingAction.value
    const routeName = type === 'revoke'
        ? 'guardian-access.children.revoke'
        : 'guardian-access.children.approve'

    router.put(route(routeName, child.id), {}, {
        preserveScroll: true,
        onError: (errors) => {
            actionError.value = errors?.code || errors?.message || 'Die Aktion konnte nicht ausgeführt werden. Bitte versuche es erneut.'
        },
        onFinish: () => {
            processingAction.value = false
        },
        onSuccess: () => {
            closeConfirmation()
        },
    })
}

const logout = () => {
    router.post(route('guardian-access.destroy'))
}
</script>

<template>
    <Head title="Elternbereich" />

    <main class="min-h-screen bg-bg text-primary">
        <div class="mx-auto w-full max-w-5xl px-4 py-8">
            <header class="flex flex-col gap-4 rounded-lg border border-border bg-card p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-16 w-16">
                        <AuthenticationCardLogo />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Airmius Elternbereich</p>
                        <h1 class="text-2xl font-semibold text-primary">Zustimmungen verwalten</h1>
                        <p class="mt-1 text-sm text-secondary">{{ email }}</p>
                    </div>
                </div>

                <button class="btn" @click="logout" aria-label="Elternbereich schließen">Schließen</button>
            </header>

            <div v-if="page.props.flash?.success" class="mt-5 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>

            <div v-if="actionError || backendActionError" class="mt-5 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error" role="status" aria-live="polite">
                {{ actionError || backendActionError }}
            </div>

            <section v-if="!hasAuthenticatedAccount" class="mt-5 rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Eigenes Elternkonto nutzen</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Du kannst optional ein normales Airmius-Konto mit dieser E-Mail erstellen.
                            Danach sind die Kinder dauerhaft mit deinem Elternkonto verknüpft.
                        </p>
                    </div>

                    <Link :href="route('guardian-access.account.create')" class="btn-primary text-center">
                        Elternkonto erstellen
                    </Link>
                </div>
            </section>

            <section class="mt-5 space-y-4">
                <article
                    v-for="child in children"
                    :key="child.id"
                    class="rounded-lg border border-border bg-card p-5"
                >
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ child.name }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ child.email }} &middot; {{ child.age || 0 }} Jahre &middot; Geburtstag {{ formatDate(child.birth_date) }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span :class="statusBadge(child).className">
                                    {{ statusBadge(child).text }}
                                </span>
                            </div>
                        </div>

                        <button
                            v-if="child.approved_at && !child.revoked_at"
                            type="button"
                            class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                            :disabled="processingAction"
                            :aria-label="`Zustimmung für ${child.name} widerrufen`"
                            @click="openConfirmation('revoke', child)"
                        >
                            Zustimmung widerrufen
                        </button>
                        <button
                            v-else-if="child.rejected_at || child.revoked_at"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            :disabled="processingAction"
                            :aria-label="`Zustimmung für ${child.name} erneut erteilen`"
                            @click="openConfirmation('approve', child)"
                        >
                            {{ child.rejected_at ? 'Ablehnung zurücknehmen und zustimmen' : 'Zustimmung erneut erteilen' }}
                        </button>
                    </div>
                </article>

                <div v-if="children.length === 0" class="rounded-lg border border-border bg-card p-8 text-center text-secondary">
                    Zu dieser E-Mail wurden keine Kinder unter 16 Jahren gefunden.
                </div>
            </section>

            <div class="mt-6 text-sm text-secondary">
                <Link :href="route('welcome')" class="underline hover:text-primary">Zur Startseite</Link>
            </div>
        </div>

        <ConfirmActionModal
            :show="Boolean(confirmation)"
            :title="confirmation?.title"
            :message="confirmation?.message"
            :confirm-label="confirmation?.confirmLabel"
            :danger="confirmation?.danger"
            :processing="processingAction"
            @cancel="closeConfirmation"
            @confirm="confirmAction"
        />
    </main>
</template>
