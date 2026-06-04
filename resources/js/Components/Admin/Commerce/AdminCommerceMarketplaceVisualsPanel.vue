<script setup>
defineProps({
    marketplaceVisuals: { type: Array, default: () => [] },
    marketplaceVisualForm: { type: Object, required: true },
})

const emit = defineEmits([
    'update-marketplace-visuals',
    'set-marketplace-visual-upload',
])
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Marketplace</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Statische Bilder verwalten</h2>
                <p class="mt-1 max-w-3xl text-sm text-secondary">
                    Diese Bilder steuern die festen Marketplace-Flächen wie Seitenbanner, Hero-Banner und Sale-Kachel. Du kannst eine URL eintragen oder direkt ein Bild hochladen.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                :disabled="marketplaceVisualForm.processing"
                @click="emit('update-marketplace-visuals')"
            >
                Bilder speichern
            </button>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <article
                v-for="visual in marketplaceVisuals"
                :key="visual.key"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div
                    class="overflow-hidden rounded-lg border border-border bg-inputBg"
                    :class="visual.key === 'side_banner' ? 'flex h-80 items-center justify-center' : ''"
                >
                    <img
                        v-if="visual.url"
                        :src="visual.url"
                        :alt="visual.label"
                        :class="visual.key === 'side_banner' ? 'h-full w-auto object-cover' : 'aspect-video w-full object-cover'"
                    />
                    <div
                        v-else
                        class="flex items-center justify-center text-secondary"
                        :class="visual.key === 'side_banner' ? 'h-full w-16' : 'aspect-video w-full'"
                    >
                        <i class="las la-image text-4xl"></i>
                    </div>
                </div>

                <h3 class="mt-3 font-semibold text-primary">{{ visual.label }}</h3>
                <p class="mt-1 text-xs leading-5 text-secondary">{{ visual.description }}</p>
                <p class="mt-2 text-xs font-semibold text-primary">Empfohlen: {{ visual.recommended_size }}</p>
                <div v-if="visual.key === 'side_banner'" class="mt-3 rounded border border-air-blue/30 bg-air-blue/10 p-3 text-xs leading-5 text-secondary">
                    <p class="font-semibold text-primary">Seitenbanner-Regel</p>
                    <p>Kein Text, keine Logos, keine Gesichter und keine wichtigen Details am Rand. Das Bild wird gespiegelt und nur dezent als Hintergrund genutzt.</p>
                </div>
                <p class="mt-1 text-xs text-secondary">Aktuelle Zielgröße: {{ marketplaceVisualForm.dimensions[visual.key]?.width }} x {{ marketplaceVisualForm.dimensions[visual.key]?.height }} px</p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="block text-xs font-semibold uppercase text-secondary">
                        Breite px
                        <input
                            v-model="marketplaceVisualForm.dimensions[visual.key].width"
                            type="number"
                            min="120"
                            max="3840"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                        />
                    </label>
                    <label class="block text-xs font-semibold uppercase text-secondary">
                        Höhe px
                        <input
                            v-model="marketplaceVisualForm.dimensions[visual.key].height"
                            type="number"
                            min="120"
                            max="3840"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                        />
                    </label>
                </div>

                <label class="mt-4 block text-xs font-semibold uppercase text-secondary">Bild-URL oder gespeicherter Pfad</label>
                <input
                    v-model="marketplaceVisualForm.sources[visual.key]"
                    class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="https://... oder marketplace/visuals/..."
                />

                <label class="mt-3 block text-xs font-semibold uppercase text-secondary">Bild hochladen</label>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    @change="emit('set-marketplace-visual-upload', visual.key, $event)"
                />
            </article>
        </div>
    </section>
</template>

