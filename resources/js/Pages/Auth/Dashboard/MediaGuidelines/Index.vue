<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    guidelines: { type: Array, default: () => [] },
    loginSlider: { type: Array, default: () => [] },
    visuals: { type: Array, default: () => [] },
})

const page = usePage()
const search = ref('')
const selectedCategory = ref('all')
const visualFeedback = ref(null)

const visualForm = useForm({
    login_slider_sources: props.loginSlider.length ? props.loginSlider.map((slide) => slide.source || '') : [''],
    login_slider_uploads: [],
    sources: Object.fromEntries(props.visuals.map((visual) => [visual.key, visual.source || ''])),
    uploads: {},
})

const categories = computed(() => [
    'all',
    ...new Set(props.guidelines.map((item) => item.category)),
])

const filteredGuidelines = computed(() => {
    const term = search.value.trim().toLowerCase()

    return props.guidelines.filter((item) => {
        const matchesCategory = selectedCategory.value === 'all' || item.category === selectedCategory.value
        const matchesSearch = !term || [
            item.category,
            item.name,
            item.dimensions,
            item.ratio,
            item.formats,
            item.note,
        ].some((value) => String(value || '').toLowerCase().includes(term))

        return matchesCategory && matchesSearch
    })
})

const visualKeys = computed(() => new Set(['login_slider', ...props.visuals.map((visual) => visual.key)]))
const manageableVisuals = computed(() => props.visuals.filter((visual) => selectedCategory.value === 'all' || visual.category === selectedCategory.value))
const showLoginSlider = computed(() => selectedCategory.value === 'all' || selectedCategory.value === 'Login')
const flashMessage = computed(() => page.props.flash?.success || page.props.flash?.error || null)
const flashType = computed(() => page.props.flash?.error ? 'error' : 'success')

const sourcePreview = (source) => {
    if (!source) return ''
    if (source.startsWith('http') || source.startsWith('/')) return source

    const uploadsUrl = page.props.uploads?.url || '/uploads'
    return `${uploadsUrl.replace(/\/$/, '')}/${source.replace(/^\//, '')}`
}

const addLoginSlide = () => {
    visualForm.login_slider_sources.push('')
}

const removeLoginSlide = (index) => {
    if (visualForm.login_slider_sources.length <= 1) {
        visualForm.login_slider_sources = ['']
        return
    }

    visualForm.login_slider_sources.splice(index, 1)
}

const setLoginSliderUploads = (event) => {
    visualForm.login_slider_uploads = Array.from(event.target.files || [])
}

const setVisualUpload = (key, event) => {
    visualForm.uploads[key] = event.target.files?.[0] || null
}

const updateVisuals = () => {
    visualFeedback.value = null

    visualForm.post(route('admin.media-guidelines.visuals.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            visualFeedback.value = { type: 'success', message: 'Bilder wurden gespeichert.' }
            visualForm.uploads = {}
            visualForm.login_slider_uploads = []
        },
        onError: () => {
            visualFeedback.value = { type: 'error', message: 'Bilder konnten nicht gespeichert werden. Bitte prüfe die Dateien oder Pfade.' }
        },
    })
}

const actionLabel = (item) => item.visual_keys?.some((key) => visualKeys.value.has(key))
    ? 'Hier bearbeitbar'
    : (item.edit_hint || 'Jeweils am Inhalt bearbeiten')
</script>

<template>
    <Head title="Bildmasse" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Media & Content</p>
                <h1 class="mt-1 text-3xl font-bold text-primary">Empfohlene Bildmasse</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Zentrale Übersicht für Redakteure, Admins und Vereine: welche Bildgrößen für Profile, Blog,
                    Posts, Videos und weitere Medien am besten funktionieren.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    v-model="search"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="Suchen..."
                >
                <select v-model="selectedCategory" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="all">Alle Bereiche</option>
                    <option v-for="category in categories.filter((category) => category !== 'all')" :key="category" :value="category">
                        {{ category }}
                    </option>
                </select>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Standard Upload</p>
                <p class="mt-2 text-2xl font-bold text-primary">JPG, PNG, WebP</p>
                <p class="mt-1 text-sm text-secondary">Diese Formate funktionieren für fast alle Bildbereiche.</p>
            </article>
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Login-Slider</p>
                <p class="mt-2 text-2xl font-bold text-primary">1 bis mehrere</p>
                <p class="mt-1 text-sm text-secondary">Dynamischer Hochformat-Slider für die rechte Login-Seite.</p>
            </article>
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Globale Banner</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ props.visuals.length }}</p>
                <p class="mt-1 text-sm text-secondary">Marketplace und Outfit-Abo Bildflaechen zentral pflegen.</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card">
            <div class="border-b border-border bg-inputBg/70 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-air-blue">Zentrale Bildsteuerung</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Globale Bilder direkt bearbeiten</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                            Login-Slider, Marketplace-Banner und Outfit-Abo Hero können hier zentral gepflegt werden. Inhaltsspezifische Bilder bleiben beim jeweiligen Profil, Verein, Blog oder Produkt.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                        :disabled="visualForm.processing"
                        @click="updateVisuals"
                    >
                        <i class="las la-save text-lg"></i>
                        Bilder speichern
                    </button>
                </div>
            </div>

            <div
                v-if="visualFeedback || flashMessage"
                class="mx-5 mt-5 rounded-lg border px-4 py-3 text-sm font-semibold"
                :class="(visualFeedback?.type || flashType) === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ visualFeedback?.message || flashMessage }}
            </div>

            <div v-if="showLoginSlider" class="p-5">
                <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-air-blue">Dynamischer Slider</p>
                                <h3 class="mt-1 text-lg font-bold text-primary">Login-Slider rechts</h3>
                                <p class="mt-2 text-sm leading-6 text-secondary">
                                    Lade ein Bild oder mehrere Bilder hoch. Die Reihenfolge entspricht der Liste. Empfohlen: 1080 x 1920 px.
                                </p>
                            </div>
                            <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">
                                {{ visualForm.login_slider_sources.filter(Boolean).length + visualForm.login_slider_uploads.length }} Bilder
                            </span>
                        </div>

                        <div class="mt-4 rounded-lg border border-dashed border-border bg-card p-4">
                            <label class="block text-xs font-semibold uppercase text-secondary">Mehrere Bilder hochladen</label>
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                @change="setLoginSliderUploads"
                            >
                            <p class="mt-2 text-xs leading-5 text-secondary">
                                Neue Uploads werden beim Speichern an die vorhandenen Sliderbilder angehaengt.
                            </p>
                            <p v-if="visualForm.errors.login_slider_uploads" class="mt-1 text-xs text-error">{{ visualForm.errors.login_slider_uploads }}</p>
                        </div>

                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-card"
                            @click="addLoginSlide"
                        >
                            <i class="las la-plus"></i>
                            Leeren Slot hinzufügen
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="(source, index) in visualForm.login_slider_sources"
                            :key="`login-slide-${index}`"
                            class="rounded-lg border border-border bg-inputBg p-4 transition hover:border-borderHover"
                        >
                            <div class="relative overflow-hidden rounded-lg border border-border bg-card">
                                <img
                                    v-if="sourcePreview(source)"
                                    :src="sourcePreview(source)"
                                    :alt="`Login-Slider Bild ${index + 1}`"
                                    class="aspect-[9/16] w-full object-cover"
                                >
                                <div v-else class="flex aspect-[9/16] items-center justify-center text-secondary">
                                    <i class="las la-image text-4xl"></i>
                                </div>
                                <span class="absolute left-2 top-2 rounded-full bg-black/65 px-2 py-1 text-xs font-bold text-white">
                                    {{ index + 1 }}
                                </span>
                            </div>

                            <label class="mt-3 block text-xs font-semibold uppercase text-secondary">URL oder gespeicherter Pfad</label>
                            <input
                                v-model="visualForm.login_slider_sources[index]"
                                class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary"
                                placeholder="/img/login/bild1.png oder login/visuals/..."
                            >
                            <p v-if="visualForm.errors[`login_slider_sources.${index}`]" class="mt-1 text-xs text-error">{{ visualForm.errors[`login_slider_sources.${index}`] }}</p>

                            <button
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 rounded-lg border border-red-500/40 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10"
                                @click="removeLoginSlide(index)"
                            >
                                <i class="las la-trash"></i>
                                Entfernen
                            </button>
                        </article>
                    </div>
                </div>
            </div>

            <div v-if="manageableVisuals.length" class="grid gap-4 border-t border-border p-5 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="visual in manageableVisuals"
                    :key="visual.key"
                    class="rounded-lg border border-border bg-inputBg p-4 transition hover:border-borderHover"
                >
                    <div class="overflow-hidden rounded-lg border border-border bg-card">
                        <img
                            v-if="visual.url"
                            :src="visual.url"
                            :alt="visual.label"
                            class="aspect-video w-full object-cover"
                        >
                        <div v-else class="flex aspect-video items-center justify-center text-secondary">
                            <i class="las la-image text-4xl"></i>
                        </div>
                    </div>

                    <div class="mt-3">
                        <span class="rounded-full bg-air-blue/10 px-2 py-1 text-[11px] font-semibold text-air-blue">{{ visual.category }}</span>
                        <h3 class="mt-2 font-semibold text-primary">{{ visual.label }}</h3>
                        <p class="mt-1 text-xs leading-5 text-secondary">{{ visual.description }}</p>
                        <p class="mt-2 text-xs font-semibold text-primary">{{ visual.recommended_size }} - {{ visual.ratio }}</p>
                    </div>

                    <label class="mt-4 block text-xs font-semibold uppercase text-secondary">URL oder gespeicherter Pfad</label>
                    <input
                        v-model="visualForm.sources[visual.key]"
                        class="mt-1 w-full rounded-lg border-border bg-card text-sm text-primary"
                        placeholder="https://... oder uploads/..."
                    >
                    <p v-if="visualForm.errors[`sources.${visual.key}`]" class="mt-1 text-xs text-error">{{ visualForm.errors[`sources.${visual.key}`] }}</p>

                    <label class="mt-3 block text-xs font-semibold uppercase text-secondary">Bild hochladen</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary"
                        @change="setVisualUpload(visual.key, $event)"
                    >
                    <p v-if="visualForm.errors[`uploads.${visual.key}`]" class="mt-1 text-xs text-error">{{ visualForm.errors[`uploads.${visual.key}`] }}</p>
                </article>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-inputBg text-left text-xs font-semibold uppercase tracking-wider text-secondary">
                        <tr>
                            <th class="px-4 py-3">Bereich</th>
                            <th class="px-4 py-3">Bildtyp</th>
                            <th class="px-4 py-3">Empfohlen</th>
                            <th class="px-4 py-3">Verhaeltnis</th>
                            <th class="px-4 py-3">Format</th>
                            <th class="px-4 py-3">Max.</th>
                            <th class="px-4 py-3">Hinweis</th>
                            <th class="px-4 py-3">Bearbeitung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in filteredGuidelines" :key="`${item.category}-${item.name}`" class="align-top">
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                    {{ item.category }}
                                </span>
                            </td>
                            <td class="px-4 py-4 font-semibold text-primary">{{ item.name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-primary">{{ item.dimensions }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-secondary">{{ item.ratio }}</td>
                            <td class="px-4 py-4 text-secondary">{{ item.formats }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-secondary">{{ item.max_size }}</td>
                            <td class="min-w-72 px-4 py-4 leading-6 text-secondary">{{ item.note }}</td>
                            <td class="min-w-56 px-4 py-4 text-secondary">
                                {{ actionLabel(item) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!filteredGuidelines.length" class="p-8 text-center text-sm text-secondary">
                Keine passenden Bildmasse gefunden.
            </div>
        </section>
    </div>
</template>
