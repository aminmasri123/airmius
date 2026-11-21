<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    products: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    stockAdjustments: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    inventoryAdjustment: { type: Function, required: true },
})

const selected = ref([])
const dialog = ref(null)
const visibleIds = computed(() => props.products.map(({ id }) => id))
const selectedIds = computed(() => selected.value.filter((id) => visibleIds.value.includes(id)))
const form = useForm({ product_ids: [], action: 'discount', discount_percent: null, status: 'draft', rejection_reason: '' })
const confirmed = ref(false)

function openBulkActions() {
    form.reset()
    form.clearErrors()
    form.product_ids = [...selectedIds.value]
    confirmed.value = false
    dialog.value.showModal()
}

function applyBulkAction() {
    if (form.processing || (form.action === 'delete' && !confirmed.value)) return

    form.post(route('admin.commerce.products.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = []
            dialog.value.close()
        },
    })
}

const emit = defineEmits([
    'set-inventory-adjustment',
    'adjust-inventory-stock',
    'update-product-stock',
    'adjust-global-stock',
    'update-product-status',
    'open-edit-product',
    'open-delete-product',
])
</script>

<template>
    <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div v-for="warehouse in warehouses" :key="warehouse.id" class="rounded-lg border border-border bg-bg p-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-primary">{{ warehouse.name }}</p>
                    <p class="text-xs text-secondary">{{ warehouse.country_code }} · {{ warehouse.city || 'Ohne Stadt' }}</p>
                </div>
                <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                    {{ warehouse.active_inventories_count || 0 }} aktiv
                </span>
            </div>
            <p class="mt-2 text-xs text-secondary">Inventories gesamt: {{ warehouse.inventories_count || 0 }}</p>
        </div>
    </div>

    <div class="mt-5 space-y-3">
        <div v-if="products.length" class="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" :checked="selectedIds.length === products.length"
                    :indeterminate.prop="selectedIds.length > 0 && selectedIds.length < products.length"
                    @change="selected = $event.target.checked ? [...visibleIds] : []">
                Alle auf dieser Seite auswählen
            </label>
            <span class="text-secondary" aria-live="polite">{{ selectedIds.length }} ausgewählt</span>
            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 font-semibold text-buttonTextPrimary disabled:opacity-50"
                :disabled="!selectedIds.length || selectedIds.length > 100" @click="openBulkActions">
                Sammelaktion
            </button>
            <span v-if="selectedIds.length > 100" class="text-warning">Maximal 100 Produkte pro Aktion.</span>
        </div>
        <div v-for="product in products" :key="product.id" class="rounded-lg border border-border p-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <input v-model="selected" type="checkbox" :value="product.id"
                    :aria-label="product.title + ' auswählen'" class="shrink-0 self-start md:self-center">
                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-inputBg">
                    <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center">
                        <i class="las la-store text-2xl text-air-blue"></i>
                    </div>
                </div>
                <div>
                    <p class="font-semibold text-primary">{{ product.title }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-secondary">{{ product.status }} · {{ product.moderation_status }} · {{ product.product_type || 'single' }} · {{ formatMoney(product.price_cents) }}</span>
                        <span
                            :class="[
                                'rounded-full px-2 py-0.5 font-semibold',
                                product.quality_score >= 86 ? 'bg-success/10 text-success' : (product.quality_score >= 72 ? 'bg-warning/10 text-warning' : 'bg-error/10 text-error')
                            ]"
                        >
                            Qualität {{ product.quality_score ?? 0 }}%
                        </span>
                    </div>
                    <p v-if="product.seller_name" class="mt-1 text-xs text-secondary">
                        Anbieter {{ product.seller_name }}
                        <span v-if="product.seller_verified" class="font-semibold text-success">· verifiziert</span>
                    </p>
                    <p class="text-xs text-secondary">
                        Artikelnummer {{ product.sku || '-' }} · Steuer {{ product.tax_class || 'standard' }} ·
                        <span v-if="product.manages_stock">Bestand {{ product.stock_quantity ?? 0 }}</span>
                        <span v-else>Bestand nicht verwaltet</span>
                    </p>
                    <p v-if="product.features?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                        Merkmale: {{ product.features.join(' | ') }}
                    </p>
                    <p v-if="product.product_attributes?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                        Eigenschaften: {{ product.product_attributes.map((attribute) => `${attribute.name}: ${attribute.value}`).join(' | ') }}
                    </p>
                    <p v-if="product.variants?.length" class="mt-1 line-clamp-1 text-xs text-secondary">
                        Varianten: {{ product.variants.length }}
                    </p>
                    <div v-if="product.quality_issues?.length" class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="issue in product.quality_issues"
                            :key="`${product.id}-${issue}`"
                            class="rounded bg-warning/10 px-2 py-1 text-[11px] font-semibold text-warning"
                        >
                            {{ issue }}
                        </span>
                    </div>
                    <div v-if="product.inventories?.length" class="mt-3 grid gap-2">
                        <div
                            v-for="inventory in product.inventories.filter((row) => row.is_active)"
                            :key="inventory.id"
                            class="grid gap-2 rounded-lg border border-border bg-card p-2 lg:grid-cols-[minmax(0,1fr)_5rem_5rem_7rem_7rem_auto]"
                        >
                            <div>
                                <p class="text-xs font-semibold text-primary">{{ inventory.country_code }} · {{ inventory.warehouse?.name || 'Lager' }}</p>
                                <p class="text-[11px] text-secondary">{{ inventory.warehouse?.city || 'Ort offen' }} · Lieferzeit {{ inventory.lead_time_days ?? '-' }} Tage</p>
                            </div>
                            <span class="rounded bg-muted px-2 py-2 text-xs font-semibold text-secondary">Bestand {{ inventory.stock_quantity }}</span>
                            <span class="rounded bg-muted px-2 py-2 text-xs font-semibold text-secondary">Frei {{ inventory.available_quantity }}</span>
                            <input
                                :value="inventoryAdjustment(product, inventory).quantity_delta"
                                type="number"
                                class="rounded-lg border-border bg-inputBg text-xs text-primary"
                                placeholder="+/-"
                                @input="emit('set-inventory-adjustment', product, inventory, 'quantity_delta', $event.target.value)"
                            >
                            <input
                                :value="inventoryAdjustment(product, inventory).note"
                                class="rounded-lg border-border bg-inputBg text-xs text-primary"
                                placeholder="Notiz"
                                @input="emit('set-inventory-adjustment', product, inventory, 'note', $event.target.value)"
                            >
                            <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('adjust-inventory-stock', product, inventory)">
                                Buchen
                            </button>
                        </div>
                    </div>
                    <p v-if="product.rejection_reason" class="mt-1 text-xs text-warning">{{ product.rejection_reason }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-if="product.manages_stock" class="flex items-center gap-2">
                        <input v-model.number="product.stock_quantity" type="number" min="0" class="w-24 rounded-lg border-border bg-inputBg text-xs text-primary" placeholder="Bestand">
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-product-stock', product)">Bestand speichern</button>
                        <input
                            :value="(stockAdjustments[`${product.id}:global`] || {}).quantity_delta"
                            type="number"
                            class="w-20 rounded-lg border-border bg-inputBg text-xs text-primary"
                            placeholder="+/-"
                            @input="stockAdjustments[`${product.id}:global`] = { ...(stockAdjustments[`${product.id}:global`] || {}), quantity_delta: $event.target.value }"
                        >
                        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('adjust-global-stock', product)">
                            Global buchen
                        </button>
                    </div>
                    <select v-model="product.status" class="rounded-lg border-border bg-inputBg text-xs font-semibold text-primary" @change="emit('update-product-status', product, product.status)">
                        <option value="draft">Entwurf</option>
                        <option value="review">Prüfen</option>
                        <option value="published">Freigegeben</option>
                        <option value="archived">Archiviert</option>
                    </select>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('open-edit-product', product)">Bearbeiten</button>
                    <button type="button" class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('update-product-status', product, 'rejected')">Ablehnen</button>
                    <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="emit('open-delete-product', product)">Löschen</button>
                </div>
            </div>
        </div>
        <p v-if="!products.length" class="text-sm text-secondary">Noch keine Produkte vorbereitet.</p>
    </div>
    <dialog ref="dialog" aria-labelledby="product-bulk-title" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-border bg-card p-5 text-primary shadow-xl backdrop:bg-black/60" @cancel.prevent="!form.processing && dialog.close()">
        <form class="space-y-4" @submit.prevent="applyBulkAction">
            <h2 id="product-bulk-title" class="text-lg font-semibold">Sammelaktion für {{ form.product_ids.length }} Produkte</h2>
            <label class="grid gap-1 text-sm">
                Aktion
                <select v-model="form.action" :disabled="form.processing" class="rounded-lg border-border bg-inputBg" @change="form.clearErrors(); confirmed = false">
                    <option value="discount">Rabatt anwenden</option>
                    <option value="status">Status ändern</option>
                    <option value="archive">Archivieren</option>
                    <option value="delete">Löschen</option>
                </select>
            </label>
            <template v-if="form.action === 'discount'">
                <label class="grid gap-1 text-sm">
                    Rabatt in Prozent
                    <input v-model.number="form.discount_percent" type="number" min="1" max="100" required :disabled="form.processing" class="rounded-lg border-border bg-inputBg">
                </label>
                <p class="text-sm text-secondary">Senkt die aktuellen Produktpreise und expliziten Variantenpreise dauerhaft.</p>
            </template>
            <template v-if="form.action === 'status'">
                <label class="grid gap-1 text-sm">
                    Neuer Status
                    <select v-model="form.status" :disabled="form.processing" class="rounded-lg border-border bg-inputBg">
                        <option value="draft">Entwurf</option>
                        <option value="review">Zur Prüfung</option>
                        <option value="published">Freigeben</option>
                        <option value="rejected">Ablehnen</option>
                    </select>
                </label>
                <label v-if="form.status === 'rejected'" class="grid gap-1 text-sm">
                    Ablehnungsgrund
                    <textarea v-model="form.rejection_reason" required maxlength="2000" :disabled="form.processing" class="rounded-lg border-border bg-inputBg" />
                </label>
            </template>
            <label v-if="form.action === 'delete'" class="flex items-start gap-2 text-sm">
                <input v-model="confirmed" type="checkbox" :disabled="form.processing" class="mt-0.5">
                Produkte ohne Bestellungen endgültig löschen. Produkte mit Bestellungen werden archiviert.
            </label>
            <ul v-if="form.hasErrors" role="alert" class="space-y-1 text-sm text-danger">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
            </ul>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" :disabled="form.processing" class="rounded-lg border border-border px-3 py-2 text-sm" @click="dialog.close()">Abbrechen</button>
                <button type="submit" :disabled="form.processing || (form.action === 'delete' && !confirmed)"
                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                    {{ form.action === 'delete' ? 'Produkte löschen' : 'Anwenden' }}
                </button>
            </div>
        </form>
    </dialog>
</template>


