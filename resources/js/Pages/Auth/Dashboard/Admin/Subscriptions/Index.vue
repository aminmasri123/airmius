<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { centsToMajor, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    userSubscriptions: { type: Array, default: () => [] },
    pendingBankTransfers: { type: Array, default: () => [] },
})

const page = usePage()
const selectedActor = ref('all')
const clubSearch = ref('')
const userSearch = ref('')
const editingPlanId = ref(null)
const clubForms = ref({})
const userForms = ref({})
const planForms = ref({})

const actorLabels = {
    all: 'Alle',
    sportler: 'Sportler',
    trainer: 'Trainer',
    verein: 'Vereine',
    eltern: 'Eltern',
    sponsor: 'Sponsoren',
    anbieter: 'Anbieter',
    enterprise: 'Enterprise',
}

const actorIcons = {
    all: 'las la-layer-group',
    sportler: 'las la-running',
    trainer: 'las la-chalkboard-teacher',
    verein: 'las la-users',
    eltern: 'las la-shield-alt',
    sponsor: 'las la-bullhorn',
    anbieter: 'las la-store',
    enterprise: 'las la-network-wired',
}

const statusLabels = {
    trialing: 'Testphase',
    active: 'Aktiv',
    past_due: 'Zahlung offen',
    cancelled: 'Gekündigt',
    cancels_at_period_end: 'Gekündigt zum Periodenende',
}

const actors = computed(() => [
    'all',
    ...Object.keys(actorLabels).filter((actor) => actor !== 'all' && props.plans.some((plan) => plan.target_actor === actor)),
])

const filteredPlans = computed(() => {
    if (selectedActor.value === 'all') return props.plans
    return props.plans.filter((plan) => plan.target_actor === selectedActor.value)
})

const clubPlans = computed(() => props.plans.filter((plan) => plan.target_actor === 'verein'))
const userPlans = computed(() => props.plans.filter((plan) => plan.target_actor !== 'verein'))
const selectedUserPlans = computed(() => {
    if (selectedActor.value === 'all' || selectedActor.value === 'verein') {
        return userPlans.value
    }

    return props.plans.filter((plan) => plan.target_actor === selectedActor.value)
})

const filteredClubs = computed(() => {
    const query = clubSearch.value.trim().toLowerCase()
    if (!query) return props.clubs

    return props.clubs.filter((club) =>
        `${club.name} ${club.plan?.name || ''}`.toLowerCase().includes(query),
    )
})

const subscriptionForUser = (user) => {
    if (selectedActor.value === 'all' || selectedActor.value === 'verein') return null

    return (user.subscriptions || []).find((subscription) => subscription.plan?.target_actor === selectedActor.value) || null
}

const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase()
    if (!query) return props.users

    return props.users.filter((user) => {
        const subscription = subscriptionForUser(user)

        return `${user.name || ''} ${user.first_name || ''} ${user.last_name || ''} ${user.email || ''} ${subscription?.plan?.name || ''}`
            .toLowerCase()
            .includes(query)
    })
})

const summary = computed(() => ({
    plans: props.plans.length,
    publicPlans: props.plans.filter((plan) => plan.is_public && plan.is_active).length,
    clubSubscriptions: props.plans.reduce((total, plan) => total + Number(plan.club_subscriptions_count || 0), 0),
    userSubscriptions: props.plans.reduce((total, plan) => total + Number(plan.user_subscriptions_count || 0), 0),
}))

const formatPrice = (cents, currency = 'EUR') => {
    const value = Number(cents || 0) / 100
    return value
        ? new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(value)
        : `0 ${currency}`
}

const limitLabel = (value, suffix = '') => value ? `${value}${suffix}` : 'Unbegrenzt'
const displayUserName = (user) => user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || '-'

const formForPlan = (plan) => {
    planForms.value[plan.id] ??= useForm({
        target_actor: plan.target_actor || 'verein',
        description: plan.description || '',
        monthly_price_cents: centsToMajor(plan.monthly_price_cents),
        yearly_price_cents: centsToMajor(plan.yearly_price_cents),
        member_limit: plan.member_limit || '',
        team_limit: plan.team_limit || '',
        storage_gb: plan.storage_gb || 1,
        minimum_term_months: plan.minimum_term_months ?? 0,
        cancellation_notice_days: plan.cancellation_notice_days ?? 0,
        cta_label: plan.cta_label || '',
        badge: plan.badge || '',
        is_public: Boolean(plan.is_public),
        is_active: Boolean(plan.is_active),
        country_prices: (plan.country_prices || []).map((price) => ({
            country_code: price.country_code || 'DE',
            currency: price.currency || plan.currency || 'EUR',
            monthly_price_cents: centsToMajor(price.monthly_price_cents ?? plan.monthly_price_cents),
            yearly_price_cents: centsToMajor(price.yearly_price_cents ?? plan.yearly_price_cents),
            is_active: Boolean(price.is_active),
        })),
    })

    return planForms.value[plan.id]
}

const addCountryPrice = (plan) => {
    formForPlan(plan).country_prices.push({
        country_code: 'CH',
        currency: 'CHF',
        monthly_price_cents: centsToMajor(plan.monthly_price_cents),
        yearly_price_cents: centsToMajor(plan.yearly_price_cents),
        is_active: true,
    })
}

const removeCountryPrice = (plan, index) => {
    formForPlan(plan).country_prices.splice(index, 1)
}

const formForClub = (club) => {
    clubForms.value[club.id] ??= useForm({
        subscription_plan_id: club.plan?.id || clubPlans.value[0]?.id || '',
        status: club.subscription?.status || 'active',
        trial_ends_at: club.subscription?.trial_ends_at || '',
        current_period_ends_at: club.subscription?.current_period_ends_at || '',
        payment_provider: club.subscription?.payment_provider || '',
    })

    return clubForms.value[club.id]
}

const formForUserSubscription = (subscription) => {
    userForms.value[subscription.id] ??= useForm({
        user_subscription_id: subscription.id,
        subscription_plan_id: subscription.plan?.id || userPlans.value[0]?.id || '',
        status: subscription.status || 'active',
        trial_ends_at: subscription.trial_ends_at || '',
        current_period_ends_at: subscription.current_period_ends_at || '',
        payment_provider: subscription.payment_provider || '',
    })

    return userForms.value[subscription.id]
}

const formForUser = (user) => {
    const subscription = subscriptionForUser(user)
    const key = `${selectedActor.value}-${user.id}`

    userForms.value[key] ??= useForm({
        user_subscription_id: subscription?.id || '',
        subscription_plan_id: subscription?.plan?.id || selectedUserPlans.value[0]?.id || '',
        status: subscription?.status || 'active',
        trial_ends_at: subscription?.trial_ends_at || '',
        current_period_ends_at: subscription?.current_period_ends_at || '',
        payment_provider: subscription?.payment_provider || '',
    })

    return userForms.value[key]
}

const savePlan = (plan) => {
    formForPlan(plan)
        .transform((data) => ({
            ...transformMoneyFields(data, ['monthly_price_cents', 'yearly_price_cents']),
            country_prices: (data.country_prices || []).map((price) => transformMoneyFields(price, ['monthly_price_cents', 'yearly_price_cents'])),
        }))
        .put(route('admin.subscription-plans.update', plan.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPlanId.value = null
        },
        })
}

const saveClub = (club) => {
    formForClub(club).put(route('admin.clubs.subscription.update', club.id), {
        preserveScroll: true,
    })
}

const saveUserSubscription = (subscription) => {
    formForUserSubscription(subscription).put(route('admin.users.subscription.update', subscription.user.id), {
        preserveScroll: true,
    })
}

const saveUser = (user) => {
    formForUser(user).put(route('admin.users.subscription.update', user.id), {
        preserveScroll: true,
    })
}

const cancelClubSubscription = (club, mode = 'period_end') => {
    if (!club.subscription) return
    router.post(route('admin.club-subscriptions.cancel', club.subscription.id), { mode }, { preserveScroll: true })
}

const renewClubSubscription = (club, months = 1) => {
    if (!club.subscription) return
    router.post(route('admin.club-subscriptions.renew', club.subscription.id), { months }, { preserveScroll: true })
}

const cancelUserSubscription = (subscription, mode = 'period_end') => {
    if (!subscription) return
    router.post(route('admin.user-subscriptions.cancel', subscription.id), { mode }, { preserveScroll: true })
}

const renewUserSubscription = (subscription, months = 1) => {
    if (!subscription) return
    router.post(route('admin.user-subscriptions.renew', subscription.id), { months }, { preserveScroll: true })
}

const markTransferPaid = (checkout) => {
    router.post(route('admin.subscription-checkouts.mark-paid', checkout.id), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Abo-Verwaltung" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Business Model</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Abo-Verwaltung</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Preise, Zielgruppen, Limits und Vereinszuordnungen zentral verwalten.
                    </p>
                </div>

                <a
                    :href="route('guest.pricing')"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                >
                    <i class="las la-external-link-alt"></i>
                    Preisseite ansehen
                </a>
            </div>

            <div v-if="page.props.flash?.success" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Pläne</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.plans }}</p>
                <p class="text-sm text-secondary">{{ summary.publicPlans }} aktiv und öffentlich</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Vereins-Abos</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.clubSubscriptions }}</p>
                <p class="text-sm text-secondary">zugeordneten Vereinen</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Nutzer-Abos</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ summary.userSubscriptions }}</p>
                <p class="text-sm text-secondary">Sportler, Trainer und Partner</p>
            </div>
            <div class="surface-card p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Vereine</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ clubs.length }}</p>
                <p class="text-sm text-secondary">für Plan-Zuordnung</p>
            </div>
        </section>

        <section class="surface-card p-3">
            <div class="flex gap-2 overflow-x-auto">
                <button
                    v-for="actor in actors"
                    :key="actor"
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="selectedActor === actor ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary hover:text-primary'"
                    @click="selectedActor = actor"
                >
                    <i :class="actorIcons[actor]"></i>
                    {{ actorLabels[actor] }}
                </button>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article v-for="plan in filteredPlans" :key="plan.id" class="surface-card overflow-hidden">
                <div class="border-b border-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-primary">{{ plan.name }}</h2>
                                <span v-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ plan.badge }}</span>
                            </div>
                            <p class="mt-1 text-xs text-secondary">{{ actorLabels[plan.target_actor] || plan.target_actor }} - {{ plan.slug }}</p>
                        </div>
                        <button type="button" class="min-h-10 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:border-borderHover" @click="editingPlanId = editingPlanId === plan.id ? null : plan.id">
                            {{ editingPlanId === plan.id ? 'Schließen' : 'Bearbeiten' }}
                        </button>
                    </div>
                </div>

                <div v-if="editingPlanId !== plan.id" class="space-y-4 p-4">
                    <p class="min-h-12 text-sm leading-relaxed text-secondary">{{ plan.description }}</p>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Monat</p>
                            <p class="mt-1 font-bold text-primary">{{ formatPrice(plan.monthly_price_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Jahr</p>
                            <p class="mt-1 font-bold text-primary">{{ formatPrice(plan.yearly_price_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Mitglieder</p>
                            <p class="mt-1 font-bold text-primary">{{ limitLabel(plan.member_limit) }}</p>
                        </div>
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Speicher</p>
                            <p class="mt-1 font-bold text-primary">{{ plan.storage_gb }} GB</p>
                        </div>
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Mindestlaufzeit</p>
                            <p class="mt-1 font-bold text-primary">{{ Number(plan.minimum_term_months || 0) }} Monate</p>
                        </div>
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Kündigungsfrist</p>
                            <p class="mt-1 font-bold text-primary">{{ Number(plan.cancellation_notice_days || 0) }} Tage</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full px-2 py-1 font-semibold" :class="plan.is_active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'">
                            {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                        <span class="rounded-full px-2 py-1 font-semibold" :class="plan.is_public ? 'bg-air-blue/15 text-air-blue' : 'bg-muted text-secondary'">
                            {{ plan.is_public ? 'Öffentlich' : 'Privat' }}
                        </span>
                        <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                            {{ plan.club_subscriptions_count }} Vereine
                        </span>
                        <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                            {{ plan.user_subscriptions_count }} Nutzer
                        </span>
                        <span class="rounded-full bg-muted px-2 py-1 font-semibold text-secondary">
                                    {{ plan.country_prices?.length || 0 }} Länderpreise
                        </span>
                    </div>
                </div>

                <form v-else class="space-y-4 p-4" @submit.prevent="savePlan(plan)">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Zielgruppe</label>
                        <select v-model="formForPlan(plan).target_actor" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="sportler">Sportler</option>
                            <option value="trainer">Trainer</option>
                            <option value="verein">Verein</option>
                            <option value="eltern">Eltern</option>
                            <option value="sponsor">Sponsor</option>
                            <option value="anbieter">Anbieter</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Beschreibung</label>
                        <textarea v-model="formForPlan(plan).description" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Monat</label>
                            <input v-model="formForPlan(plan).monthly_price_cents" v-bind="moneyInputAttrs" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="10,99">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Jahr</label>
                            <input v-model="formForPlan(plan).yearly_price_cents" v-bind="moneyInputAttrs" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="99,00">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Mitglieder</label>
                            <input v-model="formForPlan(plan).member_limit" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="leer = unbegrenzt">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Teams</label>
                            <input v-model="formForPlan(plan).team_limit" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="leer = unbegrenzt">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Speicher GB</label>
                            <input v-model="formForPlan(plan).storage_gb" type="number" min="1" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Mindestlaufzeit Monate</label>
                            <input v-model="formForPlan(plan).minimum_term_months" type="number" min="0" max="60" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Kündigungsfrist Tage</label>
                            <input v-model="formForPlan(plan).cancellation_notice_days" type="number" min="0" max="365" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Badge</label>
                            <input v-model="formForPlan(plan).badge" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-semibold uppercase text-secondary">CTA</label>
                            <input v-model="formForPlan(plan).cta_label" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="formForPlan(plan).is_public" type="checkbox" class="rounded border-border bg-inputBg">
                            Öffentlich
                        </label>
                        <label class="flex items-center gap-2 text-sm text-primary">
                            <input v-model="formForPlan(plan).is_active" type="checkbox" class="rounded border-border bg-inputBg">
                            Aktiv
                        </label>
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                            <p class="text-xs font-semibold uppercase text-secondary">Länderpreise</p>
                                <p class="mt-1 text-xs text-secondary">Land, Währung und Preis pro Plan steuern.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="addCountryPrice(plan)">
                                + Land
                            </button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div
                                v-for="(price, index) in formForPlan(plan).country_prices"
                                :key="`${plan.id}-${index}`"
                                class="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[4rem_5rem_1fr_1fr_auto]"
                            >
                                <input v-model="price.country_code" maxlength="2" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="DE">
                                <input v-model="price.currency" maxlength="3" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="EUR">
                                <input v-model="price.monthly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Monat">
                                <input v-model="price.yearly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Jahr">
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 text-xs text-primary">
                                        <input v-model="price.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                        Aktiv
                                    </label>
                                    <button type="button" class="rounded-lg border border-border px-2 py-1 text-xs text-primary hover:bg-muted" @click="removeCountryPrice(plan, index)">
                                        Entfernen
                                    </button>
                                </div>
                            </div>
                            <p v-if="!formForPlan(plan).country_prices.length" class="text-xs text-secondary">
                            Ohne Länderpreis wird der Standardpreis des Plans verwendet.
                            </p>
                        </div>
                    </div>

                    <button class="w-full rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="formForPlan(plan).processing">
                        Speichern
                    </button>
                </form>
            </article>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Offene Überweisungen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Airmius-Abos, die per Banküberweisung gebucht wurden und noch manuell bestätigt werden müssen.
                        </p>
                    </div>
                    <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                        {{ pendingBankTransfers.length }} offen
                    </span>
                </div>
            </div>

            <div class="custom-scrollbar overflow-x-auto">
                <table v-if="pendingBankTransfers.length" class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Referenz</th>
                            <th class="px-5 py-3">Kunde</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Betrag</th>
                            <th class="px-5 py-3">Fällig</th>
                            <th class="px-5 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="checkout in pendingBankTransfers" :key="checkout.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ checkout.payment_reference }}</p>
                                <p class="text-xs text-secondary">Checkout {{ checkout.id }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ checkout.club?.name || checkout.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ checkout.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ checkout.plan?.name || '-' }}</td>
                            <td class="px-5 py-3 font-semibold text-primary">{{ checkout.amount }}</td>
                            <td class="px-5 py-3 text-secondary">{{ checkout.due_at || '-' }}</td>
                            <td class="px-5 py-3 text-right">
                                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="markTransferPaid(checkout)">
                                    Als bezahlt markieren
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="px-5 py-8 text-sm text-secondary">
                    Keine offenen Überweisungen.
                </p>
            </div>
        </section>

        <section v-if="selectedActor === 'all' || selectedActor === 'verein'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Vereins-Abos</h2>
                        <p class="mt-1 text-sm text-secondary">Ordne Vereinen einen Vereinsplan zu und behalte Nutzung und Limits im Blick.</p>
                    </div>
                    <div class="relative w-full lg:w-80">
                        <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                        <input v-model="clubSearch" type="search" class="w-full rounded-lg border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary" placeholder="Verein oder Plan suchen">
                    </div>
                </div>
            </div>

            <div class="custom-scrollbar overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Verein</th>
                            <th class="px-5 py-3">Nutzung</th>
                            <th class="px-5 py-3">Aktueller Plan</th>
                            <th class="px-5 py-3">Neuer Plan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Laufzeit</th>
                            <th class="px-5 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="club in filteredClubs" :key="club.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ club.name }}</p>
                                <p class="text-xs text-secondary">ID {{ club.id }}</p>
                            </td>
                            <td class="px-5 py-3 text-secondary">
                                <p>{{ club.member_usage }} Mitglieder</p>
                                <p class="text-xs">{{ club.teams_count }} Teams</p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">
                                    {{ club.plan?.name || 'Free' }}
                                </span>
                                <p v-if="club.subscription?.payment_provider" class="mt-1 text-xs text-secondary">{{ club.subscription.payment_provider }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForClub(club).subscription_plan_id" class="min-w-40 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="plan in clubPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                                </select>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForClub(club).status" class="min-w-36 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="trialing">Testphase</option>
                                    <option value="active">Aktiv</option>
                                    <option value="past_due">Zahlung offen</option>
                                    <option value="cancels_at_period_end">Gekündigt zum Ende</option>
                                    <option value="cancelled">Gekündigt</option>
                                </select>
                                <p class="mt-1 text-xs text-secondary">{{ statusLabels[formForClub(club).status] }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid min-w-44 gap-2">
                                    <input v-model="formForClub(club).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary" title="Testphase bis">
                                    <input v-model="formForClub(club).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary" title="Aktuelle Periode bis">
                                </div>
                                <p v-if="club.subscription?.cancels_at" class="mt-1 text-xs text-warning">Endet {{ club.subscription.cancels_at }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" :disabled="!club.subscription" @click="renewClubSubscription(club, 1)">
                                        +1M
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" :disabled="!club.subscription" @click="cancelClubSubscription(club, 'period_end')">
                                        Kündigen
                                    </button>
                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForClub(club).processing" @click="saveClub(club)">
                                        Speichern
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!filteredClubs.length" class="px-5 py-8 text-sm text-secondary">
                    Keine Vereine gefunden.
                </p>
            </div>
        </section>

        <section v-if="selectedActor !== 'all' && selectedActor !== 'verein'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ actorLabels[selectedActor] }}-Abos zuordnen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Liste alle Nutzer und ordne ihnen manuell einen passenden {{ actorLabels[selectedActor] }}-Plan zu.
                        </p>
                    </div>
                    <div class="relative w-full lg:w-80">
                        <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                        <input v-model="userSearch" type="search" class="w-full rounded-lg border-border bg-inputBg py-2 pl-10 pr-3 text-sm text-primary" placeholder="Nutzer, E-Mail oder Plan suchen">
                    </div>
                </div>
            </div>

            <div class="custom-scrollbar overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Nutzer</th>
                            <th class="px-5 py-3">Aktueller Plan</th>
                            <th class="px-5 py-3">Neuer Plan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Laufzeit</th>
                            <th class="px-5 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ displayUserName(user) }}</p>
                                <p class="text-xs text-secondary">{{ user.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">
                                    {{ subscriptionForUser(user)?.plan?.name || 'Kein Abo' }}
                                </span>
                                <p v-if="subscriptionForUser(user)?.payment_provider" class="mt-1 text-xs text-secondary">{{ subscriptionForUser(user).payment_provider }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForUser(user).subscription_plan_id" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="plan in selectedUserPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                                </select>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForUser(user).status" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="trialing">Testphase</option>
                                    <option value="active">Aktiv</option>
                                    <option value="past_due">Zahlung offen</option>
                                    <option value="cancels_at_period_end">Gekündigt zum Ende</option>
                                    <option value="cancelled">Gekündigt</option>
                                </select>
                                <input v-model="formForUser(user).payment_provider" class="mt-2 min-w-44 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Zahlungsart">
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid min-w-44 gap-2">
                                    <input v-model="formForUser(user).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                    <input v-model="formForUser(user).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                </div>
                                <p v-if="subscriptionForUser(user)?.cancels_at" class="mt-1 text-xs text-warning">Endet {{ subscriptionForUser(user).cancels_at }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!subscriptionForUser(user)" @click="renewUserSubscription(subscriptionForUser(user), 1)">
                                        +1M
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted disabled:opacity-50" :disabled="!subscriptionForUser(user)" @click="cancelUserSubscription(subscriptionForUser(user), 'period_end')">
                                    Kündigen
                                    </button>
                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForUser(user).processing || !selectedUserPlans.length" @click="saveUser(user)">
                                        Speichern
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!filteredUsers.length" class="px-5 py-8 text-sm text-secondary">
                    Keine Nutzer gefunden.
                </p>
                <p v-else-if="!selectedUserPlans.length" class="px-5 pb-5 text-sm text-warning">
                    Für {{ actorLabels[selectedActor] }} sind noch keine Abo-Pläne vorhanden.
                </p>
            </div>
        </section>

        <section v-if="selectedActor === 'all'" class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Nutzer-Abos</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Persönliche Pläne für Sportler, Trainer, Sponsoren, Anbieter und weitere Rollen verwalten.
                    </p>
                </div>
            </div>

            <div class="custom-scrollbar overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-bg text-xs uppercase text-secondary">
                        <tr>
                            <th class="px-5 py-3">Nutzer</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Laufzeit</th>
                            <th class="px-5 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="subscription in userSubscriptions" :key="subscription.id" class="hover:bg-muted/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-primary">{{ subscription.user?.name || '-' }}</p>
                                <p class="text-xs text-secondary">{{ subscription.user?.email || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForUserSubscription(subscription).subscription_plan_id" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="plan in userPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                                </select>
                                <p class="mt-1 text-xs text-secondary">{{ subscription.plan?.target_actor || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="formForUserSubscription(subscription).status" class="min-w-44 rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="trialing">Testphase</option>
                                    <option value="active">Aktiv</option>
                                    <option value="past_due">Zahlung offen</option>
                                    <option value="cancels_at_period_end">Gekündigt zum Ende</option>
                                    <option value="cancelled">Gekündigt</option>
                                </select>
                                <input v-model="formForUserSubscription(subscription).payment_provider" class="mt-2 min-w-44 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Zahlungsart">
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid min-w-44 gap-2">
                                    <input v-model="formForUserSubscription(subscription).trial_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                    <input v-model="formForUserSubscription(subscription).current_period_ends_at" type="date" class="rounded-lg border-border bg-inputBg text-xs text-primary">
                                </div>
                                <p v-if="subscription.cancels_at" class="mt-1 text-xs text-warning">Endet {{ subscription.cancels_at }}</p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="renewUserSubscription(subscription, 1)">
                                        +1M
                                    </button>
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="cancelUserSubscription(subscription, 'period_end')">
                                        Kündigen
                                    </button>
                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForUserSubscription(subscription).processing" @click="saveUserSubscription(subscription)">
                                        Speichern
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!userSubscriptions.length" class="px-5 py-8 text-sm text-secondary">
                    Noch keine Nutzer-Abos vorhanden.
                </p>
            </div>
        </section>
    </div>
</template>
