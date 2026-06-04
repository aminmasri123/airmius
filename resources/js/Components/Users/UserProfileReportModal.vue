<script setup>
defineProps({
    reportTargetOpen: { type: Boolean, default: false },
    reportForm: { type: Object, required: true },
    closeProfileReport: { type: Function, required: true },
    submitProfileReport: { type: Function, required: true },
})
</script>

<template>
    <div
        v-if="reportTargetOpen"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeProfileReport"
    >
        <form class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-xl" @submit.prevent="submitProfileReport">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Profil melden</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">Warum soll dieses Profil geprüft werden?</h2>
                </div>
                <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted" @click="closeProfileReport">
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
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover" @click="closeProfileReport">Abbrechen</button>
                <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="reportForm.processing">
                    Meldung senden
                </button>
            </div>
        </form>
    </div>
</template>

