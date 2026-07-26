<script setup>
import { Link } from '@inertiajs/vue3'

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    deliveries: {
        type: Object,
        required: true,
    },
    deliveryRows: {
        type: Array,
        default: () => [],
    },
    filterForm: {
        type: Object,
        required: true,
    },
    resendCategories: {
        type: Object,
        required: true,
    },
    types: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['apply-filters', 'resend-delivery', 'resolve-delivery'])

const statusLabel = (status) => ({
    sent: 'Gesendet',
    failed: 'Fehlgeschlagen',
    skipped: 'Gedrosselt',
    resolved: 'Erledigt',
}[status] || status || '-')

const statusClass = (status) => ({
    sent: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
    failed: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
    skipped: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
    resolved: 'border-sky-500/30 bg-sky-500/10 text-sky-300',
}[status] || 'border-border bg-muted text-secondary')
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Versandprotokoll</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Neue Einträge erscheinen für Mails, die über die zentrale Mail-Schicht laufen.
                    </p>
                </div>
                <form class="grid gap-3 sm:grid-cols-[12rem_16rem_auto]" @submit.prevent="$emit('apply-filters')">
                    <select v-model="filterForm.status" class="rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Alle Status</option>
                        <option value="sent">Gesendet</option>
                        <option value="failed">Fehlgeschlagen</option>
                        <option value="skipped">Gedrosselt</option>
                        <option value="resolved">Erledigt</option>
                    </select>
                    <select v-model="filterForm.type" class="rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Alle Typen</option>
                        <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                        Filtern
                    </button>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-bg">
                    <tr class="text-left text-xs uppercase tracking-wide text-secondary">
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Typ</th>
                        <th class="px-5 py-3">Empfänger</th>
                        <th class="px-5 py-3">Absender</th>
                        <th class="px-5 py-3">Zeit</th>
                        <th class="px-5 py-3">Fehler</th>
                        <th class="px-5 py-3">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!deliveryRows.length">
                        <td colspan="7" class="px-5 py-10 text-center text-secondary">
                            Noch keine Mail-Einträge vorhanden.
                        </td>
                    </tr>
                    <tr v-for="delivery in deliveryRows" :key="delivery.id" class="align-top">
                        <td class="px-5 py-4">
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass(delivery.status)">
                                {{ statusLabel(delivery.status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-primary">{{ delivery.mail_type }}</td>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-primary">{{ delivery.recipient.name || '-' }}</p>
                            <p class="text-xs text-secondary">{{ delivery.recipient.email || '-' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-primary">{{ delivery.from_address || '-' }}</p>
                            <p class="text-xs text-secondary">{{ delivery.used_category || delivery.primary_category || '-' }} · {{ delivery.mailer || '-' }}</p>
                        </td>
                        <td class="px-5 py-4 text-secondary">{{ delivery.sent_at || delivery.created_at }}</td>
                        <td class="max-w-md px-5 py-4 text-xs text-secondary">
                            {{ delivery.error_message || '-' }}
                        </td>
                        <td class="min-w-64 px-5 py-4">
                            <div v-if="delivery.status !== 'sent'" class="flex flex-col gap-2">
                                <div v-if="delivery.resendable" class="flex gap-2">
                                    <select v-model="resendCategories[delivery.id]" class="min-w-32 rounded-lg border-border bg-inputBg text-xs text-primary">
                                        <option v-for="category in categories" :key="category" :value="category">
                                            {{ category }}
                                        </option>
                                    </select>
                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-white" @click="$emit('resend-delivery', delivery)">
                                        Erneut senden
                                    </button>
                                </div>
                                <button type="button" class="w-fit rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="$emit('resolve-delivery', delivery)">
                                    Als erledigt markieren
                                </button>
                            </div>
                            <span v-else class="text-xs text-secondary">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="deliveries.links?.length" class="flex flex-wrap gap-2 border-t border-border p-4">
            <Link
                v-for="link in deliveries.links"
                :key="link.label"
                :href="link.url || ''"
                class="rounded-lg border px-3 py-2 text-sm"
                :class="link.active ? 'border-buttonPrimary bg-buttonPrimary text-white' : 'border-border text-primary hover:bg-muted'"
            >
                {{ paginationLabel(link.label) }}
            </Link>
        </div>
    </section>
</template>

