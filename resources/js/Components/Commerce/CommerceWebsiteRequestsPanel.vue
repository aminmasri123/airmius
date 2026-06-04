<script setup>
defineProps({
    clubs: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    websiteRequestModal: { type: Boolean, default: false },
    websiteForm: { type: Object, required: true },
})

const emit = defineEmits([
    'open-website-request-modal',
    'update:websiteRequestModal',
    'store-website-request',
])
</script>

<template>
    <article class="surface-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Vereinswebsite</h2>
                <p class="mt-1 text-sm text-secondary">Website-Anfragen sind nur für Vereine sichtbar, für die du berechtigt bist.</p>
            </div>
            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-website-request-modal')">
                Website anfragen
            </button>
        </div>
        <p class="mt-4 text-sm text-secondary">{{ websiteRequests.length }} Website-Anfragen</p>

        <div v-if="websiteRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('store-website-request')">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    aria-label="Website-Anfrage schließen"
                    @click="emit('update:websiteRequestModal', false)"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="mb-4 pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">Verein</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Vereinswebsite erstellen lassen</h2>
                    <p class="mt-1 text-sm text-secondary">Wähle den Verein und beschreibe kurz die gewünschte Website.</p>
                </div>
                <div class="grid gap-3">
                    <select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewünschte Domain">
                    <textarea v-model="websiteForm.goals" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was soll die Website können?"></textarea>
                    <textarea v-model="websiteForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Weitere Hinweise"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Anfrage senden</button>
                </div>
            </form>
        </div>
    </article>
</template>

