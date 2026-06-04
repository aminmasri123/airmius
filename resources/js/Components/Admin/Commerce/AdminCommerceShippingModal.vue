<script setup>
defineProps({
    carriers: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['autofill-tracking-url', 'close', 'submit'])
</script>

<template>
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">Versand bearbeiten</h2>
            <div class="mt-4 grid gap-3">
                <select v-model="form.shipping_status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="open">Offen</option>
                    <option value="prepared">Vorbereitet</option>
                    <option value="shipped">Versendet</option>
                    <option value="delivered">Zugestellt</option>
                </select>
                <input
                    v-model="form.shipping_carrier"
                    list="commerce-shipping-carriers"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="DHL / UPS / Hermes"
                    @change="emit('autofill-tracking-url')"
                >
                <datalist id="commerce-shipping-carriers">
                    <option v-for="carrier in carriers" :key="carrier.value" :value="carrier.value">
                        {{ carrier.label }}
                    </option>
                </datalist>
                <input v-model="form.shipping_label_url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Versandlabel-URL">
                <input v-model="form.tracking_number" class="rounded-lg border-border bg-inputBg text-sm uppercase text-primary" placeholder="Trackingnummer" @blur="emit('autofill-tracking-url')">
                <input v-model="form.tracking_url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tracking-URL">
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('submit')">
                    Speichern
                </button>
            </div>
        </div>
    </div>
</template>
