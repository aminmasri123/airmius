<script setup>
defineProps({
    cart: {
        type: Object,
        default: () => ({ summary: {} }),
    },
    cartItems: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
    formatMoney: {
        type: Function,
        default: (value) => value,
    },
    open: {
        type: Boolean,
        default: false,
    },
    pricingCountries: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'submit'])
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form class="max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('submit')">
            <h2 class="text-lg font-semibold text-primary">{{ $t('commerce.checkout.title') }}</h2>
            <p class="mt-2 text-sm text-secondary">
                {{ $t('commerce.ui.total') }}: {{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}
            </p>

            <div class="mt-4 rounded-xl border border-border bg-bg p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('commerce.checkout.selected_products') }}</p>
                <div class="mt-3 space-y-3">
                    <div v-for="item in cartItems" :key="`checkout-${item.id}`" class="flex items-center gap-3">
                        <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" class="h-12 w-12 rounded-lg object-cover">
                        <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-inputBg">
                            <i class="las la-store text-xl text-air-blue"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-primary">{{ item.product?.title }}</p>
                            <p class="text-xs text-secondary">{{ item.quantity }} x {{ formatMoney(item.product?.price_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                        </div>
                        <p class="text-sm font-bold text-primary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <select v-model="form.shipping_country" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option v-for="country in pricingCountries" :key="country.country" :value="country.country">{{ country.label }}</option>
                </select>
                <select v-model="form.provider" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="bank_transfer">{{ $t('commerce.payment.bank_transfer') }}</option>
                    <option value="stripe">{{ $t('commerce.payment.stripe') }}</option>
                    <option value="paypal">{{ $t('commerce.payment.paypal') }}</option>
                </select>
                <select v-model="form.customer_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="consumer">{{ $t('commerce.checkout.consumer') }}</option>
                    <option value="business">{{ $t('commerce.checkout.business') }}</option>
                </select>
                <input v-if="form.customer_type === 'business'" v-model="form.customer_vat_id" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" :placeholder="$t('commerce.checkout.vat_id_placeholder')">
                <input v-if="form.customer_type === 'business'" v-model="form.customer_company" class="rounded-lg border-border bg-inputBg text-sm text-primary sm:col-span-2" :placeholder="$t('commerce.checkout.company_placeholder')">
                <input v-model="form.shipping_street" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.checkout.street_placeholder')">
                <input v-model="form.shipping_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.checkout.house_number_placeholder')">
                <input v-model="form.shipping_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.checkout.postal_code_placeholder')">
                <input v-model="form.shipping_city" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.checkout.city_placeholder')">
            </div>

            <label class="mt-4 flex items-start gap-3 text-sm text-secondary">
                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>{{ $t('commerce.checkout.accept_terms') }}</span>
            </label>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    {{ $t('commerce.ui.cancel') }}
                </button>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                    {{ $t('commerce.ui.buy') }}
                </button>
            </div>
        </form>
    </div>
</template>
