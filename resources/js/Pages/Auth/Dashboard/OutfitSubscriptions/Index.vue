<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    styleProfile: { type: Object, default: null },
    subscriptions: { type: Array, default: () => [] },
})

const profileForm = useForm({
    sport_focus: props.styleProfile?.sport_focus || '',
    sizes_text: (props.styleProfile?.sizes || []).join(', '),
    fit_preference: props.styleProfile?.fit_preference || 'regular',
    colors_text: (props.styleProfile?.colors || []).join(', '),
    excluded_colors_text: (props.styleProfile?.excluded_colors || []).join(', '),
    brand_style: props.styleProfile?.brand_style || 'minimal',
    notes: props.styleProfile?.notes || '',
})

const activeSubscriptions = computed(() => props.subscriptions.filter((subscription) => subscription.status !== 'cancelled'))

const toList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatDate = (value) => {
    if (!value) return 'Noch nicht geplant'

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const statusLabel = (status) => ({
    active: 'Aktiv',
    paused: 'Pausiert',
    cancelled: 'Gekuendigt',
    planned: 'Geplant',
    preparing: 'In Vorbereitung',
    shipped: 'Versendet',
    delivered: 'Geliefert',
    skipped: 'Ausgesetzt',
}[status] || status)

const updateProfile = () => {
    profileForm.transform((data) => ({
        sport_focus: data.sport_focus,
        sizes: toList(data.sizes_text),
        fit_preference: data.fit_preference,
        colors: toList(data.colors_text),
        excluded_colors: toList(data.excluded_colors_text),
        brand_style: data.brand_style,
        notes: data.notes,
    })).put(route('auth.outfit-subscriptions.profile.update'), {
        preserveScroll: true,
    })
}

const subscribe = (plan) => {
    router.post(route('auth.outfit-subscriptions.store', plan.id), {}, { preserveScroll: true })
}

const pause = (subscription) => router.post(route('auth.outfit-subscriptions.pause', subscription.id), {}, { preserveScroll: true })
const resume = (subscription) => router.post(route('auth.outfit-subscriptions.resume', subscription.id), {}, { preserveScroll: true })
const cancel = (subscription) => {
    if (confirm('Dieses Outfit-Abo wirklich kuendigen?')) {
        router.post(route('auth.outfit-subscriptions.cancel', subscription.id), {}, { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Outfit-Abo" />

    <div class="space-y-6 p-4 sm:p-6">
        <section class="overflow-hidden rounded-lg border border-border bg-card">
            <div class="grid gap-6 p-5 lg:grid-cols-[1.2fr_.8fr] lg:p-6">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Sportkleidung monatlich</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary sm:text-3xl">Personalisierte Outfit-Abos</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-secondary">
                        Waehle einen Plan, pflege dein Style-Profil und erhalte regelmaessig Sport-Outfits passend zu Sportart, Groesse, Farben und Markenstil.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Aktive Abos</p>
                        <p class="mt-2 text-2xl font-bold text-primary">{{ activeSubscriptions.length }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Verfuegbare Plaene</p>
                        <p class="mt-2 text-2xl font-bold text-primary">{{ plans.length }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[.85fr_1.15fr]">
            <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="updateProfile">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Style-Profil</h2>
                        <p class="mt-1 text-sm text-secondary">Diese Angaben steuern die Zusammenstellung deiner Boxen.</p>
                    </div>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="profileForm.processing">
                        Speichern
                    </button>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sportfokus</span>
                        <input v-model="profileForm.sport_focus" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Laufen, Fitness, Fussball" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Passform</span>
                        <select v-model="profileForm.fit_preference" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="slim">Slim</option>
                            <option value="regular">Regular</option>
                            <option value="relaxed">Locker</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Groessen</span>
                        <input v-model="profileForm.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="M, L, 42" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Lieblingsfarben</span>
                        <input v-model="profileForm.colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Schwarz, Blau, Weiss" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Ausschlussfarben</span>
                        <input v-model="profileForm.excluded_colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Gelb, Pink" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Stil</span>
                        <select v-model="profileForm.brand_style" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="minimal">Minimal</option>
                            <option value="bold">Auffaellig</option>
                            <option value="classic">Klassisch</option>
                            <option value="team">Team-orientiert</option>
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-primary">Notizen</span>
                        <textarea v-model="profileForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Materialwuensche, Marken, No-Gos, besondere Hinweise"></textarea>
                    </label>
                </div>
            </form>

            <div class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-bold text-primary">Deine Abos und Lieferungen</h2>
                <div v-if="subscriptions.length" class="mt-5 space-y-4">
                    <article v-for="subscription in subscriptions" :key="subscription.id" class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-base font-bold text-primary">{{ subscription.plan?.name || 'Outfit-Abo' }}</p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ statusLabel(subscription.status) }} · Naechste Lieferung: {{ formatDate(subscription.next_delivery_at) }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.monthly_price_cents, subscription.currency) }} / Monat</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="subscription.status === 'active'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="pause(subscription)">Pausieren</button>
                                <button v-if="subscription.status === 'paused'" type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-card" @click="resume(subscription)">Fortsetzen</button>
                                <button v-if="subscription.status !== 'cancelled'" type="button" class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="cancel(subscription)">Kuendigen</button>
                            </div>
                        </div>

                        <div v-if="subscription.deliveries?.length" class="mt-4 grid gap-3 md:grid-cols-2">
                            <div v-for="delivery in subscription.deliveries" :key="delivery.id" class="rounded-lg bg-card p-3">
                                <p class="text-sm font-semibold text-primary">{{ statusLabel(delivery.status) }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(delivery.delivery_month) }}</p>
                                <p v-if="delivery.tracking_number" class="mt-1 text-xs text-secondary">{{ delivery.carrier }} · {{ delivery.tracking_number }}</p>
                                <p v-if="delivery.notes" class="mt-1 text-xs text-secondary">{{ delivery.notes }}</p>
                            </div>
                        </div>
                    </article>
                </div>
                <p v-else class="mt-5 rounded-lg bg-inputBg p-4 text-sm text-secondary">Noch kein Outfit-Abo aktiv.</p>
            </div>
        </section>

        <section>
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-primary">Plaene waehlen</h2>
                    <p class="mt-1 text-sm text-secondary">Sponsor-Subventionen werden direkt vom Monatsbetrag abgezogen.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="plan in plans" :key="plan.id" class="flex flex-col rounded-lg border border-border bg-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                            <p v-if="plan.sponsor" class="mt-1 text-xs font-semibold text-accent">Subventioniert von {{ plan.sponsor.name }}</p>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ plan.items_per_box }} Teile</span>
                    </div>
                    <p class="mt-4 min-h-16 text-sm leading-6 text-secondary">{{ plan.description }}</p>
                    <div class="mt-4 rounded-lg bg-inputBg p-4">
                        <p v-if="plan.sponsor_discount_cents" class="text-xs text-secondary">
                            Statt {{ formatMoney(plan.monthly_price_cents, plan.currency) }} · Rabatt {{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}
                        </p>
                        <p class="text-2xl font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</p>
                        <p class="text-xs text-secondary">pro Monat</p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="sport in plan.sports" :key="sport" class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">{{ sport }}</span>
                        <span v-if="plan.branding_type !== 'none'" class="rounded-full bg-accent/15 px-3 py-1 text-xs text-accent">{{ plan.branding_type }}</span>
                    </div>
                    <button type="button" class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="subscribe(plan)">
                        Plan auswaehlen
                    </button>
                </article>
            </div>
        </section>
    </div>
</template>
