<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    inventoryCountries: {
        type: Array,
        default: () => [],
    },
    modal: {
        type: Object,
        default: () => ({}),
    },
    moneyInputAttrs: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits([
    'add-inventory-row',
    'close',
    'remove-inventory-row',
    'set-image-upload',
    'submit',
])
</script>

<template>
    <div v-if="modal?.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form class="relative max-h-[90dvh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('submit')">
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                aria-label="Produkt schließen"
                @click="emit('close')"
            >
                <i class="las la-times text-xl"></i>
            </button>
            <div class="pr-12">
                <p class="text-xs font-semibold uppercase text-air-blue">Verkaufen</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Produkt bearbeiten</h2>
                <p class="mt-1 text-sm text-secondary">Änderungen werden danach erneut geprüft, bevor sie im Marketplace sichtbar sind.</p>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <input v-model="form.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                <input v-model="form.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                <input v-model="form.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                <input v-model="form.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL">
                <label class="rounded-lg border border-border bg-bg p-3 text-sm text-secondary md:col-span-2">
                    <span class="block text-xs font-semibold uppercase text-secondary">Bild hochladen</span>
                    <input type="file" accept="image/*" class="mt-2 text-sm text-primary" @change="emit('set-image-upload', $event.target.files?.[0] || null)">
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                    <input v-model="form.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                    Bestand verwalten
                </label>
                <input v-if="form.manages_stock" v-model="form.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                <textarea v-model="form.description" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Beschreibung"></textarea>
            </div>
            <div v-if="form.manages_stock" class="mt-4 rounded-lg border border-border bg-bg p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-primary">Länderbestand</h3>
                        <p class="text-xs text-secondary">Steuert, in welchen Ländern dein Produkt sichtbar und kaufbar ist.</p>
                    </div>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-inventory-row')">
                        Land hinzufügen
                    </button>
                </div>
                <div class="mt-3 space-y-3">
                    <div v-for="(inventory, index) in form.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                        <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                        </select>
                        <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                        <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Warnbestand">
                        <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lieferzeit">
                        <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerstadt">
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-inventory-row', index)">
                            Entfernen
                        </button>
                    </div>
                </div>
                <p v-if="!form.inventories.length" class="mt-3 text-sm text-secondary">Noch kein Länderbestand gepflegt.</p>
            </div>

            <div v-if="Object.keys(form.errors || {}).length" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">Abbrechen</button>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="form.processing">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</template>

