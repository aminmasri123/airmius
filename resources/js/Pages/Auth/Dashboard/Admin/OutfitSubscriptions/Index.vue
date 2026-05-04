<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    plans: { type: Array, default: () => [] },
    sponsors: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const editingPlanId = ref(null)
const editForms = ref({})

const newPlan = useForm({
    sponsor_id: '',
    name: '',
    description: '',
    monthly_price_eur: 29,
    sponsor_discount_eur: 0,
    currency: 'EUR',
    target_gender: 'unisex',
    sizes_text: 'XS, S, M, L, XL',
    sports_text: 'Laufen, Fitness',
    items_per_box: 3,
    branding_type: 'none',
    sort_order: 0,
    is_public: true,
    is_active: true,
})

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const toList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const cents = (value) => Math.max(0, Math.round(Number(String(value).replace(',', '.')) * 100))

const payload = (form) => ({
    sponsor_id: form.sponsor_id || null,
    name: form.name,
    description: form.description,
    monthly_price_cents: cents(form.monthly_price_eur),
    sponsor_discount_cents: cents(form.sponsor_discount_eur),
    currency: form.currency,
    target_gender: form.target_gender,
    sizes: toList(form.sizes_text),
    sports: toList(form.sports_text),
    items_per_box: Number(form.items_per_box || 1),
    branding_type: form.branding_type,
    sort_order: Number(form.sort_order || 0),
    is_public: Boolean(form.is_public),
    is_active: Boolean(form.is_active),
})

const storePlan = () => {
    newPlan.transform(payload).post(route('admin.outfit-subscription-plans.store'), {
        preserveScroll: true,
        onSuccess: () => newPlan.reset(),
    })
}

const formFor = (plan) => {
    if (!editForms.value[plan.id]) {
        editForms.value[plan.id] = useForm({
            sponsor_id: plan.sponsor_id || '',
            name: plan.name,
            description: plan.description || '',
            monthly_price_eur: Number(plan.monthly_price_cents || 0) / 100,
            sponsor_discount_eur: Number(plan.sponsor_discount_cents || 0) / 100,
            currency: plan.currency || 'EUR',
            target_gender: plan.target_gender || 'unisex',
            sizes_text: (plan.sizes || []).join(', '),
            sports_text: (plan.sports || []).join(', '),
            items_per_box: plan.items_per_box || 3,
            branding_type: plan.branding_type || 'none',
            sort_order: plan.sort_order || 0,
            is_public: Boolean(plan.is_public),
            is_active: Boolean(plan.is_active),
        })
    }

    return editForms.value[plan.id]
}

const savePlan = (plan) => {
    formFor(plan).transform(payload).put(route('admin.outfit-subscription-plans.update', plan.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPlanId.value = null
        },
    })
}

const destroyPlan = (plan) => {
    if (confirm('Plan wirklich loeschen oder deaktivieren?')) {
        router.delete(route('admin.outfit-subscription-plans.destroy', plan.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Admin Outfit-Abos" />

    <div class="space-y-6 p-4 sm:p-6">
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Sportkleidung-Abo Modul</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary">Outfit-Abo Plaene</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Verwalte monatliche Sportkleidung-Abos, Sponsor-Rabatte und Branding-Regeln. Diese Seite ist ueber eigene Permissions geschuetzt.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Plaene</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.plans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Aktiv</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.activePlans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Sponsor</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.sponsoredPlans || 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-inputBg p-3">
                        <p class="text-xs uppercase text-secondary">Abos</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ summary.subscriptions || 0 }}</p>
                    </div>
                </div>
            </div>
        </section>

        <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="storePlan">
            <h2 class="text-lg font-bold text-primary">Neuen Plan erstellen</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="text-sm font-semibold text-primary">Name</span>
                    <input v-model="newPlan.name" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Runner Box" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Preis EUR</span>
                    <input v-model="newPlan.monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor-Rabatt EUR</span>
                    <input v-model="newPlan.sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block xl:col-span-2">
                    <span class="text-sm font-semibold text-primary">Beschreibung</span>
                    <textarea v-model="newPlan.description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor</span>
                    <select v-model="newPlan.sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Kein Sponsor</option>
                        <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Branding</span>
                    <select v-model="newPlan.branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="none">Kein Branding</option>
                        <option value="sponsor_logo">Sponsor-Logo</option>
                        <option value="club_logo">Vereinslogo</option>
                        <option value="custom">Individuell</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Zielgruppe</span>
                    <select v-model="newPlan.target_gender" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="unisex">Unisex</option>
                        <option value="women">Damen</option>
                        <option value="men">Herren</option>
                        <option value="kids">Kinder</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Teile pro Box</span>
                    <input v-model="newPlan.items_per_box" type="number" min="1" max="12" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Groessen</span>
                    <input v-model="newPlan.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sportarten</span>
                    <input v-model="newPlan.sports_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="newPlan.is_public" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Oeffentlich</span>
                </label>
                <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                    <input v-model="newPlan.is_active" type="checkbox" class="rounded border-border bg-card" />
                    <span class="text-sm text-primary">Aktiv</span>
                </label>
            </div>
            <button class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="newPlan.processing">
                Plan erstellen
            </button>
        </form>

        <section class="grid gap-4 xl:grid-cols-2">
            <article v-for="plan in plans" :key="plan.id" class="rounded-lg border border-border bg-card p-5">
                <template v-if="editingPlanId !== plan.id">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">{{ plan.name }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ plan.description }}</p>
                            <p v-if="plan.sponsor" class="mt-2 text-sm font-semibold text-accent">Sponsor: {{ plan.sponsor.name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-inputBg" @click="editingPlanId = plan.id">Bearbeiten</button>
                            <button class="rounded-lg border border-red-500/50 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10" @click="destroyPlan(plan)">Entfernen</button>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Preis</p>
                            <p class="font-bold text-primary">{{ formatMoney(plan.monthly_price_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Rabatt</p>
                            <p class="font-bold text-primary">{{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Box</p>
                            <p class="font-bold text-primary">{{ plan.items_per_box }} Teile</p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-3">
                            <p class="text-xs uppercase text-secondary">Abos</p>
                            <p class="font-bold text-primary">{{ plan.subscriptions_count || 0 }}</p>
                        </div>
                    </div>
                </template>

                <form v-else class="grid gap-4 md:grid-cols-2" @submit.prevent="savePlan(plan)">
                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-primary">Name</span>
                        <input v-model="formFor(plan).name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-primary">Beschreibung</span>
                        <textarea v-model="formFor(plan).description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Preis EUR</span>
                        <input v-model="formFor(plan).monthly_price_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Rabatt EUR</span>
                        <input v-model="formFor(plan).sponsor_discount_eur" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sponsor</span>
                        <select v-model="formFor(plan).sponsor_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="">Kein Sponsor</option>
                            <option v-for="sponsor in sponsors" :key="sponsor.id" :value="sponsor.id">{{ sponsor.name }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Branding</span>
                        <select v-model="formFor(plan).branding_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="none">Kein Branding</option>
                            <option value="sponsor_logo">Sponsor-Logo</option>
                            <option value="club_logo">Vereinslogo</option>
                            <option value="custom">Individuell</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Groessen</span>
                        <input v-model="formFor(plan).sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-primary">Sportarten</span>
                        <input v-model="formFor(plan).sports_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </label>
                    <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                        <input v-model="formFor(plan).is_public" type="checkbox" class="rounded border-border bg-card" />
                        <span class="text-sm text-primary">Oeffentlich</span>
                    </label>
                    <label class="flex items-center gap-2 rounded-lg bg-inputBg p-3">
                        <input v-model="formFor(plan).is_active" type="checkbox" class="rounded border-border bg-card" />
                        <span class="text-sm text-primary">Aktiv</span>
                    </label>
                    <div class="flex gap-2 md:col-span-2">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary" @click="editingPlanId = null">Abbrechen</button>
                    </div>
                </form>
            </article>
        </section>
    </div>
</template>
