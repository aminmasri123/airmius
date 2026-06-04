<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    clubs: {
        type: Array,
        default: () => [],
    },
    editingSponsor: {
        type: Object,
        default: null,
    },
    canManageSponsors: {
        type: Boolean,
        default: true,
    },
    isDark: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close', 'submit'])

const previewUrl = (source) => {
    if (!source) return ''
    if (source.startsWith('http://') || source.startsWith('https://') || source.startsWith('/')) return source

    return `/storage/${source}`
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/60 px-4 py-6"
            @click.self="$emit('close')"
        >
            <form class="w-full max-w-5xl rounded-lg border border-border bg-card shadow-2xl" @submit.prevent="$emit('submit')">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-card p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Sponsor</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">
                            {{ editingSponsor ? 'Sponsor bearbeiten' : 'Sponsor anlegen' }}
                        </h2>
                        <p class="mt-1 text-sm text-secondary">Zuordnung, Kontakt, Logo und Laufzeit an einem Ort.</p>
                    </div>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-secondary hover:text-primary" @click="$emit('close')">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="max-h-[75vh] overflow-y-auto p-5">
                    <div v-if="!canManageSponsors" class="mb-4 rounded-lg border border-border bg-inputBg p-4 text-sm text-primary">
                        Sponsorenverwaltung für diesen Verein ist ab dem Club-Plan verfügbar. Wähle einen Verein mit passendem Plan oder eine Plattform-/Outfit-Abo-Zuordnung.
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Sponsor-Art</span>
                            <select v-model="form.scope" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="platform">Airmius Plattform</option>
                                <option value="outfit_subscription">Outfit-Abo</option>
                                <option value="club">Verein</option>
                            </select>
                            <div v-if="form.errors.scope" class="mt-1 text-sm text-error">{{ form.errors.scope }}</div>
                        </label>

                        <label v-if="form.scope === 'club'" class="block">
                            <span class="text-sm font-semibold text-primary">Verein</span>
                            <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                                <option value="">Verein auswählen</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                            </select>
                            <div v-if="form.errors.club_id" class="mt-1 text-sm text-error">{{ form.errors.club_id }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Sponsorname</span>
                            <input v-model="form.name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Nike, Stadtwerke, ..." required>
                            <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Kontaktperson</span>
                            <input v-model="form.contact_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Ansprechpartner">
                            <div v-if="form.errors.contact_name" class="mt-1 text-sm text-error">{{ form.errors.contact_name }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">E-Mail</span>
                            <input v-model="form.email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="sponsor@example.com" type="email">
                            <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Website</span>
                            <input v-model="form.website" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://..." type="url">
                            <div v-if="form.errors.website" class="mt-1 text-sm text-error">{{ form.errors.website }}</div>
                        </label>

                        <div class="rounded-lg border border-border bg-inputBg p-4 xl:col-span-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-primary">Sponsorlogos nach Farbfläche</p>
                                    <p class="mt-1 text-xs text-secondary">
                                        Hinterlege idealerweise zwei Varianten: dunkles Logo für helle Flächen und helles Logo für dunkle Flächen.
                                    </p>
                                </div>
                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">
                                    {{ isDark ? 'Aktuell: dunkle Palette' : 'Aktuell: helle Palette' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="block rounded-lg border border-border bg-card p-3">
                                    <span class="text-sm font-semibold text-primary">Logo für helle Flächen</span>
                                    <input v-model="form.logo_light" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="sponsors/nike-light.webp">
                                    <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-white p-3">
                                        <img v-if="form.logo_light" :src="previewUrl(form.logo_light)" alt="Logo für helle Flächen" class="max-h-full max-w-full object-contain">
                                        <span v-else class="text-xs text-slate-500">Vorschau helle Fläche</span>
                                    </div>
                                </label>

                                <label class="block rounded-lg border border-border bg-card p-3">
                                    <span class="text-sm font-semibold text-primary">Logo für dunkle Flächen</span>
                                    <input v-model="form.logo_dark" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="sponsors/nike-dark.webp">
                                    <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-slate-950 p-3">
                                        <img v-if="form.logo_dark" :src="previewUrl(form.logo_dark)" alt="Logo für dunkle Flächen" class="max-h-full max-w-full object-contain">
                                        <span v-else class="text-xs text-slate-400">Vorschau dunkle Fläche</span>
                                    </div>
                                </label>
                            </div>

                            <input v-model="form.logo" type="hidden">
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Budget / Betrag</span>
                            <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="0,00" type="number" min="0" step="0.01">
                            <div v-if="form.errors.amount" class="mt-1 text-sm text-error">{{ form.errors.amount }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Start</span>
                            <input v-model="form.starts_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                            <div v-if="form.errors.starts_at" class="mt-1 text-sm text-error">{{ form.errors.starts_at }}</div>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Ende</span>
                            <input v-model="form.ends_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                            <div v-if="form.errors.ends_at" class="mt-1 text-sm text-error">{{ form.errors.ends_at }}</div>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 font-semibold text-primary" @click="$emit('close')">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.processing || !canManageSponsors">
                        {{ editingSponsor ? 'Sponsor speichern' : 'Sponsor erstellen' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>

