<script setup>
import { computed } from 'vue'

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const props = defineProps({
    inactiveRules: { type: Array, default: () => [] },
    inactiveSearch: { type: String, default: '' },
    inactiveStage: { type: String, default: 'all' },
    inactiveSummary: { type: Object, default: () => ({}) },
    inactiveUsers: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            from: null,
            to: null,
            total: 0,
        }),
    },
    sendingNoticeId: { type: String, default: null },
})

const emit = defineEmits([
    'clear',
    'open-mail-center',
    'page',
    'send-notice',
    'update:inactiveSearch',
    'update:inactiveStage',
])

const search = computed({
    get: () => props.inactiveSearch,
    set: (value) => emit('update:inactiveSearch', value),
})

const stage = computed({
    get: () => props.inactiveStage,
    set: (value) => emit('update:inactiveStage', value),
})

const inactiveCards = computed(() => [
    { label: '12+ Monate', value: props.inactiveSummary.inactive_12 || 0, tone: 'text-warning' },
    { label: '18+ Monate', value: props.inactiveSummary.inactive_18 || 0, tone: 'text-warning' },
    { label: '24+ Monate', value: props.inactiveSummary.inactive_24 || 0, tone: 'text-error' },
    { label: '36+ Monate', value: props.inactiveSummary.inactive_36 || 0, tone: 'text-error' },
    { label: 'Mail-Fehler', value: props.inactiveSummary.mail_failed || 0, tone: 'text-error' },
])

const initials = (name) => (name || '?')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

const stageLabel = (value) => ({
    first: 'Erste Mail fällig',
    second: 'Zweite Mail fällig',
    scheduled: 'Profil ausblenden',
    anonymize: 'Anonymisierung prüfen',
    waiting: 'Warten',
    active: 'Aktiv',
    check: 'Prüfen',
}[value] || value)

const stageClass = (value) => {
    if (['anonymize', 'scheduled'].includes(value)) return 'bg-error/10 text-error'
    if (['first', 'second', 'check'].includes(value)) return 'bg-warning/10 text-warning'
    return 'bg-success/10 text-success'
}

const mailBadgeClass = (status) => {
    if (status === 'sent') return 'bg-success/10 text-success'
    if (status === 'failed') return 'bg-error/10 text-error'
    if (status === 'skipped') return 'bg-warning/10 text-warning'
    return 'bg-secondary/20 text-secondary'
}
</script>

<template>
    <section class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="card in inactiveCards"
                :key="card.label"
                class="rounded-lg border border-border bg-card p-4"
            >
                <p class="text-xs font-semibold uppercase text-secondary">{{ card.label }}</p>
                <p class="mt-2 text-2xl font-bold" :class="card.tone">{{ card.value }}</p>
            </article>
        </div>

        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Regeln</h2>
                    <p class="text-sm text-secondary">
                        Diese Regeln gelten für die automatische Prüfung und deine manuelle Kontrolle.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-border bg-inputBg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted"
                    @click="emit('open-mail-center')"
                >
                    <i class="las la-envelope-open-text text-lg"></i>
                    Mail-Zentrale öffnen
                </button>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="rule in inactiveRules"
                    :key="rule.month"
                    class="rounded-lg border border-border bg-inputBg p-4"
                >
                    <p class="text-xs font-bold uppercase text-buttonPrimary">{{ rule.month }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-primary">{{ rule.title }}</h3>
                    <p class="mt-2 text-xs leading-5 text-secondary">{{ rule.description }}</p>
                </article>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <div class="grid gap-3 border-b border-border p-4 lg:grid-cols-[1fr_220px_auto]">
                <div class="relative">
                    <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-lg text-secondary"></i>
                    <input
                        v-model="search"
                        type="search"
                        class="w-full rounded-md border border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary placeholder-secondary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary"
                        placeholder="Name oder E-Mail suchen"
                    />
                </div>

                <select
                    v-model="stage"
                    class="rounded-md border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-buttonPrimary focus:ring-1 focus:ring-buttonPrimary"
                >
                    <option value="all">Alle Nutzer</option>
                    <option value="12">12+ Monate inaktiv</option>
                    <option value="18">18+ Monate inaktiv</option>
                    <option value="24">24+ Monate inaktiv</option>
                    <option value="36">36+ Monate inaktiv</option>
                    <option value="mail_failed">Mail fehlgeschlagen</option>
                </select>

                <button
                    type="button"
                    class="rounded-md border border-border bg-inputBg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-muted"
                    @click="emit('clear')"
                >
                    Zurücksetzen
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-card">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Nutzer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Letzter Login</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">DSGVO-Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Mailstatus</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-table">
                        <tr v-for="user in inactiveUsers.data" :key="user.id">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="user.profile_photo_url"
                                        :src="user.profile_photo_url"
                                        :alt="user.name"
                                        class="h-10 w-10 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-sm font-bold text-buttonTextPrimary"
                                    >
                                        {{ initials(user.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-primary">{{ user.name }}</p>
                                        <p class="text-xs text-secondary">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4 text-sm text-primary">
                                <p>{{ user.last_login_at || user.last_seen_at || '-' }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ user.inactive_days ?? '-' }} Tage inaktiv
                                </p>
                            </td>

                            <td class="px-4 py-4 text-sm">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="stageClass(user.recommended_stage)">
                                    {{ stageLabel(user.recommended_stage) }}
                                </span>
                                <p class="mt-2 text-xs text-secondary">
                                    Datenschutz: {{ user.privacy_status }} · Profil: {{ user.profile_visibility }}
                                </p>
                                <p v-if="user.deletion_scheduled_at" class="mt-1 text-xs text-error">
                                    Anonymisierung geplant: {{ user.deletion_scheduled_at }}
                                </p>
                            </td>

                            <td class="px-4 py-4 text-sm">
                                <div class="space-y-1 text-xs text-secondary">
                                    <p>12M: {{ user.first_warning_sent_at || 'nicht gesendet' }}</p>
                                    <p>18M: {{ user.second_warning_sent_at || 'nicht gesendet' }}</p>
                                </div>
                                <div v-if="user.last_mail" class="mt-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="mailBadgeClass(user.last_mail.status)">
                                        {{ user.last_mail.status }}
                                    </span>
                                    <p class="mt-1 max-w-xs truncate text-xs text-secondary">
                                        {{ user.last_mail.type }} · {{ user.last_mail.created_at }}
                                    </p>
                                    <p v-if="user.last_mail.error_message" class="mt-1 max-w-xs truncate text-xs text-error">
                                        {{ user.last_mail.error_message }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="sendingNoticeId === `${user.id}-first`"
                                        @click="emit('send-notice', user, 'first')"
                                    >
                                        12M-Mail
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="sendingNoticeId === `${user.id}-second`"
                                        @click="emit('send-notice', user, 'second')"
                                    >
                                        18M-Mail
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-error/40 bg-error/10 px-3 py-2 text-xs font-semibold text-error transition hover:bg-error/20 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="sendingNoticeId === `${user.id}-scheduled`"
                                        @click="emit('send-notice', user, 'scheduled')"
                                    >
                                        24M-Hinweis
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="inactiveUsers.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-secondary">
                                Keine passenden Nutzer gefunden.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-secondary">
                    <span v-if="inactiveUsers.total > 0">
                        Zeige {{ inactiveUsers.from }} bis {{ inactiveUsers.to }} von {{ inactiveUsers.total }} Nutzern.
                    </span>
                    <span v-else>Keine Nutzer vorhanden.</span>
                </p>

                <div v-if="inactiveUsers.links.length > 3" class="flex flex-wrap gap-1">
                    <button
                        v-for="link in inactiveUsers.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="link.active
                            ? 'bg-primary text-buttonTextPrimary'
                            : 'bg-card text-primary hover:bg-secondary/20'"
                        @click="emit('page', link.url)"
                    >
                        {{ paginationLabel(link.label) }}
                        </button>
                </div>
            </div>
        </section>
    </section>
</template>
