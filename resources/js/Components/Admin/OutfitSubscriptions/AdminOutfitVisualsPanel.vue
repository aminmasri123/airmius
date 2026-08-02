<script setup>
defineProps({
    activeTab: { type: String, required: true },
    visuals: { type: Object, default: () => ({}) },
    visualModalOpen: { type: Boolean, default: false },
    visualForm: { type: Object, required: true },
    setHeroUpload: { type: Function, required: true },
    setVisualUploadInput: { type: Function, required: true },
    updateVisuals: { type: Function, required: true },
    openVisualModal: { type: Function, required: true },
    closeVisualModal: { type: Function, required: true },
})
</script>

<template>
    <section v-if="activeTab === 'visuals'" class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="h-20 w-32 shrink-0 overflow-hidden rounded-lg border border-border bg-inputBg">
                    <img
                        v-if="visuals.hero?.url"
                        :src="visuals.hero.url"
                        alt="Outfit-Abo Hero Vorschau"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center text-secondary">
                        <i class="las la-image text-2xl"></i>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Bild</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Dashboard-Hero</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ visuals.hero?.recommended_size || '1920 x 1080 px' }} - {{ visuals.hero?.ratio || '16:9' }}
                    </p>
                    <p class="mt-1 truncate text-xs text-secondary">{{ visuals.hero?.source }}</p>
                </div>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                @click="openVisualModal"
            >
                <i class="las la-image text-lg"></i>
                Bild anpassen
            </button>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="visualModalOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <div class="w-full max-w-4xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-accent">Outfit-Abo Bild</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Dashboard-Hero anpassen</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                            Dieses Bild erscheint oben auf der Outfit-Abo Dashboardseite. Du kannst eine URL/Pfad eintragen oder ein neues Bild hochladen.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeVisualModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="grid max-h-[75vh] gap-6 overflow-y-auto p-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div>
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Bild-URL oder gespeicherter Pfad</span>
                            <input
                                v-model="visualForm.hero_source"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="/images/marketplace/airmius_outfit_abo.webp oder outfit-subscriptions/visuals/..."
                            />
                        </label>
                        <p v-if="visualForm.errors.hero_source" class="mt-1 text-sm text-error">{{ visualForm.errors.hero_source }}</p>

                        <label class="mt-4 block">
                            <span class="text-sm font-semibold text-primary">Bild hochladen</span>
                            <input
                                :ref="setVisualUploadInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                @change="setHeroUpload"
                            />
                        </label>
                        <p v-if="visualForm.errors.hero_upload" class="mt-1 text-sm text-error">{{ visualForm.errors.hero_upload }}</p>

                        <div class="mt-4 rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs font-semibold uppercase text-secondary">Empfohlenes Format</p>
                            <p class="mt-2 text-xl font-bold text-primary">{{ visuals.hero?.recommended_size || '1920 x 1080 px' }}</p>
                            <p class="mt-1 text-sm text-secondary">Verhältnis {{ visuals.hero?.ratio || '16:9' }} - {{ visuals.hero?.formats || 'WebP, JPG, PNG' }}</p>
                        </div>

                        <p class="mt-4 rounded-lg border border-accent/30 bg-accent/10 p-3 text-sm leading-6 text-secondary">
                            {{ visuals.hero?.note || 'Querformat nutzen. Wichtige Texte/Logos mittig halten, weil die Dashboard-Ansicht beschneiden kann.' }}
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-border bg-inputBg">
                        <img
                            v-if="visuals.hero?.url"
                            :src="visuals.hero.url"
                            alt="Outfit-Abo Hero Vorschau"
                            class="aspect-video w-full object-cover"
                        />
                        <div v-else class="flex aspect-video items-center justify-center text-secondary">
                            <i class="las la-image text-4xl"></i>
                        </div>
                        <div class="border-t border-border p-4">
                            <p class="text-sm font-semibold text-primary">Aktuelle Vorschau</p>
                            <p class="mt-1 break-all text-xs text-secondary">{{ visuals.hero?.source }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeVisualModal">
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                        :disabled="visualForm.processing"
                        @click="updateVisuals"
                    >
                        Bild speichern
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
