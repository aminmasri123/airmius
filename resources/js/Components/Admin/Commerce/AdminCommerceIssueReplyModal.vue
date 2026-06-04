<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'submit'])
</script>

<template>
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div class="w-full max-w-xl rounded-xl border border-border bg-card p-5 shadow-2xl">
            <h2 class="text-lg font-semibold text-primary">Auf Meldung antworten</h2>
            <div v-if="form.order" class="mt-3 rounded-lg border border-border bg-bg p-3 text-sm">
                <p class="text-xs font-semibold uppercase text-secondary">Meldung des Käufers</p>
                <p class="mt-1 text-primary">{{ form.order.issue_note || 'Keine Nachricht hinterlegt.' }}</p>
            </div>
            <div class="mt-4 grid gap-3">
                <textarea v-model="form.issue_response" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Antwort an den Käufer schreiben"></textarea>
                <p v-if="form.errors.issue_response" class="text-sm text-danger">{{ form.errors.issue_response }}</p>
                <select v-model="form.issue_status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="reviewing">In Prüfung</option>
                    <option value="resolved">Gelöst</option>
                    <option value="reported">Weiter offen</option>
                </select>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="form.processing" @click="emit('submit')">
                    Antwort senden
                </button>
            </div>
        </div>
    </div>
</template>

