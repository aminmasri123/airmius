<script setup>
import { Link } from '@inertiajs/vue3'

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

defineProps({
    invoices: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
    statusClasses: { type: Function, required: true },
    recipientLabel: { type: Function, required: true },
    openCreateModal: { type: Function, required: true },
    openStatusModal: { type: Function, required: true },
    deleteInvoice: { type: Function, required: true },
})
</script>

<template>
    <section class="rounded-2xl border-l-4 border-l-air-blue border border-border bg-card">
        <div class="flex flex-col gap-2 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Rechnungsliste</p>
                <h2 class="mt-1 text-lg font-black text-primary">Alle Quellen zentral</h2>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <p class="text-sm text-secondary">ADS, Marketplace, E-Learning, Outfit, Konto-Abo und manuelle Rechnungen</p>
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="openCreateModal">
                    <i class="las la-plus-circle text-lg"></i>
                    Neu
                </button>
            </div>
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-bg text-xs uppercase tracking-[0.12em] text-secondary">
                    <tr>
                        <th class="px-5 py-3">Rechnung</th>
                        <th class="px-5 py-3">Empfänger</th>
                        <th class="px-5 py-3">Grund</th>
                        <th class="px-5 py-3">Betrag</th>
                        <th class="px-5 py-3">Bezahlt</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/40">
                        <td class="px-5 py-4">
                            <p class="font-black text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ invoice.title || '-' }}</p>
                            <p class="text-xs text-secondary">Fällig {{ invoice.due_date || '-' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="font-bold text-primary">{{ recipientLabel(invoice) }}</p>
                            <p class="text-xs text-secondary">{{ invoice.user?.email || '-' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="font-bold text-primary">{{ invoice.type_label || '-' }}</p>
                            <p class="text-xs text-secondary">{{ invoice.source || '-' }}</p>
                        </td>
                        <td class="px-5 py-4 font-black text-primary">{{ invoice.amount }}</td>
                        <td class="px-5 py-4">
                            <p class="text-primary">{{ invoice.paid_amount }}</p>
                            <p class="text-xs text-secondary">{{ invoice.paid_at || '-' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <button
                                v-if="invoice.status_update_url"
                                type="button"
                                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition hover:scale-[1.02] hover:ring-2 hover:ring-air-blue/30"
                                :class="statusClasses(invoice.status)"
                                @click="openStatusModal(invoice)"
                            >
                                {{ statusLabel(invoice.status) }}
                            </button>
                            <span v-else class="inline-flex rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(invoice.status)">
                                {{ statusLabel(invoice.status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a v-if="invoice.download_url" :href="invoice.download_url" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted">PDF</a>
                                <button v-if="invoice.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-semibold text-white hover:bg-error/90" @click="deleteInvoice(invoice)">Löschen</button>
                                <span v-if="!invoice.download_url && !invoice.delete_url" class="text-xs text-secondary">-</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 p-4 lg:hidden">
            <article v-for="invoice in invoices.data" :key="invoice.id" class="rounded-xl border border-border bg-inputBg p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-black text-primary">{{ invoice.number || ('#' + invoice.id) }}</p>
                        <p class="mt-1 text-sm text-secondary">{{ invoice.title || invoice.type_label }}</p>
                    </div>
                    <button
                        v-if="invoice.status_update_url"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                        :class="statusClasses(invoice.status)"
                        @click="openStatusModal(invoice)"
                    >
                        {{ statusLabel(invoice.status) }}
                    </button>
                    <span v-else class="shrink-0 rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(invoice.status)">
                        {{ statusLabel(invoice.status) }}
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs uppercase text-secondary">Empfänger</p>
                        <p class="mt-1 font-bold text-primary">{{ recipientLabel(invoice) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-secondary">Betrag</p>
                        <p class="mt-1 font-bold text-primary">{{ invoice.amount }}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a v-if="invoice.download_url" :href="invoice.download_url" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary">PDF</a>
                    <button v-if="invoice.delete_url" type="button" class="rounded-lg bg-error px-3 py-2 text-xs font-semibold text-white hover:bg-error/90" @click="deleteInvoice(invoice)">Löschen</button>
                </div>
            </article>
        </div>

        <p v-if="!invoices.data.length" class="px-5 py-8 text-sm text-secondary">Noch keine Rechnungen vorhanden.</p>

        <div v-if="invoices.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border px-5 py-4">
            <Link
                v-for="link in invoices.links"
                :key="link.label"
                :href="link.url || '#'"
                preserve-scroll
                class="rounded-lg border border-border px-3 py-2 text-sm"
                :class="[link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted', !link.url ? 'pointer-events-none opacity-40' : '']"
            >
                {{ paginationLabel(link.label) }}
            </Link>
        </div>
    </section>
</template>
