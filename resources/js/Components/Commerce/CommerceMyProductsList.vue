<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    myProducts: { type: Array, default: () => [] },
    formatDateTime: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    offerTypeLabel: { type: Function, required: true },
    productStatusLabel: { type: Function, required: true },
})

const emit = defineEmits([
    'open-edit-product-modal',
    'update-own-product-status',
    'open-delete-product-modal',
])
</script>

<template>
    <p class="mt-4 text-sm text-secondary">{{ myProducts.length }} eigene Angebote</p>

    <div class="mt-4 space-y-3">
        <article
            v-for="product in myProducts"
            :key="product.id"
            class="rounded-lg border border-border bg-bg p-4"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-primary">{{ product.title }}</h3>
                        <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                            {{ offerTypeLabel(product.offer_type, product.category) }}
                        </span>
                        <span class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">
                            {{ productStatusLabel(product.status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-secondary">
                        {{ product.sku || 'Keine Artikelnummer' }} · erstellt {{ formatDateTime(product.created_at) }}
                    </p>
                    <p v-if="product.rejection_reason" class="mt-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                        {{ product.rejection_reason }}
                    </p>
                </div>
                <div class="shrink-0 text-left sm:text-right">
                    <p class="font-semibold text-primary">{{ formatMoney(product.price_cents, product.currency) }}</p>
                    <p class="text-xs text-secondary">
                        <span v-if="product.manages_stock">Bestand: {{ product.stock_quantity ?? 0 }}</span>
                        <span v-else>Kein Lagerlimit</span>
                    </p>
                    <div v-if="product.inventories?.length" class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="inventory in product.inventories.filter((row) => row.is_active)"
                            :key="inventory.id"
                            class="rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary"
                        >
                            {{ inventory.country_code }}: {{ inventory.stock_quantity }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <Link :href="route('auth.commerce.products.show', product.id)" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary">
                    Details
                </Link>
                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('open-edit-product-modal', product)">
                    Bearbeiten
                </button>
                <button
                    v-if="product.status !== 'review'"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                    @click="emit('update-own-product-status', product, 'review')"
                >
                    Zur Prüfung
                </button>
                <button
                    v-if="product.status !== 'draft'"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                    @click="emit('update-own-product-status', product, 'draft')"
                >
                    Entwurf
                </button>
                <button
                    v-if="product.status !== 'archived'"
                    type="button"
                    class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning"
                    @click="emit('update-own-product-status', product, 'archived')"
                >
                    Archivieren
                </button>
                <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="emit('open-delete-product-modal', product)">
                    Löschen
                </button>
            </div>
        </article>

        <p v-if="!myProducts.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
            Noch keine eigenen Angebote eingereicht.
        </p>
    </div>
</template>


