<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useTheme } from '@/services/useTheme'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'

// Jetstream Components
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue'
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue'
import SectionBorder from '@/Components/SectionBorder.vue'
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue'

defineOptions({ layout: AppLayout })

// Props
const props = defineProps({
    profileAddress: {
        type: Object,
        default: () => ({}),
    },
    privacySettings: {
        type: Object,
        default: () => ({}),
    },
    eventDefaults: {
        type: Object,
        default: () => ({ radius_km: null, sport_ids: [], filters: {} }),
    },
    sports: {
        type: Array,
        default: () => [],
    },
    confirmsTwoFactorAuthentication: Boolean,
    billingHistory: {
        type: Object,
        default: () => ({ invoices: [], payments: [], subscription_invoices: [], airmius_bank: {} }),
    },
    currentUserSubscriptions: {
        type: Array,
        default: () => [],
    },
    socialAccounts: {
        type: Array,
        default: () => [],
    },
    sportIntegrations: {
        type: Object,
        default: () => ({ providers: {}, accounts: [], activities: [] }),
    },
    userRoles: {
        type: Array,
        default: () => [],
    },
    activities: {
        type: Array,
        default: () => [],
    },
    sessions: {
        type: Array,
        default: () => [], // FIX gegen undefined
    },
})

// Tabs
const activeTab = ref('profile')
const openPaymentModal = ref({
    show: false,
    action: null,
    invoice: null,
})
const disconnectIntegrationModal = ref({
    show: false,
    account: null,
})
const sportActivityDeleteModal = ref({
    show: false,
    activity: null,
    mode: null,
})
const sportActivityEditModal = ref({
    show: false,
    activity: null,
})
const sportActivityEditForm = useForm({
    title: '',
})
const manualActivityImageInput = ref(null)
const manualActivityForm = useForm({
    title: '',
    activity_type: 'Training',
    started_at: '',
    duration_minutes: '',
    distance_km: '',
    calories: '',
    image: null,
})
const bankTransferModal = ref({
    show: false,
    type: null,
    invoice: null,
})

const tabClass = (tab) =>
    `px-4 py-2 rounded-lg text-sm font-semibold transition ${
        activeTab.value === tab
            ? 'bg-buttonPrimary text-buttonTextPrimary'
            : 'bg-muted text-secondary'
    }`

// Theme
const { setTheme } = useTheme()
const addressNotice = ref(null)
const themeOptions = [
    { key: 'air', label: 'Air', description: 'Klar, leicht und fokussiert.', colors: ['#0ea5e9', '#10b981', '#f7fbff'] },
    { key: 'dark', label: 'Dark', description: 'Konzentriert für spaete Sessions.', colors: ['#0c1016', '#60a5fa', '#34d399'] },
    { key: 'womanly', label: 'Womanly', description: 'Warm, stark und elegant.', colors: ['#be185d', '#fde8f2', '#0f9f6e'] },
    { key: 'champion', label: 'Champion', description: 'Goldene Energie für Gewinner.', colors: ['#b45309', '#f59e0b', '#fffaf0'] },
    { key: 'sprint', label: 'Sprint', description: 'Frisch, schnell und aktiv.', colors: ['#059669', '#10b981', '#f5fff9'] },
    { key: 'arena', label: 'Arena', description: 'Ruhig, robust und professionell.', colors: ['#334155', '#64748b', '#f8fafc'] },
    { key: 'pulse', label: 'Pulse', description: 'Dynamisch und motivierend.', colors: ['#ea580c', '#f97316', '#fff7ed'] },
    { key: 'trail', label: 'Trail', description: 'Natuerlich, ausdauernd und bodenstaendig.', colors: ['#4d7c0f', '#65a30d', '#f6f8f2'] },
    { key: 'bazaar', label: 'Bazaar Rush', description: 'Lebendig, verkaufsstark und frisch für Marketplace-Flows.', colors: ['#00a8c6', '#ff8a00', '#ffffff'] },
]

// Form
const form = useForm({
    theme: '',
    country: props.profileAddress.country || 'DE',
    street: props.profileAddress.street || '',
    house_number: props.profileAddress.house_number || '',
    postal_code: props.profileAddress.postal_code || '',
    city: props.profileAddress.city || '',
    state: props.profileAddress.state || '',
    event_radius_km: props.eventDefaults.radius_km || 20,
    event_default_sport_ids: props.eventDefaults.sport_ids || [],
    profile_visibility: props.privacySettings.profile_visibility || 'public',
    direct_message_privacy: props.privacySettings.direct_message_privacy || 'everyone',
    friend_request_privacy: props.privacySettings.friend_request_privacy || 'everyone',
})

// Actions
const saveAddress = (showFeedback = true) => {
    if (showFeedback) {
        addressNotice.value = null
    }

    form.put(route('auth.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            if (showFeedback) {
                addressNotice.value = {
                    type: 'success',
                    message: 'Adresse wurde erfolgreich gespeichert.',
                }
            }
        },
        onError: () => {
            if (showFeedback) {
                addressNotice.value = {
                    type: 'error',
                    message: 'Adresse konnte nicht gespeichert werden. Bitte prüfe die Eingaben.',
                }
            }
        },
    })
}

const updateTheme = (theme) => {
    setTheme(theme)
    form.theme = theme
    saveAddress(false)
}

const toggleDefaultSport = (sportId) => {
    const id = Number(sportId)
    const selected = (form.event_default_sport_ids || []).map(Number)

    form.event_default_sport_ids = selected.includes(id)
        ? selected.filter((value) => value !== id)
        : [...selected, id]
}

const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatTime = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

const invoiceStatusLabel = (status) => ({
    open: 'Offen',
    awaiting_transfer: 'Warte auf Überweisung',
    paid: 'Bezahlt',
    overdue: 'Überfällig',
    cancelled: 'Storniert',
    active: 'Aktiv',
    trialing: 'Testphase',
    past_due: 'Zahlung offen',
    cancels_at_period_end: 'Gekündigt zum Periodenende',
}[status] || status)

const isPayableClubInvoice = (invoice) => ['open', 'overdue', 'awaiting_transfer'].includes(invoice.status)
const isOpenSubscriptionPayment = (invoice) => Boolean(invoice.payment_checkout_id)
    && ['open', 'overdue', 'awaiting_transfer'].includes(invoice.status)
    && ['pending', 'awaiting_transfer'].includes(invoice.checkout?.status)
const canDeleteOpenSubscriptionPayment = (invoice) => Boolean(invoice.payment_checkout_id)
    && invoice.status !== 'paid'
    && !invoice.paid_at
    && ['pending', 'awaiting_transfer', 'cancelled'].includes(invoice.checkout?.status)

const formatIban = (value) => String(value || '')
    .replace(/\s+/g, '')
    .replace(/(.{4})/g, '$1 ')
    .trim()

const paymentReference = (invoice) => invoice.payment_reference || invoice.number || `Rechnung ${invoice.id}`

const openBankTransferModal = (type, invoice) => {
    bankTransferModal.value = {
        show: true,
        type,
        invoice,
    }
}

const closeBankTransferModal = () => {
    bankTransferModal.value = {
        show: false,
        type: null,
        invoice: null,
    }
}

const hasAirmiusBank = () => Boolean(props.billingHistory.airmius_bank?.iban)
const hasClubBank = (invoice) => Boolean(invoice.club?.sepa_iban)

const bankTransferRows = () => {
    const invoice = bankTransferModal.value.invoice
    if (!invoice) return []

    if (bankTransferModal.value.type === 'airmius') {
        const bank = props.billingHistory.airmius_bank || {}

        return [
            ['Empfaenger', 'Airmius'],
            ['Kontoinhaber', bank.bank_account_holder || 'Airmius'],
            ...(bank.bank_name ? [['Bank', bank.bank_name]] : []),
            ['IBAN', formatIban(bank.iban)],
            ...(bank.bic ? [['BIC', bank.bic]] : []),
            ['Betrag', formatMoney(Number(invoice.amount_cents || 0) / 100)],
            ['Verwendungszweck', paymentReference(invoice)],
        ]
    }

    const club = invoice.club || {}

    return [
        ['Empfaenger', club.name || '-'],
        ['Kontoinhaber', club.sepa_account_holder || club.name || '-'],
        ['IBAN', formatIban(club.sepa_iban)],
        ...(club.sepa_bic ? [['BIC', club.sepa_bic]] : []),
        ['Betrag', formatMoney(invoice.amount)],
        ['Verwendungszweck', paymentReference(invoice)],
    ]
}

const openPaymentActionModal = (action, invoice) => {
    openPaymentModal.value = {
        show: true,
        action,
        invoice,
    }
}

const closeOpenPaymentModal = () => {
    openPaymentModal.value = {
        show: false,
        action: null,
        invoice: null,
    }
}

const openPaymentModalTitle = () => openPaymentModal.value.action === 'delete'
    ? 'Offene Zahlung loeschen'
    : 'Offene Zahlung abbrechen'

const openPaymentModalMessage = () => {
    const invoice = openPaymentModal.value.invoice
    const number = invoice?.number ? ` ${invoice.number}` : ''

    if (openPaymentModal.value.action === 'delete') {
        return `Die offene Zahlung${number} wird dauerhaft geloescht. Das ist nur fuer unbezahlte, nicht aktivierte Zahlungen moeglich.`
    }

    return `Die offene Zahlung${number} wird abgebrochen und als storniert markiert.`
}

const openPaymentModalConfirmText = () => openPaymentModal.value.action === 'delete'
    ? 'delete'
    : 'abbrechen'

const confirmOpenPaymentAction = () => {
    const invoice = openPaymentModal.value.invoice
    const action = openPaymentModal.value.action

    if (!invoice) return

    if (action === 'delete') {
        deleteOpenSubscriptionPayment(invoice)
        return
    }

    cancelOpenSubscriptionPayment(invoice)
}

const cancelOpenSubscriptionPayment = (invoice) => {
    if (!isOpenSubscriptionPayment(invoice)) return

    router.post(route('auth.settings.subscription-invoices.cancel-open-payment', invoice.id), {}, {
        preserveScroll: true,
        onFinish: closeOpenPaymentModal,
    })
}

const deleteOpenSubscriptionPayment = (invoice) => {
    if (!canDeleteOpenSubscriptionPayment(invoice)) return

    router.delete(route('auth.settings.subscription-invoices.destroy-open-payment', invoice.id), {
        preserveScroll: true,
        onFinish: closeOpenPaymentModal,
    })
}

const cancelSubscription = (subscription) => {
    router.post(route('auth.user-subscriptions.cancel', subscription.id), {}, { preserveScroll: true })
}

const openProviderPortal = (subscription) => {
    router.post(route('auth.user-subscriptions.provider-portal', subscription.id), {}, { preserveScroll: true })
}

const socialAccountFor = (provider) =>
    props.socialAccounts.find((account) => account.provider === provider)

const connectedAccountFor = (provider) =>
    props.sportIntegrations.accounts.find((account) => account.provider === provider)

const integrationStatusLabel = (status) => ({
    connected: 'Verbunden',
    requested: 'Vorgemerkt',
    disconnected: 'Getrennt',
    error: 'Fehler',
}[status] || status)

const syncIntegration = (account) => {
    router.post(route('auth.sport-integrations.sync', account.id), {}, { preserveScroll: true })
}

const openDisconnectIntegrationModal = (account) => {
    disconnectIntegrationModal.value = {
        show: true,
        account,
    }
}

const closeDisconnectIntegrationModal = () => {
    disconnectIntegrationModal.value = {
        show: false,
        account: null,
    }
}

const disconnectIntegration = (account) => {
    router.delete(route('auth.sport-integrations.destroy', account.id), {
        preserveScroll: true,
        onFinish: closeDisconnectIntegrationModal,
    })
}

const openSportActivityDeleteModal = (activity = null) => {
    sportActivityDeleteModal.value = {
        show: true,
        activity,
        mode: activity ? 'single' : 'all',
    }
}

const closeSportActivityDeleteModal = () => {
    sportActivityDeleteModal.value = {
        show: false,
        activity: null,
        mode: null,
    }
}

const sportActivityDeleteTitle = () => sportActivityDeleteModal.value.mode === 'all'
    ? 'Alle importierten Aktivitaeten loeschen'
    : 'Importierte Aktivitaet loeschen'

const sportActivityDeleteMessage = () => sportActivityDeleteModal.value.mode === 'all'
    ? 'Alle importierten Sportaktivitaeten werden dauerhaft aus deinem Airmius Konto geloescht. Die Verbindung zu Google Fit oder anderen Apps bleibt bestehen.'
    : 'Diese importierte Sportaktivitaet wird dauerhaft aus deinem Airmius Konto geloescht.'

const confirmSportActivityDelete = () => {
    if (sportActivityDeleteModal.value.mode === 'all') {
        router.delete(route('auth.sport-activities.destroy-all'), {
            preserveScroll: true,
            onFinish: closeSportActivityDeleteModal,
        })

        return
    }

    const activity = sportActivityDeleteModal.value.activity
    if (!activity) return

    router.delete(route('auth.sport-activities.destroy', activity.id), {
        preserveScroll: true,
        onFinish: closeSportActivityDeleteModal,
    })
}

const openSportActivityEditModal = (activity) => {
    sportActivityEditModal.value = {
        show: true,
        activity,
    }
    sportActivityEditForm.title = activity.title || activity.activity_type || ''
    sportActivityEditForm.clearErrors()
}

const closeSportActivityEditModal = () => {
    sportActivityEditModal.value = {
        show: false,
        activity: null,
    }
    sportActivityEditForm.reset()
    sportActivityEditForm.clearErrors()
}

const updateSportActivityTitle = () => {
    const activity = sportActivityEditModal.value.activity
    if (!activity) return

    sportActivityEditForm.put(route('auth.sport-activities.update', activity.id), {
        preserveScroll: true,
        onSuccess: closeSportActivityEditModal,
    })
}

const storeManualActivity = () => {
    manualActivityForm.post(route('auth.sport-activities.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            manualActivityForm.reset()
            manualActivityForm.activity_type = 'Training'
            if (manualActivityImageInput.value) {
                manualActivityImageInput.value.value = ''
            }
        },
    })
}

const setManualActivityImage = (event) => {
    manualActivityForm.image = event.target.files?.[0] || null
}

const formatDuration = (seconds) => {
    if (!seconds) return '-'
    const minutes = Math.round(seconds / 60)
    if (minutes < 60) return `${minutes} min`

    const hours = Math.floor(minutes / 60)
    const rest = minutes % 60

    return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`
}

const formatDistance = (meters) => {
    if (!meters) return '-'
    return `${(meters / 1000).toFixed(2).replace('.', ',')} km`
}

const formatProvider = (provider) => ({
    manual: 'Manuell',
    google_fit: 'Google Fit',
    strava: 'Strava',
    garmin: 'Garmin',
    mi_fitness: 'Mi Fitness',
    fitbit: 'Fitbit',
    polar: 'Polar',
}[provider] || provider)

const sportActivityTitle = (activity) => {
    if (activity.title && activity.title !== 'Google Fit Tagesaktivitaet') {
        return activity.title
    }

    return activity.activity_type || 'Tagesaktivitaet'
}

const sportActivitySubtitle = (activity) => {
    if (activity.metrics?.source_kind === 'manual_entry') {
        return 'Manuell eingetragen'
    }

    if (activity.metrics?.source_kind === 'daily_summary') {
        const parts = ['Tageszusammenfassung']
        if (activity.metrics?.active_minutes) {
            parts.push(`${activity.metrics.active_minutes} aktive Minuten`)
        }

        return parts.join(' · ')
    }

    return activity.metrics?.earliest_start_time
        ? `Start ca. ${activity.metrics.earliest_start_time}`
        : ''
}

const sportActivityTime = (activity) => {
    if (activity.metrics?.earliest_start_time) {
        return activity.metrics.earliest_start_time
    }

    return activity.metrics?.source_kind === 'daily_summary' ? '-' : formatTime(activity.started_at)
}

const activityLabel = (type) => ({
    'post.created': 'Beitrag erstellt',
    'post.updated': 'Beitrag aktualisiert',
    'post.deleted': 'Beitrag geloescht',
    'user.followed': 'Person gefolgt',
    'friend.requested': 'Freundschaftsanfrage gesendet',
    'friend.accepted': 'Freundschaft akzeptiert',
    'comment.created': 'Kommentar geschrieben',
    'comment.updated': 'Kommentar bearbeitet',
    'comment.deleted': 'Kommentar geloescht',
}[type] || type)

const activityScope = (activity) => activity.team?.name || activity.club?.name || 'Persoenlich'

const activityDescription = (activity) => activity.data?.title || activity.data?.content || activity.data?.message || ''
</script>

<template>
    <Head title="Einstellungen" />

    <div class="space-y-5">

        <!-- HEADER -->
        <div class="surface-card p-5">
            <h1 class="text-xl font-semibold text-primary">Einstellungen</h1>
            <p class="mt-1 text-sm text-secondary">
                Profil, Sicherheit, Design und Adresse verwalten.
            </p>
        </div>

        <div
            v-if="$page.props.flash?.success || $page.props.flash?.error"
            class="rounded-lg border px-4 py-3 text-sm font-semibold"
            :class="$page.props.flash?.success
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
        >
            {{ $page.props.flash?.success || $page.props.flash?.error }}
        </div>

        <!-- TABS -->
        <div class="surface-card p-3 flex flex-wrap gap-2">
            <button @click="activeTab = 'profile'" :class="tabClass('profile')">Profil</button>
            <button @click="activeTab = 'address'" :class="tabClass('address')">Adresse</button>
            <button @click="activeTab = 'billing'" :class="tabClass('billing')">Zahlungen</button>
            <button @click="activeTab = 'roles'" :class="tabClass('roles')">Rollen</button>
            <button @click="activeTab = 'activities'" :class="tabClass('activities')">Aktivitaeten</button>
            <button @click="activeTab = 'integrations'" :class="tabClass('integrations')">Verknüpfungen</button>
            <button @click="activeTab = 'design'" :class="tabClass('design')">Design</button>
            <button @click="activeTab = 'language'" :class="tabClass('language')">Sprache</button>
            <button @click="activeTab = 'privacy'" :class="tabClass('privacy')">Privatsphäre</button>
            <button @click="activeTab = 'security'" :class="tabClass('security')">Sicherheit</button>
        </div>

        <!-- PROFIL -->
        <div v-if="activeTab === 'profile'" class="surface-card p-5 space-y-6">
            <UpdateProfileInformationForm :user="$page.props.auth.user" />
        </div>

        <!-- ROLLEN -->
        <div v-if="activeTab === 'roles'" class="surface-card p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Meine Rollen</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Hier siehst du, welche Plattform-Rollen deinem Konto aktuell zugeordnet sind.
                    </p>
                </div>
                <span class="text-sm font-semibold text-secondary">{{ userRoles.length }} Rollen</span>
            </div>

            <div v-if="userRoles.length" class="mt-5 grid gap-3 md:grid-cols-2">
                <article
                    v-for="role in userRoles"
                    :key="role.id"
                    class="rounded-lg border border-border bg-bg p-4"
                >
                    <div>
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-primary">{{ role.name }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ role.description || 'Keine Beschreibung vorhanden.' }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
                Deinem Konto ist noch keine Rolle zugewiesen.
            </div>
        </div>

        <!-- AKTIVITAETEN -->
        <div v-if="activeTab === 'activities'" class="surface-card p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Meine Aktivitaeten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Hier erscheinen nur Aktionen, die von deinem eigenen Konto erstellt wurden.
                    </p>
                </div>
                <span class="text-sm font-semibold text-secondary">{{ activities.length }} Einträge</span>
            </div>

            <div v-if="activities.length" class="mt-5 divide-y divide-border rounded-lg border border-border bg-bg">
                <article
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex gap-3 p-4"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                        <i class="las la-history text-lg"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-semibold text-primary">{{ activityLabel(activity.type) }}</p>
                            <time class="text-xs text-secondary">{{ formatDate(activity.created_at) }}</time>
                        </div>
                        <p class="mt-1 text-sm text-secondary">{{ activityScope(activity) }}</p>
                        <p v-if="activityDescription(activity)" class="mt-2 line-clamp-2 text-sm text-primary">
                            {{ activityDescription(activity) }}
                        </p>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
                Noch keine eigenen Aktivitaeten vorhanden.
            </div>
        </div>

        <!-- SICHERHEIT -->
        <div v-if="activeTab === 'security'" class="surface-card p-5 space-y-6">

            <UpdatePasswordForm />

            <SectionBorder />

            <TwoFactorAuthenticationForm
                :requires-confirmation="confirmsTwoFactorAuthentication"
            />

            <SectionBorder />

            <LogoutOtherBrowserSessionsForm :sessions="sessions || []" />

            <SectionBorder />

            <DeleteUserForm />

        </div>

        <!-- DESIGN -->
        <div v-if="activeTab === 'design'" class="surface-card p-5">
            <h2 class="text-sm font-semibold text-secondary mb-3">Design</h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="themeOption in themeOptions"
                    :key="themeOption.key"
                    type="button"
                    class="rounded-lg border border-border bg-bg p-4 text-left transition hover:border-borderHover hover:bg-muted"
                    @click="updateTheme(themeOption.key)"
                >
                    <span class="flex items-center gap-2">
                        <span
                            v-for="color in themeOption.colors"
                            :key="color"
                            class="h-5 w-5 rounded-full border border-border"
                            :style="{ backgroundColor: color }"
                        ></span>
                    </span>
                    <span class="mt-3 block font-semibold text-primary">{{ themeOption.label }}</span>
                    <span class="mt-1 block text-xs text-secondary">{{ themeOption.description }}</span>
                </button>
            </div>

        </div>

        <!-- SPRACHE -->
        <div v-if="activeTab === 'language'" class="surface-card relative z-20 overflow-visible p-5">
            <h2 class="text-sm font-semibold text-secondary mb-3">Sprache</h2>
            <p class="mb-3 text-sm text-secondary">
                Wähle die Sprache für Navigation, Seiten und Bedienelemente.
            </p>
            <LanguageDropdown align="start" />
        </div>

        <!-- ADRESSE -->
        <div v-if="activeTab === 'address'" class="surface-card p-5">

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAddress">

                <div class="md:col-span-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                        Adresse
                    </h2>
                </div>

                <div
                    v-if="addressNotice"
                    class="md:col-span-2 rounded-lg border px-4 py-3 text-sm"
                    :class="addressNotice.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ addressNotice.message }}
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">
                        Land <span class="text-error">*</span>
                    </label>
                    <select v-model="form.country" required class="input">
                        <option value="DE">Deutschland</option>
                        <option value="AT">Österreich</option>
                        <option value="CH">Schweiz</option>
                        <option value="FR">Frankreich</option>
                        <option value="NL">Niederlande</option>
                        <option value="BE">Belgien</option>
                        <option value="TR">Türkei</option>
                        <option value="US">USA</option>
                    </select>
                    <p v-if="form.errors.country" class="mt-1 text-sm text-error">{{ form.errors.country }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Stadt</label>
                    <input v-model="form.city" class="input" />
                    <p v-if="form.errors.city" class="mt-1 text-sm text-error">{{ form.errors.city }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">PLZ</label>
                    <input v-model="form.postal_code" class="input" />
                    <p v-if="form.errors.postal_code" class="mt-1 text-sm text-error">{{ form.errors.postal_code }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Bundesland</label>
                    <input v-model="form.state" class="input" />
                    <p v-if="form.errors.state" class="mt-1 text-sm text-error">{{ form.errors.state }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Straße</label>
                    <input v-model="form.street" class="input" />
                    <p v-if="form.errors.street" class="mt-1 text-sm text-error">{{ form.errors.street }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Hausnummer</label>
                    <input v-model="form.house_number" class="input" />
                    <p v-if="form.errors.house_number" class="mt-1 text-sm text-error">{{ form.errors.house_number }}</p>
                </div>

                <div class="md:col-span-2 mt-4 border-t border-border pt-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                        Event-Defaults
                    </h2>
                    <p class="mt-1 text-sm text-secondary">
                        Diese Werte werden automatisch für deine Eventliste genutzt, solange du dort keine eigenen Filter setzt.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Eventzone</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            v-model="form.event_radius_km"
                            class="input"
                            min="1"
                            max="500"
                            type="number"
                        />
                        <span class="text-sm font-semibold text-secondary">km</span>
                    </div>
                    <p class="mt-1 text-xs text-secondary">
                        Aktuell adressbasiert ueber PLZ/Stadt/Vereinsadresse.
                    </p>
                    <p v-if="form.errors.event_radius_km" class="mt-1 text-sm text-error">{{ form.errors.event_radius_km }}</p>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold text-primary">Sportarten für Eventvorschlaege</label>
                    <div class="mt-2 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-border bg-bg p-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="sport in sports"
                            :key="sport.id"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-left text-sm transition"
                            :class="(form.event_default_sport_ids || []).map(Number).includes(Number(sport.id))
                                ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary'
                                : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'"
                            @click="toggleDefaultSport(sport.id)"
                        >
                            <span class="block font-semibold">{{ sport.name }}</span>
                            <span class="text-xs">{{ sport.category || 'Sport' }}</span>
                        </button>
                    </div>
                    <p v-if="form.errors.event_default_sport_ids" class="mt-1 text-sm text-error">{{ form.errors.event_default_sport_ids }}</p>
                </div>

                <div class="md:col-span-2">
                    <button class="btn-primary" :disabled="form.processing">
                        Adresse & Event-Defaults speichern
                    </button>
                </div>

            </form>
        </div>

        <!-- PRIVATSPHAERE -->
        <div v-if="activeTab === 'privacy'" class="surface-card p-5">
            <form class="space-y-5" @submit.prevent="saveAddress">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Privatsphäre</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Lege fest, wer dein Profil sehen, dich direkt kontaktieren oder dir Freundschaftsanfragen senden darf.
                    </p>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Profil-Sichtbarkeit</span>
                    <select v-model="form.profile_visibility" class="input">
                        <option value="public">Alle angemeldeten Personen</option>
                        <option value="private">Nur ich, Freunde und Follower</option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        Diese Einstellung steuert, ob andere dein Profil und deine Profilinhalte sehen können.
                    </p>
                    <p v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Nachrichten erhalten</span>
                    <select v-model="form.direct_message_privacy" class="input">
                        <option value="everyone">Alle angemeldeten Personen</option>
                        <option value="friends">Nur Freunde</option>
                    </select>
                    <p v-if="form.errors.direct_message_privacy" class="mt-1 text-sm text-error">{{ form.errors.direct_message_privacy }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Freundschaftsanfragen erhalten</span>
                    <select v-model="form.friend_request_privacy" class="input">
                        <option value="everyone">Alle angemeldeten Personen</option>
                        <option value="friends">Nur Freunde</option>
                    </select>
                    <p v-if="form.errors.friend_request_privacy" class="mt-1 text-sm text-error">{{ form.errors.friend_request_privacy }}</p>
                </label>

                <button class="btn-primary" :disabled="form.processing">
                    Privatsphaere speichern
                </button>
            </form>
        </div>

        <div v-if="activeTab === 'billing'" class="space-y-5">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Airmius Abos</h2>
                <p class="mt-1 text-sm text-secondary">
                    Aktuelle persönliche Airmius Pläne und Laufzeiten.
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="subscription in currentUserSubscriptions" :key="subscription.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">{{ subscription.plan?.name || 'Airmius Abo' }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ invoiceStatusLabel(subscription.status) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <button
                                    v-if="subscription.payment_provider === 'stripe'"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="openProviderPortal(subscription)"
                                >
                                    Zahlungsportal
                                </button>

                                <button
                                    v-if="!['cancelled', 'cancels_at_period_end'].includes(subscription.status)"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="cancelSubscription(subscription)"
                                >
                                    Kündigen
                                </button>
                            </div>
                        </div>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-secondary">Zahlungsart</dt>
                                <dd class="text-primary">{{ subscription.payment_provider || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-secondary">Läuft bis</dt>
                                <dd class="text-primary">{{ formatDate(subscription.current_period_ends_at || subscription.trial_ends_at) }}</dd>
                            </div>
                            <div v-if="subscription.cancels_at" class="flex justify-between gap-3">
                                <dt class="text-secondary">Gekündigt zum</dt>
                                <dd class="text-primary">{{ formatDate(subscription.cancels_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <p v-if="!currentUserSubscriptions.length" class="mt-4 text-sm text-secondary">
                    Du hast noch kein persönliches Airmius Abo.
                </p>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Airmius Abo-Rechnungen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Rechnungen für Airmius Pläne und Plattform-Abos.
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Nr.</th>
                                <th class="py-2 pr-4">Plan</th>
                                <th class="py-2 pr-4">Verein</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Fällig</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Zahlen</th>
                                <th class="py-2 pr-4 text-right">PDF</th>
                                <th class="py-2 pr-4 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in billingHistory.subscription_invoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.plan?.name || invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(Number(invoice.amount_cents || 0) / 100) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="isPayableClubInvoice(invoice) && hasAirmiusBank()"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                        @click="openBankTransferModal('airmius', invoice)"
                                    >
                                        Bankdaten
                                    </button>
                                    <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">Bankdaten fehlen</span>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    <a :href="route('auth.subscription-invoices.download', invoice.id)" download class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                        Download
                                    </a>
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    <div v-if="isOpenSubscriptionPayment(invoice) || canDeleteOpenSubscriptionPayment(invoice)" class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="isOpenSubscriptionPayment(invoice)"
                                            type="button"
                                            class="rounded-lg border border-warning px-3 py-1 text-xs font-semibold text-warning hover:bg-warning/10"
                                            @click="openPaymentActionModal('cancel', invoice)"
                                        >
                                            Abbrechen
                                        </button>
                                        <button
                                            v-if="canDeleteOpenSubscriptionPayment(invoice)"
                                            type="button"
                                            class="rounded-lg border border-error px-3 py-1 text-xs font-semibold text-error hover:bg-error/10"
                                            @click="openPaymentActionModal('delete', invoice)"
                                        >
                                            Loeschen
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.subscription_invoices.length" class="py-6 text-sm text-secondary">
                        Noch keine Airmius Abo-Rechnungen vorhanden.
                    </p>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Rechnungen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Hier siehst du offene und bezahlte Vereinsbeiträge.
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Nr.</th>
                                <th class="py-2 pr-4">Verein</th>
                                <th class="py-2 pr-4">Titel</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Fällig</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Zahlen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in billingHistory.invoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="isPayableClubInvoice(invoice) && hasClubBank(invoice)"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                        @click="openBankTransferModal('club', invoice)"
                                    >
                                        Bankdaten
                                    </button>
                                    <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">Bankdaten fehlen</span>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.invoices.length" class="py-6 text-sm text-secondary">
                        Noch keine Rechnungen vorhanden.
                    </p>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Zahlungshistorie</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Datum</th>
                                <th class="py-2 pr-4">Verein</th>
                                <th class="py-2 pr-4">Rechnung</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="payment in billingHistory.payments" :key="payment.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(payment.paid_at || payment.created_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ payment.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ payment.invoice?.number || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(payment.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ payment.status === 'paid' ? 'Bezahlt' : payment.status }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.payments.length" class="py-6 text-sm text-secondary">
                        Noch keine Zahlungen markiert.
                    </p>
                </div>
            </section>
        </div>

        <div v-if="activeTab === 'integrations'" class="space-y-5">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Login-Verknüpfungen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Nutze Google oder Outlook für eine schnelle Anmeldung.
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div
                        class="rounded-lg border p-4 transition"
                        :class="socialAccountFor('google') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Google</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccountFor('google')?.email || 'Noch nicht verbunden' }}
                                </p>
                            </div>
                            <span
                                v-if="socialAccountFor('google')"
                                class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                            >
                                Verbunden
                            </span>
                            <a v-else :href="route('social-auth.redirect', 'google')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                                Verbinden
                            </a>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border p-4 transition"
                        :class="socialAccountFor('microsoft') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Outlook / Microsoft</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccountFor('microsoft')?.email || 'Noch nicht verbunden' }}
                                </p>
                            </div>
                            <span
                                v-if="socialAccountFor('microsoft')"
                                class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                            >
                                Verbunden
                            </span>
                            <a v-else :href="route('social-auth.redirect', 'microsoft')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                                Verbinden
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Sportprogramme synchronisieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Verknüpfe Sport-Apps, damit Trainingsdaten später automatisch in dein Airmius Profil fließen können.
                </p>

                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="(provider, key) in sportIntegrations.providers"
                        :key="key"
                        class="rounded-lg border p-4 transition"
                        :class="connectedAccountFor(key) ? 'border-success/40 bg-bg' : 'border-border bg-bg'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-primary">{{ provider.label }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ provider.description }}</p>
                            </div>
                            <span
                                v-if="connectedAccountFor(key)"
                                class="shrink-0 whitespace-nowrap rounded-full border border-success/40 bg-success/15 px-2.5 py-1 text-xs font-semibold text-success"
                            >
                                {{ integrationStatusLabel(connectedAccountFor(key).status) }}
                            </span>
                        </div>

                        <p v-if="connectedAccountFor(key)?.last_synced_at" class="mt-3 text-xs text-secondary">
                            Zuletzt synchronisiert: {{ formatDate(connectedAccountFor(key).last_synced_at) }}
                        </p>
                        <p v-if="connectedAccountFor(key)?.sync_summary?.message" class="mt-2 text-xs text-secondary">
                            {{ connectedAccountFor(key).sync_summary.message }}
                        </p>
                        <dl v-if="connectedAccountFor(key)?.sync_summary?.google_status || connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="mt-2 space-y-1 text-xs text-secondary">
                            <div v-if="connectedAccountFor(key)?.sync_summary?.google_status" class="flex gap-2">
                                <dt>Google Status:</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_status }}</dd>
                            </div>
                            <div v-if="connectedAccountFor(key)?.sync_summary?.google_error" class="flex gap-2">
                                <dt>Google Fehler:</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_error }}</dd>
                            </div>
                            <div v-if="connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="flex gap-2">
                                <dt>Tagesbereiche:</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.bucket_count }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a
                                v-if="!connectedAccountFor(key)"
                                :href="route('auth.sport-integrations.connect', provider.route_key || key)"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                            >
                                {{ provider.status === 'live_oauth' ? 'Verbinden' : 'Vormerken' }}
                            </a>
                            <button
                                v-if="connectedAccountFor(key)"
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary"
                                @click="syncIntegration(connectedAccountFor(key))"
                            >
                                Sync prüfen
                            </button>
                            <button
                                v-if="connectedAccountFor(key)"
                                type="button"
                                class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                                @click="openDisconnectIntegrationModal(connectedAccountFor(key))"
                            >
                                Entfernen
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="surface-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-primary">Importierte Aktivitäten</h2>
                    <button
                        v-if="sportIntegrations.activities.length"
                        type="button"
                        class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                        @click="openSportActivityDeleteModal()"
                    >
                        Alle löschen
                    </button>
                </div>
                <form class="mt-5 rounded-xl border border-border bg-muted/30 p-4" @submit.prevent="storeManualActivity">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">Manuell eintragen</h3>
                            <p class="mt-1 text-sm text-secondary">
                                Fuege eigene Trainingseinheiten hinzu, auch wenn keine Sport-App verbunden ist.
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="manualActivityForm.processing"
                        >
                            Training speichern
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block text-sm font-semibold text-primary">
                            Name
                            <input
                                v-model="manualActivityForm.title"
                                type="text"
                                maxlength="120"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="z. B. Lauftraining"
                                required
                            />
                            <span v-if="manualActivityForm.errors.title" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.title }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Sportart
                            <select
                                v-model="manualActivityForm.activity_type"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            >
                                <option>Training</option>
                                <option>Laufen</option>
                                <option>Radfahren</option>
                                <option>Schwimmen</option>
                                <option>Fussball</option>
                                <option>Fitness</option>
                                <option>Krafttraining</option>
                                <option>Yoga</option>
                                <option>Gehen</option>
                                <option>Sonstiges</option>
                            </select>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Datum und Zeit
                            <input
                                v-model="manualActivityForm.started_at"
                                type="datetime-local"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                required
                            />
                            <span v-if="manualActivityForm.errors.started_at" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.started_at }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Bild
                            <input
                                ref="manualActivityImageInput"
                                type="file"
                                accept="image/*"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                                @change="setManualActivityImage"
                            />
                            <span v-if="manualActivityForm.errors.image" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.image }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Dauer in Minuten
                            <input
                                v-model="manualActivityForm.duration_minutes"
                                type="number"
                                min="0"
                                max="14400"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="60"
                            />
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Distanz in km
                            <input
                                v-model="manualActivityForm.distance_km"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="5,00"
                            />
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            Kalorien
                            <input
                                v-model="manualActivityForm.calories"
                                type="number"
                                min="0"
                                max="200000"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="450"
                            />
                        </label>
                    </div>
                </form>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Bild</th>
                                <th class="py-2 pr-4">Datum</th>
                                <th class="py-2 pr-4">Zeit</th>
                                <th class="py-2 pr-4">Quelle</th>
                                <th class="py-2 pr-4">Sportart</th>
                                <th class="py-2 pr-4">Dauer</th>
                                <th class="py-2 pr-4">Distanz</th>
                                <th class="py-2 pr-4">Kalorien</th>
                                <th class="py-2 pr-4 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="activity in sportIntegrations.activities" :key="activity.id">
                                <td class="py-3 pr-4">
                                    <img
                                        v-if="activity.image_url"
                                        :src="activity.image_url"
                                        alt=""
                                        class="h-12 w-12 rounded-lg border border-border object-cover"
                                    />
                                    <span v-else class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-border text-xs text-secondary">
                                        -
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(activity.started_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">
                                    {{ sportActivityTime(activity) }}
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatProvider(activity.provider) }}</td>
                                <td class="py-3 pr-4">
                                    <p class="font-semibold text-primary">{{ sportActivityTitle(activity) }}</p>
                                    <p v-if="sportActivitySubtitle(activity)" class="mt-1 text-xs text-secondary">
                                        {{ sportActivitySubtitle(activity) }}
                                    </p>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDuration(activity.duration_seconds) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDistance(activity.distance_meters) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ activity.calories || '-' }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="openSportActivityEditModal(activity)"
                                        >
                                            Umbenennen
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            @click="openSportActivityDeleteModal(activity)"
                                        >
                                            Löschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!sportIntegrations.activities.length" class="py-6 text-sm text-secondary">
                        Noch keine Aktivitäten importiert.
                    </p>
                </div>
            </section>
        </div>

        <DeleteConfirmModal
            :show="openPaymentModal.show"
            :title="openPaymentModalTitle()"
            :message="openPaymentModalMessage()"
            :confirm-text="openPaymentModalConfirmText()"
            cancel-text="Zurueck"
            @confirm="confirmOpenPaymentAction"
            @cancel="closeOpenPaymentModal"
        />

        <DeleteConfirmModal
            :show="disconnectIntegrationModal.show"
            title="Sport-App entfernen"
            message="Bist du sicher, dass du diese Sport-App-Verknuepfung entfernen moechtest? Gespeicherte Tokens werden geloescht und die App muss danach neu verbunden werden."
            confirm-text="entfernen"
            cancel-text="Abbrechen"
            @confirm="disconnectIntegration(disconnectIntegrationModal.account)"
            @cancel="closeDisconnectIntegrationModal"
        />

        <DeleteConfirmModal
            :show="sportActivityDeleteModal.show"
            :title="sportActivityDeleteTitle()"
            :message="sportActivityDeleteMessage()"
            confirm-text="delete"
            cancel-text="Zurueck"
            @confirm="confirmSportActivityDelete"
            @cancel="closeSportActivityDeleteModal"
        />

        <div
            v-if="sportActivityEditModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
            @click.self="closeSportActivityEditModal"
        >
            <form
                class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl"
                @submit.prevent="updateSportActivityTitle"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Aktivität umbenennen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Der neue Name wird nur in Airmius gespeichert.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeSportActivityEditModal"
                    >
                        Schliessen
                    </button>
                </div>

                <label class="mt-5 block text-sm font-semibold text-primary" for="sport-activity-title">
                    Name
                </label>
                <input
                    id="sport-activity-title"
                    v-model="sportActivityEditForm.title"
                    type="text"
                    maxlength="120"
                    class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                    required
                />
                <p v-if="sportActivityEditForm.errors.title" class="mt-2 text-sm text-danger">
                    {{ sportActivityEditForm.errors.title }}
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                        @click="closeSportActivityEditModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="sportActivityEditForm.processing"
                    >
                        Speichern
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="bankTransferModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
            @click.self="closeBankTransferModal"
        >
            <div class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Per Ueberweisung zahlen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Nutze diese Daten fuer deine Bankueberweisung.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeBankTransferModal"
                    >
                        Schliessen
                    </button>
                </div>

                <dl class="mt-5 divide-y divide-border rounded-lg border border-border bg-card">
                    <div
                        v-for="[label, value] in bankTransferRows()"
                        :key="label"
                        class="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[150px_1fr]"
                    >
                        <dt class="text-secondary">{{ label }}</dt>
                        <dd class="break-words font-semibold text-primary sm:text-right">{{ value }}</dd>
                    </div>
                </dl>
            </div>
        </div>

    </div>
</template>

<style scoped>
.input {
    @apply mt-1 w-full rounded-lg border-border bg-inputBg text-primary;
}

.btn {
    @apply rounded-lg border border-border bg-muted px-4 py-2 hover:border-borderHover;
}

.btn-primary {
    @apply rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary;
}
</style>
