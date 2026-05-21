<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    deliveries: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    queue: {
        type: Object,
        default: () => ({}),
    },
    senders: {
        type: Array,
        default: () => [],
    },
    preferences: {
        type: Object,
        default: () => ({}),
    },
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
    audits: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    types: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const filterForm = useForm({
    status: props.filters.status || '',
    type: props.filters.type || '',
})

const preferenceForm = useForm({
    invoice_primary_category: props.preferences.invoice_primary_category || 'system',
    invoice_fallback_category: props.preferences.invoice_fallback_category || 'billing',
    disabled_categories: props.preferences.disabled_categories || [],
})

const deliveryRows = computed(() => props.deliveries.data || [])
const recentFailedJobs = computed(() => props.queue.recent_failed_jobs || [])
const resendCategories = reactive({})
const senderForms = reactive(Object.fromEntries(props.senders.map((sender) => [
    sender.category,
    {
        from_address: sender.address || '',
        from_name: sender.name || '',
        host: sender.host || '',
        port: sender.port || '',
        username: sender.username || '',
        scheme: sender.scheme || '',
        active: !sender.disabled,
        new_password: '',
        processing: false,
    },
])))

const applyFilters = () => {
    router.get(route('admin.mail-center.index'), {
        status: filterForm.status || undefined,
        type: filterForm.type || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const savePreferences = () => {
    preferenceForm.put(route('admin.mail-center.preferences.update'), {
        preserveScroll: true,
    })
}

const toggleDisabledCategory = (category, checked) => {
    const current = new Set(preferenceForm.disabled_categories || [])

    if (checked) {
        current.add(category)
    } else {
        current.delete(category)
    }

    current.delete('system')
    preferenceForm.disabled_categories = Array.from(current)
}

const resendDelivery = (delivery) => {
    const category = resendCategories[delivery.id] || delivery.fallback_category || delivery.primary_category || 'billing'

    router.post(route('admin.mail-center.resend', delivery.id), { category }, {
        preserveScroll: true,
    })
}

const resolveDelivery = (delivery) => {
    router.put(route('admin.mail-center.resolve', delivery.id), {}, {
        preserveScroll: true,
    })
}

const saveSender = (sender) => {
    const form = senderForms[sender.category]
    form.processing = true

    router.put(route('admin.mail-center.senders.update', sender.category), {
        from_address: form.from_address,
        from_name: form.from_name,
        host: form.host,
        port: form.port,
        username: form.username,
        scheme: form.scheme,
        active: Boolean(form.active),
        new_password: form.new_password,
    }, {
        preserveScroll: true,
        onFinish: () => {
            form.processing = false
            form.new_password = ''
        },
    })
}

const testSender = (sender) => {
    router.post(route('admin.mail-center.senders.test', sender.category), {}, {
        preserveScroll: true,
    })
}

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

const readyLabel = (sender) => {
    if (sender.resolved?.mailer === 'log') {
        return 'Lokal: Log'
    }

    return sender.ready ? 'Bereit' : 'Unvollstaendig'
}
</script>

<template>
    <Head title="Mail-Zentrale" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">System</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Mail-Zentrale</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Überwache Versand, Warteschlange, Fehler und Absender-Regeln für transaktionale E-Mails.
                    </p>
                </div>
                <div class="rounded-lg border border-border bg-bg px-4 py-3 text-sm text-secondary">
                    Lokale Tests werden als Log-Mail behandelt, solange echte Mails lokal deaktiviert sind.
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Protokoll</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.total || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Gesendet</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ summary.sent || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Fehlgeschlagen</p>
                <p class="mt-2 text-2xl font-bold text-rose-300">{{ summary.failed || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Gedrosselt</p>
                <p class="mt-2 text-2xl font-bold text-amber-300">{{ summary.skipped || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Erledigt</p>
                <p class="mt-2 text-2xl font-bold text-sky-300">{{ summary.resolved || 0 }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Absender-Regeln</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Für Rechnungen kannst du Haupt- und Ersatz-Absender ohne Code-Änderung wechseln.
                        </p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="savePreferences">
                        <label class="text-sm font-semibold text-primary">
                            Haupt
                            <select v-model="preferenceForm.invoice_primary_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-primary">
                            Ersatz
                            <select v-model="preferenceForm.invoice_fallback_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Kein Ersatz</option>
                                <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                            </select>
                        </label>
                        <button
                            type="submit"
                            class="self-end rounded-lg border border-buttonPrimary bg-buttonPrimary px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                            :disabled="preferenceForm.processing"
                        >
                            Speichern
                        </button>
                    </form>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div v-for="sender in senders" :key="sender.category" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-primary">{{ sender.category }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ sender.address }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="sender.ready || sender.resolved?.mailer === 'log' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300'">
                                {{ sender.disabled ? 'Deaktiviert' : readyLabel(sender) }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-secondary">
                            Mailer: {{ sender.resolved?.mailer || sender.mailer || '-' }}
                        </p>
                        <div v-if="canManageSecrets" class="mt-4 space-y-3 border-t border-border pt-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-semibold text-secondary">
                                    Absenderadresse
                                    <input v-model="senderForms[sender.category].from_address" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    Anzeigename
                                    <input v-model="senderForms[sender.category].from_name" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    SMTP Host
                                    <input v-model="senderForms[sender.category].host" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    Port
                                    <input v-model="senderForms[sender.category].port" type="number" min="1" max="65535" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    SMTP Benutzer
                                    <input v-model="senderForms[sender.category].username" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                </label>
                                <label class="text-xs font-semibold text-secondary">
                                    Verschluesselung
                                    <select v-model="senderForms[sender.category].scheme" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                        <option value="">Standard</option>
                                        <option value="smtp">smtp</option>
                                        <option value="smtps">smtps</option>
                                    </select>
                                </label>
                            </div>

                            <label class="block text-xs font-semibold text-secondary">
                                Neues Passwort setzen
                                <input
                                    v-model="senderForms[sender.category].new_password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Leer lassen, um Passwort nicht zu aendern"
                                    class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                >
                            </label>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-secondary">
                                    <input v-model="senderForms[sender.category].active" type="checkbox" class="h-4 w-4 rounded border-border bg-inputBg text-buttonPrimary accent-buttonPrimary">
                                    Mailbox aktiv
                                </label>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted" @click="testSender(sender)">
                                        Testmail
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-buttonPrimary bg-buttonPrimary px-3 py-2 text-xs font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                                        :disabled="senderForms[sender.category].processing"
                                        @click="saveSender(sender)"
                                    >
                                        Speichern
                                    </button>
                                </div>
                            </div>

                            <p class="text-xs text-secondary">
                                Passwort ist {{ sender.has_password ? 'gesetzt' : 'nicht gesetzt' }}<span v-if="sender.password_updated_at"> · zuletzt aktualisiert {{ sender.password_updated_at }}</span>. Es wird nie angezeigt.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Queue & Fehlerjobs</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">Offene Jobs</p>
                        <p class="mt-2 text-2xl font-bold text-primary">{{ queue.pending_jobs || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">Fehlerjobs</p>
                        <p class="mt-2 text-2xl font-bold text-rose-300">{{ queue.failed_jobs || 0 }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3">
                    <div v-if="!recentFailedJobs.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        Keine aktuellen Fehlerjobs gefunden.
                    </div>
                    <div v-for="job in recentFailedJobs" :key="job.id" class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-4">
                        <div class="flex items-center justify-between gap-3 text-xs text-secondary">
                            <span>Job #{{ job.id }} · {{ job.queue }}</span>
                            <span>{{ job.failed_at }}</span>
                        </div>
                        <p class="mt-2 text-sm text-rose-200">{{ job.error }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="canManageSecrets" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <h2 class="text-lg font-semibold text-primary">Mailbox-Audit</h2>
                <p class="mt-1 text-sm text-secondary">
                    Protokolliert werden Änderungen und Testversand ohne Klartext-Passwoerter.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-bg">
                        <tr class="text-left text-xs uppercase tracking-wide text-secondary">
                            <th class="px-5 py-3">Zeit</th>
                            <th class="px-5 py-3">Kategorie</th>
                            <th class="px-5 py-3">Aktion</th>
                            <th class="px-5 py-3">Admin</th>
                            <th class="px-5 py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!audits.length">
                            <td colspan="5" class="px-5 py-8 text-center text-secondary">Noch keine Audit-Eintraege.</td>
                        </tr>
                        <tr v-for="audit in audits" :key="audit.id">
                            <td class="px-5 py-4 text-secondary">{{ audit.created_at }}</td>
                            <td class="px-5 py-4 text-primary">{{ audit.category }}</td>
                            <td class="px-5 py-4 text-primary">{{ audit.action }}</td>
                            <td class="px-5 py-4 text-secondary">#{{ audit.actor_id || '-' }}</td>
                            <td class="px-5 py-4 text-xs text-secondary">
                                <span v-if="audit.after?.password_changed">Passwort wurde neu gesetzt. </span>
                                <span v-if="audit.after?.from_address">Absender: {{ audit.after.from_address }}</span>
                                <span v-else-if="audit.after?.error">Fehler: {{ audit.after.error }}</span>
                                <span v-else>-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Versandprotokoll</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Neue Eintraege erscheinen für Mails, die über die zentrale Mail-Schicht laufen.
                        </p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[12rem_16rem_auto]" @submit.prevent="applyFilters">
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
                            <th class="px-5 py-3">Empfaenger</th>
                            <th class="px-5 py-3">Absender</th>
                            <th class="px-5 py-3">Zeit</th>
                            <th class="px-5 py-3">Fehler</th>
                            <th class="px-5 py-3">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!deliveryRows.length">
                            <td colspan="7" class="px-5 py-10 text-center text-secondary">
                                Noch keine Mail-Eintraege vorhanden.
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
                                        <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-white" @click="resendDelivery(delivery)">
                                            Erneut senden
                                        </button>
                                    </div>
                                    <button type="button" class="w-fit rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="resolveDelivery(delivery)">
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
                    v-html="link.label"
                />
            </div>
        </section>
    </div>
</template>
