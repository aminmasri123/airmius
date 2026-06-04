<script setup>
defineProps({
    delivery: { type: Object, required: true },
    issueForm: { type: Object, required: true },
    statusLabel: { type: Function, required: true },
    formatDate: { type: Function, required: true },
})

defineEmits(['close', 'submit'])
</script>

<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferproblem melden</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">{{ statusLabel(delivery.status) }} vom {{ formatDate(delivery.delivery_month) }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Beschreibe kurz, was nicht passt. Das Support-Team sieht Lieferung, Tracking und dein Style-Profil direkt dazu.
                    </p>
                </div>
                <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="$emit('close')">
                    <i class="las la-times text-xl"></i>
                </button>
            </div>

            <div class="mt-5 grid gap-4">
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Art des Problems</span>
                    <select v-model="issueForm.issue_type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="exchange">Umtausch / andere Größe</option>
                        <option value="return">Retoure</option>
                        <option value="damaged">Beschaedigt</option>
                        <option value="missing_item">Artikel fehlt</option>
                        <option value="wrong_item">Falscher Artikel</option>
                        <option value="other">Sonstiges</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Beschreibung</span>
                    <textarea v-model="issueForm.issue_description" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Was ist passiert? Welche Artikel sind betroffen?"></textarea>
                    <span v-if="issueForm.errors.issue_description" class="mt-1 block text-xs text-red-300">{{ issueForm.errors.issue_description }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Wunschloesung</span>
                    <input v-model="issueForm.issue_requested_resolution" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z.B. Ersatz, Retoure, Gutschrift">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Gewünschte Größe</span>
                    <input v-model="issueForm.issue_exchange_size" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional, z.B. M statt L">
                </label>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="$emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50" :disabled="issueForm.processing || !issueForm.issue_description" @click="$emit('submit')">
                    Meldung senden
                </button>
            </div>
        </div>
    </div>
</template>

