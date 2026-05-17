<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

defineProps({
    payments: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    clubs: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    invoices: {
        type: Array,
        default: () => [],
    },
})

const today = new Date().toISOString().slice(0, 10)

const form = useForm({
    club_id: '',
    user_id: '',
    invoice_id: '',
    amount: '',
    status: 'paid',
    method: 'bank_transfer',
    reference: '',
    paid_at: today,
    notes: '',
})

const submit = () => {
    form.post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.status = 'paid'
            form.method = 'bank_transfer'
            form.paid_at = today
        },
    })
}

const userLabel = (user) => user.email
    ? `${user.name || user.email} (${user.email})`
    : (user.name || `Nutzer #${user.id}`)

const invoiceLabel = (invoice) => `${invoice.number || `#${invoice.id}`} - ${invoice.title || 'Rechnung'} (${invoice.amount})`

const deletePayment = async (payment) => {
    if (!payment.delete_url) {
        return
    }

    const confirmed = await confirmDialog({
        title: 'Zahlung löschen',
        message: `Soll Zahlung #${payment.id} wirklich gelöscht werden?`,
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) {
        return
    }

    router.delete(payment.delete_url, { preserveScroll: true })
}

const statusLabel = (status) => ({
    paid: 'Bezahlt',
    pending: 'Offen',
    open: 'Offen',
    failed: 'Fehlgeschlagen',
    cancelled: 'Storniert',
}[status] || status || '-')

const methodLabel = (method) => ({
    paypal: 'PayPal',
    stripe: 'Stripe',
    bank_transfer: 'Überweisung',
    cash: 'Bar',
    card: 'Karte',
}[method] || method || '-')
</script>

<template>
    <Head title="Zahlungen" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Finanzen</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Zahlungen</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Alle erfassten Vereins- und Plattformzahlungen an einem Ort.
                    </p>
                </div>
                <Link :href="route('admin.subscription-invoices.index')" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Abo-Rechnungen
                </Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Zahlungen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.count || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.paid || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.pending || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Umsatz bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.revenue || '0,00 EUR' }}</p>
            </div>
        </section>

        <form class="surface-card p-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-primary">Manuelle Zahlung erfassen</p>
                    <p class="mt-1 text-xs text-secondary">
                        Zahlungseingang erfassen und optional einer offenen Rechnung zuordnen.
                    </p>
                </div>
                <button
                    type="submit"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Zahlung erstellen
                </button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Verein</span>
                    <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Verein waehlen</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p v-if="form.errors.club_id" class="mt-1 text-xs text-error">{{ form.errors.club_id }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Zahler</span>
                    <select v-model="form.user_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Nutzer waehlen</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
                    </select>
                    <p v-if="form.errors.user_id" class="mt-1 text-xs text-error">{{ form.errors.user_id }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">Rechnung</span>
                    <select v-model="form.invoice_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Keine Rechnung zuordnen</option>
                        <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">{{ invoiceLabel(invoice) }}</option>
                    </select>
                    <p v-if="form.errors.invoice_id" class="mt-1 text-xs text-error">{{ form.errors.invoice_id }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Betrag EUR</span>
                    <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" min="0.01" step="0.01" type="number" required>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Status</span>
                    <select v-model="form.status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="paid">Bezahlt</option>
                        <option value="pending">Ausstehend</option>
                        <option value="open">Offen</option>
                        <option value="failed">Fehlgeschlagen</option>
                        <option value="cancelled">Storniert</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Methode</span>
                    <select v-model="form.method" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="bank_transfer">Ueberweisung</option>
                        <option value="cash">Bar</option>
                        <option value="card">Karte</option>
                        <option value="paypal">PayPal</option>
                        <option value="stripe">Stripe</option>
                        <option value="manual">Manuell</option>
                    </select>
                    <p v-if="form.errors.method" class="mt-1 text-xs text-error">{{ form.errors.method }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Bezahlt am</span>
                    <input v-model="form.paid_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                    <p v-if="form.errors.paid_at" class="mt-1 text-xs text-error">{{ form.errors.paid_at }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">Referenz</span>
                    <input v-model="form.reference" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Verwendungszweck, Transaktions-ID oder Buchungsvermerk">
                    <p v-if="form.errors.reference" class="mt-1 text-xs text-error">{{ form.errors.reference }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">Notiz</span>
                    <textarea v-model="form.notes" class="mt-1 min-h-24 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optionale interne Notiz"></textarea>
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-error">{{ form.errors.notes }}</p>
                </label>
            </div>
        </form>

        <section class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Zahlung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Methode</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">#{{ payment.id }}</p>
                                <p class="text-xs text-secondary">{{ payment.paid_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ payment.club?.name || payment.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ payment.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ payment.invoice?.number || '-' }}</p>
                                <p class="text-xs text-secondary">{{ payment.invoice?.title || '' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ methodLabel(payment.method) }}</p>
                                <p class="text-xs text-secondary">{{ payment.reference || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ payment.amount }}</td>
                            <td class="px-5 py-3 text-secondary">{{ statusLabel(payment.status) }}</td>
                            <td class="px-5 py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg bg-error px-3 py-1 text-xs font-semibold text-white"
                                    @click="deletePayment(payment)"
                                >
                                    Loeschen
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!payments.data.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Zahlungen vorhanden.
                </p>

                <div v-if="payments.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                    <Link
                        v-for="link in payments.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-scroll
                        class="rounded-lg border border-border px-3 py-1 text-sm"
                        :class="[
                            link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
