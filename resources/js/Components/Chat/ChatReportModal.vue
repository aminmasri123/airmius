<script setup>
defineProps({
    reportTarget: { type: Object, default: null },
    reportForm: { type: Object, required: true },
    closeReport: { type: Function, required: true },
    submitReport: { type: Function, required: true },
})
</script>

<template>
    <div
        v-if="reportTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeReport"
    >
        <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="submitReport">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Nachricht melden</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">Warum soll diese Nachricht geprüft werden?</h2>
                </div>
                <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeReport">
                    <i class="las la-times"></i>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <select v-model="reportForm.reason" class="w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="insult">Beleidigung</option>
                    <option value="bullying">Mobbing</option>
                    <option value="hate">Hassrede</option>
                    <option value="sexual">Sexueller Inhalt</option>
                    <option value="violence">Gewalt</option>
                    <option value="threat">Drohung</option>
                    <option value="image_rights">Bild ohne Zustimmung</option>
                    <option value="spam">Spam</option>
                    <option value="other">Sonstiges</option>
                </select>
                <p v-if="reportForm.errors.reason" class="text-sm text-error">{{ reportForm.errors.reason }}</p>

                <textarea
                    v-model="reportForm.details"
                    rows="4"
                    class="w-full rounded-lg border-border bg-inputBg text-primary"
                    placeholder="Details optional"
                />
                <p v-if="reportForm.errors.details" class="text-sm text-error">{{ reportForm.errors.details }}</p>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn" @click="closeReport">Abbrechen</button>
                <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                    Meldung senden
                </button>
            </div>
        </form>
    </div>
</template>

