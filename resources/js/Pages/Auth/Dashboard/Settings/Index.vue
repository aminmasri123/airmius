<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useTheme } from '@/services/useTheme'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'

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
    confirmsTwoFactorAuthentication: Boolean,
    billingHistory: {
        type: Object,
        default: () => ({ invoices: [], payments: [], subscription_invoices: [] }),
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
    sessions: {
        type: Array,
        default: () => [], // FIX gegen undefined
    },
})

// Tabs
const activeTab = ref('profile')

const tabClass = (tab) =>
    `px-4 py-2 rounded-lg text-sm font-semibold transition ${
        activeTab.value === tab
            ? 'bg-buttonPrimary text-buttonTextPrimary'
            : 'bg-muted text-secondary'
    }`

// Theme
const { setTheme } = useTheme()

// Form
const form = useForm({
    theme: '',
    country: props.profileAddress.country || 'DE',
    street: props.profileAddress.street || '',
    house_number: props.profileAddress.house_number || '',
    postal_code: props.profileAddress.postal_code || '',
    city: props.profileAddress.city || '',
    state: props.profileAddress.state || '',
})

// Actions
const saveAddress = () => {
    form.put(route('auth.settings.update'), {
        preserveScroll: true,
    })
}

const updateTheme = (theme) => {
    setTheme(theme)
    form.theme = theme
    saveAddress()
}

const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
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

const cancelSubscription = (subscription) => {
    router.post(route('auth.user-subscriptions.cancel', subscription.id), {}, { preserveScroll: true })
}

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

const disconnectIntegration = (account) => {
    router.delete(route('auth.sport-integrations.destroy', account.id), { preserveScroll: true })
}

const formatDuration = (seconds) => {
    if (!seconds) return '-'
    const minutes = Math.round(seconds / 60)
    return `${minutes} min`
}

const formatDistance = (meters) => {
    if (!meters) return '-'
    return `${(meters / 1000).toFixed(2).replace('.', ',')} km`
}
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

        <!-- TABS -->
        <div class="surface-card p-3 flex flex-wrap gap-2">
            <button @click="activeTab = 'profile'" :class="tabClass('profile')">Profil</button>
            <button @click="activeTab = 'address'" :class="tabClass('address')">Adresse</button>
            <button @click="activeTab = 'billing'" :class="tabClass('billing')">Zahlungen</button>
            <button @click="activeTab = 'integrations'" :class="tabClass('integrations')">Verknüpfungen</button>
            <button @click="activeTab = 'design'" :class="tabClass('design')">Design</button>
            <button @click="activeTab = 'security'" :class="tabClass('security')">Sicherheit</button>
        </div>

        <!-- PROFIL -->
        <div v-if="activeTab === 'profile'" class="surface-card p-5 space-y-6">
            <UpdateProfileInformationForm :user="$page.props.auth.user" />
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

            <div class="flex flex-wrap gap-3">
                <button class="btn" @click="updateTheme('air')">Air</button>
                <button class="btn" @click="updateTheme('dark')">Dark</button>
                <button class="btn" @click="updateTheme('womanly')">Womanly</button>
            </div>

            <div class="mt-6 border-t border-border pt-5">
                <h2 class="text-sm font-semibold text-secondary mb-3">Sprache</h2>
                <p class="mb-3 text-sm text-secondary">
                    Wähle die Sprache für Navigation, Seiten und Bedienelemente.
                </p>
                <LanguageDropdown />
            </div>
        </div>

        <!-- ADRESSE -->
        <div v-if="activeTab === 'address'" class="surface-card p-5">

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAddress">

                <div class="md:col-span-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                        Adresse
                    </h2>
                    <p class="mt-1 text-sm text-secondary">
                        Land ist Pflicht. Rest optional.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Land</label>
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
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Stadt</label>
                    <input v-model="form.city" class="input" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">PLZ</label>
                    <input v-model="form.postal_code" class="input" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Bundesland</label>
                    <input v-model="form.state" class="input" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Straße</label>
                    <input v-model="form.street" class="input" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Hausnummer</label>
                    <input v-model="form.house_number" class="input" />
                </div>

                <div class="md:col-span-2">
                    <button class="btn-primary" :disabled="form.processing">
                        Adresse speichern
                    </button>
                </div>

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
                            <button
                                v-if="!['cancelled', 'cancels_at_period_end'].includes(subscription.status)"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                @click="cancelSubscription(subscription)"
                            >
                                Kündigen
                            </button>
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
                                <th class="py-2 pr-4 text-right">PDF</th>
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
                                <td class="py-3 pr-4 text-right">
                                    <Link :href="route('auth.subscription-invoices.download', invoice.id)" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                        Download
                                    </Link>
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
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Google</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccounts.find((account) => account.provider === 'google')?.email || 'Noch nicht verbunden' }}
                                </p>
                            </div>
                            <a :href="route('social-auth.redirect', 'google')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                                Verbinden
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Outlook / Microsoft</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccounts.find((account) => account.provider === 'microsoft')?.email || 'Noch nicht verbunden' }}
                                </p>
                            </div>
                            <a :href="route('social-auth.redirect', 'microsoft')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
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
                    <article v-for="(provider, key) in sportIntegrations.providers" :key="key" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">{{ provider.label }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ provider.description }}</p>
                            </div>
                            <span
                                v-if="connectedAccountFor(key)"
                                class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue"
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

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a
                                v-if="!connectedAccountFor(key)"
                                :href="route('auth.sport-integrations.connect', key)"
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
                                @click="disconnectIntegration(connectedAccountFor(key))"
                            >
                                Entfernen
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Importierte Aktivitäten</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Datum</th>
                                <th class="py-2 pr-4">Quelle</th>
                                <th class="py-2 pr-4">Aktivität</th>
                                <th class="py-2 pr-4">Dauer</th>
                                <th class="py-2 pr-4">Distanz</th>
                                <th class="py-2 pr-4">Kalorien</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="activity in sportIntegrations.activities" :key="activity.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(activity.started_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ activity.provider }}</td>
                                <td class="py-3 pr-4 text-primary">{{ activity.title || activity.activity_type || '-' }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDuration(activity.duration_seconds) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDistance(activity.distance_meters) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ activity.calories || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!sportIntegrations.activities.length" class="py-6 text-sm text-secondary">
                        Noch keine Aktivitäten importiert.
                    </p>
                </div>
            </section>
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
