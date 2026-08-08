<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    plan: { type: Object, required: true },
    acceptedTerms: { type: Boolean, default: false },
    acceptedContract: { type: Boolean, default: false },
    paymentProvider: { type: String, default: 'bank_transfer' },
    shippingName: { type: String, default: '' },
    shippingCountry: { type: String, default: 'DE' },
    shippingStreet: { type: String, default: '' },
    shippingHouseNumber: { type: String, default: '' },
    shippingPostalCode: { type: String, default: '' },
    shippingCity: { type: String, default: '' },
    shippingState: { type: String, default: '' },
    shippingNote: { type: String, default: '' },
    hasShippingAddress: { type: Boolean, default: false },
    subscribingPlanId: { type: [Number, String], default: null },
    formatMoney: { type: Function, required: true },
    planContractRules: { type: Function, required: true },
    planContractTerms: { type: Function, required: true },
})

const emit = defineEmits([
    'update:acceptedTerms',
    'update:acceptedContract',
    'update:paymentProvider',
    'update:shippingName',
    'update:shippingCountry',
    'update:shippingStreet',
    'update:shippingHouseNumber',
    'update:shippingPostalCode',
    'update:shippingCity',
    'update:shippingState',
    'update:shippingNote',
    'close',
    'confirm',
])

const model = (prop, event) => computed({
    get: () => props[prop],
    set: (value) => emit(event, value),
})

const acceptedTermsModel = model('acceptedTerms', 'update:acceptedTerms')
const acceptedContractModel = model('acceptedContract', 'update:acceptedContract')
const paymentProviderModel = model('paymentProvider', 'update:paymentProvider')
const shippingNameModel = model('shippingName', 'update:shippingName')
const shippingCountryModel = model('shippingCountry', 'update:shippingCountry')
const shippingStreetModel = model('shippingStreet', 'update:shippingStreet')
const shippingHouseNumberModel = model('shippingHouseNumber', 'update:shippingHouseNumber')
const shippingPostalCodeModel = model('shippingPostalCode', 'update:shippingPostalCode')
const shippingCityModel = model('shippingCity', 'update:shippingCity')
const shippingStateModel = model('shippingState', 'update:shippingState')
const shippingNoteModel = model('shippingNote', 'update:shippingNote')
</script>

<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-card p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo bestätigen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">{{ plan.name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Nach deiner Bestätigung wird das Abo als Zahlung offen vorgemerkt. Es wird erst aktiviert und beliefert, wenn die Zahlung bestätigt ist.
                    </p>
                </div>
                <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="$emit('close')">
                    <i class="las la-times text-xl"></i>
                </button>
            </div>

            <div class="mt-5 grid gap-3 rounded-lg border border-border bg-inputBg p-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sm text-secondary">Monatsbetrag</span>
                    <span class="text-lg font-bold text-primary">{{ formatMoney(plan.effective_monthly_price_cents, plan.currency) }}</span>
                </div>
                <div v-if="plan.sponsor_discount_cents" class="flex items-center justify-between gap-4">
                    <span class="text-sm text-secondary">Sponsor-Rabatt</span>
                    <span class="text-sm font-semibold text-success">- {{ formatMoney(plan.sponsor_discount_cents, plan.currency) }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sm text-secondary">Status nach Klick</span>
                    <span class="text-sm font-semibold text-warning">Zahlung offen</span>
                </div>
            </div>

            <label class="mt-4 block">
                <span class="text-sm font-semibold text-primary">Zahlungsart</span>
                <select v-model="paymentProviderModel" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="bank_transfer">Überweisung</option>
                    <option value="paypal">{{ t('outfit_workspace.payment.paypal') }}</option>
                </select>
                <span class="mt-1 block text-xs text-secondary">
                    Die Zahlung wird danach vorbereitet. Das Abo bleibt bis zur Zahlungsbestätigung offen.
                </span>
            </label>

            <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                <p class="text-sm font-bold text-primary">Lieferadresse</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ t('outfit_ui.name') }}</span>
                        <input v-model="shippingNameModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Vor- und Nachname">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Straße</span>
                        <input v-model="shippingStreetModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Straße">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Hausnummer</span>
                        <input v-model="shippingHouseNumberModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12a">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Land</span>
                        <input v-model="shippingCountryModel" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm uppercase text-primary" placeholder="DE">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">PLZ</span>
                        <input v-model="shippingPostalCodeModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="12345">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">Stadt</span>
                        <input v-model="shippingCityModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Berlin">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Bundesland / Region</span>
                        <input v-model="shippingStateModel" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Optional">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Lieferhinweis</span>
                        <textarea v-model="shippingNoteModel" rows="2" class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary" placeholder="Optional, z.B. bei Nachbarn abgeben"></textarea>
                    </label>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-primary">Outfit-Abo-Vertrag</p>
                        <p class="mt-1 text-xs leading-5 text-secondary">
                            Diese Bedingungen gelten für diesen Abschluss und werden mit deiner Anfrage gespeichert.
                        </p>
                    </div>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">
                        Version {{ planContractRules(plan).version || 'aktuell' }}
                    </span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-secondary">Mindestlaufzeit</dt>
                        <dd class="font-semibold text-primary">{{ planContractRules(plan).minimum_term_months }} Monate</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-secondary">Pause möglich ab</dt>
                        <dd class="font-semibold text-primary">Monat {{ planContractRules(plan).pause_allowed_after_months }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-secondary">Kündigungsfrist</dt>
                        <dd class="font-semibold text-primary">{{ planContractRules(plan).cancellation_notice_days }} Tage</dd>
                    </div>
                </dl>
                <ul class="mt-4 space-y-2 text-xs leading-5 text-secondary">
                    <li v-for="term in planContractTerms(plan)" :key="term">- {{ term }}</li>
                </ul>
            </div>

            <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                <input v-model="acceptedContractModel" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    Ich akzeptiere den Outfit-Abo-Vertrag inkl. Mindestlaufzeit, Pausen-, Liefer- und Kündigungsregeln.
                </span>
            </label>

            <label class="mt-4 flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-secondary">
                <input v-model="acceptedTermsModel" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    Ich akzeptiere
                    <a :href="route('terms.show')" target="_blank" rel="noopener noreferrer" class="font-semibold text-accent underline underline-offset-2" @click.stop>
                        AGB
                    </a>
                    und
                    <a :href="route('legal.withdrawal')" target="_blank" rel="noopener noreferrer" class="font-semibold text-accent underline underline-offset-2" @click.stop>
                        Widerrufshinweise
                    </a>
                    und weiß, dass das Abo erst nach Zahlungsbestätigung aktiv wird.
                </span>
            </label>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="$emit('close')">
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50"
                    :disabled="!acceptedTerms || !acceptedContract || !hasShippingAddress || subscribingPlanId === plan.id"
                    @click="$emit('confirm')"
                >
                    {{ subscribingPlanId === plan.id ? 'Wird gesendet...' : 'Kostenpflichtig anfragen' }}
                </button>
            </div>
        </div>
    </div>
</template>

