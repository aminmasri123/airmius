<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    cart: {
        type: Object,
        default: () => ({ items: [], summary: {} }),
    },
    cartItemCount: {
        type: Number,
        default: 0,
    },
    cartItems: {
        type: Array,
        default: () => [],
    },
    formatMoney: {
        type: Function,
        required: true,
    },
})

defineEmits(['checkout', 'remove-item', 'update-item'])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border bg-card p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Warenkorb</p>
                    <h2 class="mt-1 text-2xl font-bold text-primary">Deine ausgewählten Produkte</h2>
                    <p class="mt-1 text-sm text-secondary">Hier erscheinen nur Artikel, die du bewusst in den Einkaufswagen gelegt hast.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary">
                        {{ cartItemCount }} Artikel
                    </span>
                    <button
                        class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                        :disabled="!cartItems.length"
                        @click="$emit('checkout')"
                    >
                        Zur Kasse
                    </button>
                </div>
            </div>
        </div>
        <div class="divide-y divide-border">
            <div v-for="item in cartItems" :key="item.id" class="grid gap-4 p-5 md:grid-cols-[5rem_minmax(0,1fr)_8rem_auto] md:items-center">
                <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="block overflow-hidden rounded-lg border border-border bg-inputBg">
                    <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.product.title" width="160" height="160" loading="lazy" decoding="async" class="aspect-square h-full w-full object-cover">
                    <div v-else class="flex aspect-square items-center justify-center">
                        <i class="las la-store text-3xl text-air-blue"></i>
                    </div>
                </Link>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold uppercase text-secondary">{{ item.product?.category || 'Produkt' }}</span>
                        <span v-if="item.product?.sku" class="text-xs text-secondary">Art.-Nr. {{ item.product.sku }}</span>
                    </div>
                    <Link :href="item.product?.show_url || route('auth.commerce.products.show', item.product?.id)" class="mt-2 block break-words font-semibold text-primary hover:text-air-blue">
                        {{ item.product?.title }}
                    </Link>
                    <p class="mt-1 line-clamp-2 text-sm text-secondary">{{ item.product?.description }}</p>
                    <p class="mt-2 text-xs font-semibold text-success">Verfügbar: {{ item.product?.stock_quantity }} Stück</p>
                </div>
                <input
                    :value="item.quantity"
                    type="number"
                    min="1"
                    :max="item.product?.stock_quantity || 1"
                    class="rounded-lg border-border bg-inputBg text-sm font-semibold text-primary"
                    @change="$emit('update-item', item, Number($event.target.value || 1))"
                >
                <div class="flex items-center justify-between gap-3 md:block md:text-right">
                    <p class="text-lg font-bold text-primary">{{ formatMoney(item.line_total_cents, item.product?.currency || cart.summary?.currency || 'EUR') }}</p>
                    <button class="mt-0 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error hover:bg-error/10 md:mt-3" @click="$emit('remove-item', item)">
                        Entfernen
                    </button>
                </div>
            </div>
            <div v-if="cartItems.length" class="grid gap-3 bg-bg p-5 text-sm text-secondary sm:grid-cols-4">
                <p class="rounded-lg border border-border bg-card p-3">Warenwert<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.item_gross_cents, cart.summary?.currency) }}</span></p>
                <p class="rounded-lg border border-border bg-card p-3">Versand<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.shipping_cents, cart.summary?.currency) }}</span></p>
                <p class="rounded-lg border border-border bg-card p-3">Steuer<br><span class="font-semibold text-primary">{{ formatMoney(cart.summary?.tax_cents, cart.summary?.currency) }}</span></p>
                <p class="rounded-lg border border-border bg-card p-3">Gesamt<br><span class="text-lg font-bold text-primary">{{ formatMoney(cart.summary?.amount_cents, cart.summary?.currency) }}</span></p>
            </div>
            <div v-else class="grid gap-4 p-8 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                    <i class="las la-shopping-bag text-3xl text-air-blue"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-primary">Dein Warenkorb ist leer</h3>
                    <p class="mt-1 text-sm text-secondary">Füge ein Marketplace-Produkt hinzu, dann erscheint es hier.</p>
                </div>
                <Link :href="route('guest.marketplace')" class="mx-auto rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Marketplace ansehen
                </Link>
            </div>
        </div>
    </section>
</template>
