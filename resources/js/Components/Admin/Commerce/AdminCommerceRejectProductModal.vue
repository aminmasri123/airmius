<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    reasons: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'select-reason', 'submit'])
</script>

<template>
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">Angebot ablehnen</h2>
            <label class="mt-4 block">
                <span class="text-xs font-semibold uppercase text-secondary">Grund</span>
                <select v-model="form.selectedReason" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="emit('select-reason')">
                    <option value="">Grund auswählen</option>
                    <option v-for="reason in reasons" :key="reason.value" :value="reason.value">
                        {{ reason.label }}
                    </option>
                </select>
            </label>
            <textarea v-model="form.reason" rows="5" class="mt-3 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ablehnungsgrund für den Verkäufer"></textarea>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-warning px-4 py-2 text-sm font-semibold text-white" :disabled="!form.reason.trim()" @click="emit('submit')">
                    Ablehnen
                </button>
            </div>
        </div>
    </div>
</template>

