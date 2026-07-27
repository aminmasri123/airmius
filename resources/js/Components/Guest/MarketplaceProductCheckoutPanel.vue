<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({
    flashSuccess: { type: String, default: '' },
    product: { type: Object, required: true },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    profileAddress: { type: Object, default: null },
    isAuthenticated: { type: Boolean, default: false },
    form: { type: Object, required: true },
    price: { type: Object, required: true },
    visiblePriceCents: { type: Number, default: 0 },
    checkoutSteps: { type: Array, default: () => [] },
    checkoutStep: { type: String, default: 'address' },
    checkoutStepHint: { type: String, default: '' },
    selectedPaymentProvider: { type: Object, default: null },
    selectedProviderLabel: { type: String, default: '' },
    addressChoice: { type: String, default: 'new' },
    savedAddressOptions: { type: Array, default: () => [] },
    deliveryCountryOptions: { type: Array, default: () => [] },
    checkoutUnavailable: { type: Boolean, default: false },
    paymentProviderItems: { type: Array, default: () => [] },
    canContinueAddress: { type: Boolean, default: false },
    canContinuePayment: { type: Boolean, default: false },
    maxQuantity: { type: Number, default: 1 },
    selectedQuantity: { type: Number, default: 1 },
    selectedItemGrossCents: { type: Number, default: 0 },
    selectedTotalGrossCents: { type: Number, default: 0 },
    formatMoney: { type: Function, required: true },
    isCheckoutStepDisabled: { type: Function, required: true },
    goToCheckoutStep: { type: Function, required: true },
    updateCountry: { type: Function, required: true },
    nextCheckoutStep: { type: Function, required: true },
    normalizeQuantity: { type: Function, required: true },
    previousCheckoutStep: { type: Function, required: true },
    checkout: { type: Function, required: true },
    addToCart: { type: Function, required: true },
})
const emit = defineEmits(['update:addressChoice'])
const updateAddressChoice = (event) => emit('update:addressChoice', event.target.value)
</script>
<template>
                        <div v-if="flashSuccess" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
                            {{ flashSuccess }}
                        </div>
                        <p class="text-xs uppercase text-secondary">{{ $t("Preis") }}</p>
                        <p class="mt-2 text-3xl font-bold text-primary">{{ formatMoney(visiblePriceCents, price.currency) }}</p>
                        <div class="mt-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                            <p>
                                {{ formatMoney(price.net_cents, price.currency) }} {{ $t('netto') }}
                            </p>
                            <p>
                                {{ formatMoney(price.tax_cents, price.currency) }} {{ price.tax_label }} ({{ price.tax_rate }}%)
                            </p>
                            <p v-if="price.is_estimate" class="mt-2 text-xs">
                                {{ $t("Steuer/Währung sind eine technische Schätzung und werden beim finalen Checkout geprüft.") }}
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <button
                                v-for="(step, index) in checkoutSteps"
                                :key="step.key"
                                type="button"
                                class="rounded-lg border px-2 py-3 text-center text-[11px] font-black transition sm:text-xs"
                                :class="checkoutStep === step.key
                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                    : 'border-border bg-bg text-secondary hover:border-buttonPrimary hover:text-primary'"
                                :disabled="isCheckoutStepDisabled(step.key)"
                                @click="goToCheckoutStep(step.key)"
                            >
                                <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full border border-current/30">
                                    <i :class="[step.icon, 'text-base']"></i>
                                </span>
                                <span class="block">{{ index + 1 }}. {{ step.label }}</span>
                            </button>
                        </div>
                        <p v-if="checkoutStepHint" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs font-semibold text-warning">
                            {{ checkoutStepHint }}
                        </p>

                        <form class="mt-6 space-y-4" @submit.prevent="checkout">
                            <section v-show="checkoutStep === 'address'" class="space-y-4">
                            <div v-if="isAuthenticated" class="rounded-lg border border-border bg-bg p-3">
                                <label class="text-xs font-bold uppercase text-secondary">{{ $t("Adresse") }}</label>
                                <select :value="addressChoice" @change="updateAddressChoice" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-if="profileAddress" value="profile">
                                        {{ $t('Meine Adresse') }}{{ profileAddress.summary ? ` - ${profileAddress.summary}` : '' }}
                                    </option>
                                    <option v-for="address in savedAddressOptions" :key="address.id" :value="`saved:${address.id}`">
                                        {{ address.label }}{{ address.summary ? ` - ${address.summary}` : '' }}
                                    </option>
                                    <option value="new">{{ $t("Neue Lieferadresse") }}</option>
                                </select>
                            </div>

                            <div class="rounded-lg border border-buttonPrimary/20 bg-buttonPrimary/10 p-3 text-sm text-primary">
                                <p class="flex items-center gap-2 font-black">
                                    <i class="las la-lock text-lg text-buttonPrimary"></i>
                                    {{ $t("Sicherer Checkout") }}
                                </p>
                                <div class="mt-3 grid gap-2 text-xs font-semibold text-secondary">
                                    <p class="flex items-center gap-2">
                                        <i :class="[selectedPaymentProvider?.icon || 'las la-credit-card', 'text-base text-buttonPrimary']"></i>
                                        {{ $t('Zahlungsart:') }} {{ selectedProviderLabel || $t('Nicht konfiguriert') }}
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <i class="las la-file-invoice text-base text-buttonPrimary"></i>
                                        {{ $t("Preis, Steuer und Versand werden vor Abschluss angezeigt.") }}
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <i class="las la-envelope text-base text-buttonPrimary"></i>
                                        {{ $t("Bestellstatus und Rechnung kommen per E-Mail.") }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Land der Lieferadresse") }}</label>
                                <select v-model="form.shipping_country" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" @change="updateCountry">
                                    <option v-for="country in deliveryCountryOptions" :key="country.country" :value="country.country">
                                        {{ country.label }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-secondary">{{ $t("Die Steuer wird daraus automatisch berechnet.") }}</p>
                                <p v-if="form.errors.shipping_country" class="mt-1 text-sm text-red-400">{{ form.errors.shipping_country }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Straße") }}</label>
                                    <input v-model="form.shipping_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping street-address" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Nr.") }}</label>
                                    <input v-model="form.shipping_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-line2" />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("PLZ") }}</label>
                                    <input v-model="form.shipping_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping postal-code" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Ort") }}</label>
                                    <input v-model="form.shipping_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="shipping address-level2" />
                                </div>
                            </div>

                            <template v-if="isAuthenticated">
                                <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                    <input v-model="form.save_shipping_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                    <span>{{ $t("Diese Lieferadresse speichern") }}</span>
                                </label>
                                <input
                                    v-if="form.save_shipping_address"
                                    v-model="form.shipping_address_label"
                                    class="w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                                    :placeholder="$t('Name der Lieferadresse, z. B. Zuhause')"
                                >
                            </template>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Name") }}</label>
                                <input v-model="form.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="name" />
                                <p v-if="form.errors.guest_name" class="mt-1 text-sm text-red-400">{{ form.errors.guest_name }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kundentyp") }}</label>
                                <select v-model="form.customer_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                    <option value="consumer">{{ $t("Privatkunde") }}</option>
                                    <option value="business">{{ $t("Firma / Verein") }}</option>
                                </select>
                            </div>

                            <div v-if="form.customer_type === 'business'" class="space-y-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Firma / Verein") }}</label>
                                    <input v-model="form.customer_company" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" autocomplete="organization" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">{{ $t("USt-IdNr.") }}</label>
                                    <input v-model="form.customer_vat_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary uppercase" :placeholder="$t('z. B. ATU...')" />
                                </div>
                            </div>

                            <div v-if="!isAuthenticated">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("E-Mail") }}</label>
                                <input v-model="form.guest_email" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required autocomplete="email" />
                                <p v-if="form.errors.guest_email" class="mt-1 text-sm text-red-400">{{ form.errors.guest_email }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Zahlungsart") }}</label>
                                <select v-model="form.provider" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :disabled="checkoutUnavailable">
                                    <option v-if="checkoutUnavailable" value="">{{ $t("Keine Zahlungsart konfiguriert") }}</option>
                                    <option v-for="provider in paymentProviderItems" :key="provider.value" :value="provider.value">
                                        {{ provider.label }}
                                    </option>
                                </select>
                                <p v-if="selectedPaymentProvider?.description" class="mt-1 text-xs text-secondary">{{ selectedPaymentProvider.description }}</p>
                                <p v-if="form.errors.provider" class="mt-1 text-sm text-red-400">{{ form.errors.provider }}</p>
                            </div>

                            <button
                                type="button"
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canContinueAddress"
                                @click="nextCheckoutStep"
                            >
                                {{ $t("Weiter zu Menge & Preis") }}
                            </button>
                            </section>

                            <section v-show="checkoutStep === 'payment'" class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Menge") }}</label>
                                <input
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="1"
                                    :max="maxQuantity"
                                    @input="normalizeQuantity"
                                    @change="normalizeQuantity"
                                    @blur="normalizeQuantity"
                                    class="mt-1 h-12 w-full rounded-lg border-border bg-inputBg text-primary"
                                />
                                <p class="mt-1 text-xs text-secondary">{{ $t('Verfügbar:') }} {{ maxQuantity }}</p>
                                <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-400">{{ form.errors.quantity }}</p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="previousCheckoutStep"
                                >
                                    {{ $t("Zurück") }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!canContinuePayment"
                                    @click="nextCheckoutStep"
                                >
                                    {{ $t("Prüfen") }}
                                </button>
                            </div>
                            </section>

                            <section v-show="checkoutStep === 'review'" class="space-y-4">
                            <div class="rounded-lg border border-buttonPrimary/20 bg-buttonPrimary/10 p-3 text-xs font-semibold text-secondary">
                                <p class="mb-2 text-sm font-black text-primary">{{ $t("Bestellung prüfen") }}</p>
                                <div class="grid gap-2">
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Lieferland") }}</span>
                                        <span class="text-right text-primary">{{ form.shipping_country }}</span>
                                    </p>
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Zahlungsart") }}</span>
                                        <span class="text-right text-primary">{{ selectedProviderLabel }}</span>
                                    </p>
                                    <p class="flex justify-between gap-3">
                                        <span>{{ $t("Menge") }}</span>
                                        <span class="text-right text-primary">{{ selectedQuantity }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <p class="flex justify-between gap-3">
                                    <span>{{ $t("Zwischensumme") }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(selectedItemGrossCents, price.currency) }}</span>
                                </p>
                                <p class="mt-1 flex justify-between gap-3">
                                    <span>{{ price.shipping_label || 'Versand' }}</span>
                                    <span class="font-semibold text-primary">{{ formatMoney(price.shipping_gross_cents, price.currency) }}</span>
                                </p>
                                <p class="mt-3 flex justify-between gap-3 border-t border-border pt-3 text-base font-black text-primary">
                                    <span>{{ $t("Gesamt") }}</span>
                                    <span>{{ formatMoney(selectedTotalGrossCents, price.currency) }}</span>
                                </p>
                                <p v-if="price.reverse_charge" class="mt-2 text-xs text-air-blue">{{ $t("Reverse-Charge: Steuerschuld geht auf den Leistungsempfänger über.") }}</p>
                                <p v-else-if="price.tax_rule === 'export_outside_eu'" class="mt-2 text-xs text-air-blue">{{ $t("Export außerhalb der EU: keine EU-MwSt. berechnet.") }}</p>
                            </div>

                            <div v-if="product.learning_course_id" class="rounded-lg border border-border bg-bg p-3">
                                <label class="text-xs font-semibold uppercase text-secondary">{{ $t("Kurs-Gutschein") }}</label>
                                <input v-model="form.coupon_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Code eingeben')">
                                <p v-if="form.errors.coupon_code" class="mt-1 text-sm text-red-400">{{ form.errors.coupon_code }}</p>
                            </div>

                            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                                <span>
                                    {{ $t("Ich akzeptiere") }}
                                    <Link :href="route('terms.show')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("AGB") }}</Link>
                                    {{ $t("und") }}
                                    <Link :href="route('legal.withdrawal')" target="_blank" rel="noopener noreferrer" class="font-semibold text-air-blue underline underline-offset-2" @click.stop>{{ $t("Widerrufshinweise") }}</Link>.
                                    Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.
                                </span>
                            </label>
                            <p v-if="form.errors.accepted_terms" class="text-sm text-red-400">{{ form.errors.accepted_terms }}</p>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="previousCheckoutStep"
                            >
                                {{ $t("Zurück zu Menge") }}
                            </button>

                            <button
                                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                :disabled="form.processing || !form.accepted_terms"
                                :class="{ 'opacity-60': form.processing || !form.accepted_terms }"
                            >
                                {{ $t("Jetzt kaufen") }}
                            </button>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                                @click="addToCart"
                            >
                                {{ $t("In den Einkaufswagen") }}
                            </button>

                            <Link v-if="isAuthenticated" :href="route('auth.commerce.cart.index')" class="hidden text-center text-sm font-semibold text-air-blue">
                                Einkaufswagen ansehen ({{ cart.items_count || 0 }})
                            </Link>

                            <Link v-else :href="route('login')" class="hidden text-center text-sm font-semibold text-air-blue">
                                {{ $t("Mit Konto anmelden") }}
                            </Link>
                            </section>
                        </form>
</template>
