<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useTheme } from '@/services/useTheme'

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
        default: () => ({ invoices: [], payments: [] }),
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
    paid: 'Bezahlt',
    overdue: 'Ueberfaellig',
    cancelled: 'Storniert',
}[status] || status)
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
                <h2 class="text-lg font-semibold text-primary">Meine Rechnungen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Hier siehst du offene und bezahlte Vereinsbeitraege.
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Nr.</th>
                                <th class="py-2 pr-4">Verein</th>
                                <th class="py-2 pr-4">Titel</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Faellig</th>
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
