<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    club: { type: Object, required: true },
    counts: { type: Object, required: true },
    campaigns: { type: Array, default: () => [] },
    failures: { type: Array, default: () => [] },
    suppressions: { type: Array, default: () => [] },
    subscribers: { type: Object, required: true },
})

const { locale, t } = useI18n()
const localeCode = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar-EG' })[locale.value] || 'de-DE')
const newIdempotencyKey = () => `newsletter-${Date.now()}-${Math.random().toString(36).slice(2)}`
const form = useForm({ subject: '', body: '', idempotency_key: newIdempotencyKey() })
const formatDate = (value) => value ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '–'
const send = async () => {
    const confirmed = await confirmDialog({
        title: t('Newsletter jetzt versenden?'),
        message: t('Die Nachricht wird sofort an {count} bestätigte Empfänger versendet und kann nicht zurückgerufen werden.', { count: props.counts.confirmed }),
        confirmLabel: t('Newsletter senden'),
    })
    if (!confirmed) return
    form.post(route('auth.clubs.newsletter.send', props.club.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.idempotency_key = newIdempotencyKey()
        },
    })
}
</script>

<template>
    <Head :title="`${$t('Newsletter')} – ${club.name}`" />
    <div class="mx-auto max-w-5xl space-y-6">
        <header>
            <Link :href="route('auth.clubs.show', club.id)" class="text-sm font-bold text-air-blue hover:underline">← {{ $t('Zurück zum Verein') }}</Link>
            <p class="mt-5 text-xs font-black uppercase tracking-[.18em] text-air-blue">{{ $t('Vereinskommunikation') }}</p>
            <h1 class="mt-2 text-3xl font-black text-primary">{{ $t('Newsletter') }}</h1>
            <p class="mt-2 max-w-3xl text-secondary">{{ $t('Versende Vereinsnews ausschließlich an bestätigte Empfänger. Abmeldungen und unzustellbare Adressen werden automatisch ausgeschlossen.') }}</p>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="rounded-2xl border border-border bg-card p-5"><p class="text-3xl font-black text-success">{{ counts.confirmed }}</p><p class="mt-1 text-sm text-secondary">{{ $t('Bestätigte Empfänger') }}</p></article>
            <article class="rounded-2xl border border-border bg-card p-5"><p class="text-3xl font-black text-warning">{{ counts.pending }}</p><p class="mt-1 text-sm text-secondary">{{ $t('Bestätigung ausstehend') }}</p></article>
            <article class="rounded-2xl border border-border bg-card p-5"><p class="text-3xl font-black text-secondary">{{ counts.unsubscribed }}</p><p class="mt-1 text-sm text-secondary">{{ $t('Abgemeldet') }}</p></article>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <h2 class="text-xl font-black text-primary">{{ $t('Newsletter-Anmeldungen') }} ({{ subscribers.total }})</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-border"><th class="p-3">{{ $t('Name') }}</th><th class="p-3">{{ $t('E-Mail-Adresse') }}</th><th class="p-3">{{ $t('Angemeldet am') }}</th><th class="p-3">{{ $t('Status') }}</th></tr></thead>
                    <tbody><tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="border-b border-border"><td class="p-3">{{ subscriber.name || '–' }}</td><td class="p-3 break-all">{{ subscriber.email }}</td><td class="p-3">{{ formatDate(subscriber.created_at) }}</td><td class="p-3">{{ $t(({ pending: 'Bestätigung ausstehend', confirmed: 'Bestätigt', unsubscribed: 'Abgemeldet' })[subscriber.status] || subscriber.status) }}</td></tr></tbody>
                </table>
                <p v-if="!subscribers.total" class="py-4 text-secondary">{{ $t('Noch keine Newsletter-Anmeldungen vorhanden.') }}</p>
            </div>
            <nav class="mt-4 flex items-center justify-between gap-3" :aria-label="$t('Newsletter-Anmeldungen')">
                <Link v-if="subscribers.prev_page_url" :href="subscribers.prev_page_url" preserve-scroll class="text-air-blue">{{ $t('Zurück') }}</Link>
                <span>{{ subscribers.current_page }} / {{ subscribers.last_page }}</span>
                <Link v-if="subscribers.next_page_url" :href="subscribers.next_page_url" preserve-scroll class="text-air-blue">{{ $t('Weiter') }}</Link>
            </nav>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <h2 class="text-xl font-black text-primary">{{ $t('Newsletter verfassen') }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ $t('Prüfe Betreff und Inhalt sorgfältig. Der Versand startet nach dem Klick unmittelbar und kann nicht zurückgerufen werden.') }}</p>
            <form class="mt-5 space-y-4" @submit.prevent="send">
                <label class="block"><span class="text-sm font-bold text-primary">{{ $t('Betreff') }}</span><input v-model.trim="form.subject" required maxlength="180" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"><span v-if="form.errors.subject" class="mt-1 block text-sm text-error">{{ form.errors.subject }}</span></label>
                <label class="block"><span class="text-sm font-bold text-primary">{{ $t('Nachricht') }}</span><textarea v-model.trim="form.body" required maxlength="10000" rows="10" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea><span v-if="form.errors.body" class="mt-1 block text-sm text-error">{{ form.errors.body }}</span></label>
                <div class="rounded-xl border border-info/30 bg-info/10 p-4 text-sm leading-6 text-secondary"><strong class="text-primary">{{ $t('Datenschutz-Hinweis:') }}</strong> {{ $t('Empfängeradressen bleiben verborgen. Jede Nachricht enthält automatisch einen persönlichen Abmeldelink.') }}</div>
                <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-5 py-3 font-black text-buttonTextPrimary disabled:opacity-60 sm:w-auto" :disabled="form.processing || !counts.confirmed">
                    {{ form.processing ? $t('Wird versendet …') : $t('Jetzt an bestätigte Empfänger senden') }}
                </button>
                <p v-if="!counts.confirmed" class="text-sm text-warning">{{ $t('Noch keine bestätigten Empfänger vorhanden.') }}</p>
            </form>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <h2 class="text-xl font-black text-primary">{{ $t('Letzte Newsletter') }}</h2>
            <div v-if="campaigns.length" class="mt-4 divide-y divide-border">
                <article v-for="campaign in campaigns" :key="campaign.id" class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="font-bold text-primary">{{ campaign.subject }}</p><p class="mt-1 text-sm text-secondary">{{ formatDate(campaign.sent_at || campaign.created_at) }}</p></div>
                    <span class="w-fit rounded-full bg-success/10 px-3 py-1 text-xs font-bold text-success">{{ campaign.deliveries_count }} {{ $t('versendet') }}</span>
                </article>
            </div>
            <p v-else class="mt-4 text-sm text-secondary">{{ $t('Noch kein Newsletter versendet.') }}</p>
        </section>
        <section class="grid gap-5 md:grid-cols-2">
            <div class="rounded-2xl border border-border bg-card p-5">
                <h2 class="text-xl font-black text-primary">{{ $t('Zustellfehler') }}</h2>
                <p v-if="!failures.length" class="mt-3 text-sm text-secondary">{{ $t('Keine Zustellfehler bekannt.') }}</p>
                <ul v-else class="mt-3 divide-y divide-border">
                    <li v-for="item in failures" :key="item.id" class="py-3 text-sm">
                        <p class="break-all font-bold text-primary">{{ item.email }}</p>
                        <p class="text-secondary">{{ item.bounce_type }} · {{ formatDate(item.bounced_at) }}</p>
                        <p v-if="item.failure_reason" class="text-secondary">{{ item.failure_reason }}</p>
                    </li>
                </ul>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <h2 class="text-xl font-black text-primary">{{ $t('Gesperrte Adressen') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ $t('Diese Adressen werden beim nächsten Versand ausgeschlossen.') }}</p>
                <p v-if="!suppressions.length" class="mt-3 text-sm text-secondary">{{ $t('Keine Adressen gesperrt.') }}</p>
                <ul v-else class="mt-3 divide-y divide-border">
                    <li v-for="item in suppressions" :key="item.email" class="py-3 text-sm">
                        <p class="break-all font-bold text-primary">{{ item.email }}</p>
                        <p class="text-secondary">{{ item.reason }} · {{ formatDate(item.suppressed_at) }}</p>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
