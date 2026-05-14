<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    invoices: {
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
})

const today = new Date().toISOString().slice(0, 10)

const form = useForm({
    club_id: '',
    user_id: '',
    number: '',
    title: '',
    description: '',
    amount: '',
    status: 'open',
    due_date: today,
    issued_at: today,
})

const submit = () => {
    form.post(route('invoices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.status = 'open'
            form.due_date = today
            form.issued_at = today
        },
    })
}

const userLabel = (user) => user.email
    ? `${user.name || user.email} (${user.email})`
    : (user.name || `Nutzer #${user.id}`)

const deleteInvoice = (invoice) => {
    if (!invoice.delete_url || !window.confirm(`Rechnung ${invoice.number || invoice.id} wirklich loeschen?`)) {
        return
    }

    router.delete(invoice.delete_url, { preserveScroll: true })
}

const statusLabel = (status) => ({
    paid: 'Bezahlt',
    open: 'Offen',
    pending: 'Offen',
    awaiting_transfer: 'Warte auf Ueberweisung',
    overdue: 'Ueberfaellig',
    cancelled: 'Storniert',
    failed: 'Fehlgeschlagen',
}[status] || status || '-')
</script>

<template>
    <Head title="Rechnungen" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Finanzen</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Rechnungen</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Zentrale Uebersicht fuer ADS, Marketplace, E-Learning, Outfit-Abos, Konto-Abos und Vereinsrechnungen.
                    </p>
                </div>
                <Link :href="route('payments.index')" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Zahlungen ansehen
                </Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Rechnungen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.count || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offen</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.open || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.paid || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Umsatz bezahlt</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.revenue || '0,00 EUR' }}</p>
            </div>
        </section>

        <form class="surface-card p-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-primary">Manuelle Rechnung erstellen</p>
                    <p class="mt-1 text-xs text-secondary">
                        Fuer berechtigte Admins: einzelne Rechnung fuer Verein, Nutzer oder Sponsorvorgang anlegen.
                    </p>
                </div>
                <button
                    type="submit"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Rechnung erstellen
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
                    <span class="text-sm font-semibold text-primary">Empfaenger</span>
                    <select v-model="form.user_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Kein Nutzer / Verein allgemein</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ userLabel(user) }}
                        </option>
                    </select>
                    <p v-if="form.errors.user_id" class="mt-1 text-xs text-error">{{ form.errors.user_id }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Rechnungsnummer</span>
                    <input v-model="form.number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional, sonst automatisch">
                    <p v-if="form.errors.number" class="mt-1 text-xs text-error">{{ form.errors.number }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Status</span>
                    <select v-model="form.status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="open">Offen</option>
                        <option value="pending">Ausstehend</option>
                        <option value="paid">Bezahlt</option>
                        <option value="overdue">Ueberfaellig</option>
                        <option value="cancelled">Storniert</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-error">{{ form.errors.status }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold text-primary">Titel</span>
                    <input v-model="form.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z.B. Sponsoringrechnung Mai" required>
                    <p v-if="form.errors.title" class="mt-1 text-xs text-error">{{ form.errors.title }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Betrag EUR</span>
                    <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" min="0.01" step="0.01" type="number" required>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-error">{{ form.errors.amount }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Ausgestellt am</span>
                    <input v-model="form.issued_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                    <p v-if="form.errors.issued_at" class="mt-1 text-xs text-error">{{ form.errors.issued_at }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Faellig am</span>
                    <input v-model="form.due_date" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date" required>
                    <p v-if="form.errors.due_date" class="mt-1 text-xs text-error">{{ form.errors.due_date }}</p>
                </label>

                <label class="block md:col-span-2 xl:col-span-4">
                    <span class="text-sm font-semibold text-primary">Beschreibung</span>
                    <textarea v-model="form.description" class="mt-1 min-h-24 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optionale Details zur Leistung oder zum Vorgang"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-error">{{ form.errors.description }}</p>
                </label>
            </div>
        </form>

        <section class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Rechnung</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Quelle</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Bezahlt</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                                <p class="text-xs text-secondary">{{ invoice.title || '-' }}</p>
                                <p class="text-xs text-secondary">Faellig {{ invoice.due_date || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.club?.name || invoice.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ invoice.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ invoice.type_label || '-' }}</p>
                                <p class="text-xs text-secondary">{{ invoice.source || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ invoice.amount }}</td>
                            <td class="px-5 py-3">
                                <p class="text-primary">{{ invoice.paid_amount }}</p>
                                <p class="text-xs text-secondary">{{ invoice.paid_at || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ statusLabel(invoice.status) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a
                                        v-if="invoice.download_url"
                                        :href="invoice.download_url"
                                        class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    >
                                        PDF
                                    </a>
                                    <button
                                        v-if="invoice.delete_url"
                                        type="button"
                                        class="rounded-lg bg-error px-3 py-1 text-xs font-semibold text-white"
                                        @click="deleteInvoice(invoice)"
                                    >
                                        Loeschen
                                    </button>
                                    <span v-if="!invoice.download_url && !invoice.delete_url" class="text-xs text-secondary">-</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Rechnungen vorhanden.
                </p>

                <div v-if="invoices.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
                    <Link
                        v-for="link in invoices.links"
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
