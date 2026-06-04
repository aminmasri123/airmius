<script setup>
defineProps({
    courseForm: { type: Object, required: true },
    courseCategories: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    uploadState: { type: Object, required: true },
})

defineEmits(['updateCourse', 'uploadCourseCover'])
</script>

<template>
    <article class="surface-card p-5">
        <h2 class="text-lg font-semibold text-primary">Kursdaten und Veröffentlichung</h2>
        <form class="mt-5 grid gap-4" @submit.prevent="$emit('updateCourse')">
            <div class="grid gap-3 lg:grid-cols-2">
                <input v-model="courseForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurstitel">
                <input v-model="courseForm.subtitle" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurzversprechen">
                <select v-model="courseForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option v-for="[value, label] in courseCategories" :key="value" :value="value">{{ label }}</option>
                </select>
                <input v-model="courseForm.sport_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Sportart">
                <select v-model="courseForm.level" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option v-for="[value, label] in levels" :key="value" :value="value">{{ label }}</option>
                </select>
                <input v-model="courseForm.language" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Sprache, z. B. de">
            </div>
            <div class="grid gap-2">
                <input v-model="courseForm.cover_image" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Cover-Bild URL">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    <i class="las la-image"></i>
                    Cover hochladen
                    <input type="file" accept="image/*" class="sr-only" @change="$emit('uploadCourseCover', $event)">
                </label>
                <p v-if="uploadState.key === 'cover'" class="text-xs text-secondary">Cover wird hochgeladen...</p>
                <p v-if="uploadState.error && uploadState.key === 'cover'" class="text-xs text-error">{{ uploadState.error }}</p>
            </div>
            <textarea v-model="courseForm.description" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
            <div class="grid gap-3 lg:grid-cols-3">
                <textarea v-model="courseForm.learning_goals_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lernziele, je Zeile eins"></textarea>
                <textarea v-model="courseForm.requirements_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Voraussetzungen, je Zeile eine"></textarea>
                <textarea v-model="courseForm.target_groups_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zielgruppen, je Zeile eine"></textarea>
            </div>
            <div class="grid gap-3 lg:grid-cols-3">
                <textarea v-model="courseForm.sales_points_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Verkaufsargumente, je Zeile eins"></textarea>
                <textarea v-model="courseForm.faq_items_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="FAQ: Frage | Antwort"></textarea>
                <textarea v-model="courseForm.guarantee_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Garantie / Betreuung / Rückfragen"></textarea>
            </div>
            <div class="grid gap-3 lg:grid-cols-3">
                <input v-model="courseForm.certificate_logo_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zertifikat Logo URL">
                <input v-model="courseForm.certificate_signature_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Signatur auf Zertifikat">
                <input v-model="courseForm.certificate_footer_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zertifikat Fusszeile">
            </div>
            <input v-model="courseForm.tags_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tags durch Komma trennen">
            <div class="grid gap-3 lg:grid-cols-4">
                <select v-model="courseForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="draft">Entwurf</option>
                    <option value="review">Zur Prüfung</option>
                    <option value="published">Veröffentlicht</option>
                    <option value="archived">Archiviert</option>
                </select>
                <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                    <input v-model="courseForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">
                    öffentlich sichtbar
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                    <input v-model="courseForm.is_free" type="checkbox" class="rounded border-border bg-inputBg">
                    Kostenlos
                </label>
                <input v-if="!courseForm.is_free" v-model="courseForm.price" inputmode="decimal" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in Euro">
            </div>
            <button class="justify-self-start rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="courseForm.processing">
                Kursdaten speichern
            </button>
        </form>
    </article>
</template>



