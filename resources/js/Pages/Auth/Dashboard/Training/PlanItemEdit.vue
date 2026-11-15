<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import TrainingPlanItemForm from '@/Components/Training/TrainingPlanItemForm.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { equipmentPresets, exerciseLibrary, levelLabels, sports, structuredMetricKeys, trainingGoals, trainingSessionBlocks } from '@/support/trainingOptions'
import { exerciseLanguages, useExerciseCopy } from '@/support/exerciseContent'

defineOptions({ layout: AppLayout })
const props = defineProps({ plan: { type: Object, required: true }, item: { type: Object, default: null }, sportRoutes: { type: Array, default: () => [] } })
const { t, locale } = useI18n()
const ec = useExerciseCopy()
const item = props.item || {}
const localDate = (value) => {
    if (!value) return ''
    const date = new Date(value)
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}
const form = useForm({
    title: item.title || '', description: item.description || '', instructions: item.instructions || '', notes: item.notes || '',
    todos: (item.todos || []).join('\n'), original_locale: item.original_locale || (exerciseLanguages[locale.value] ? locale.value : 'en'),
    translations: Object.fromEntries(Object.keys(exerciseLanguages).map((code) => [code, { title: '', description: '', instructions: '', notes: '', todos: '', ...(item.translations?.[code] || {}) }])),
    explanation_images: (item.explanation_images || []).map((image) => ({ id: image.id, file: null, texts: image.texts || {} })),
    sport_type: item.sport_type || 'laufen', scheduled_at: localDate(item.scheduled_at), week: item.metrics?.Woche || '',
    duration_minutes: item.duration_minutes ?? '', distance_km: item.distance_meters != null ? item.distance_meters / 1000 : '', calories: item.calories ?? '',
    intensity: item.intensity || 'mittel', load: item.metrics?.Belastung || 'medium', focus: item.metrics?.Fokus || '',
    session_block: trainingSessionBlocks.find((block) => [block.key, block.label].includes(item.metrics?.Abschnitt))?.key || 'main',
    goal: item.metrics?.Trainingsziel || props.plan.settings?.goal || 'Technik',
    level: Object.entries(levelLabels).find(([key, label]) => [key, label].includes(item.metrics?.Niveau))?.[0] || props.plan.settings?.level || 'intermediate',
    equipment: item.metrics?.Equipment || '', sport_route_id: item.sport_route_id || '', image: null, video_url: item.video_url || '',
    metrics: Object.fromEntries(Object.entries(item.metrics || {}).filter(([key, value]) => !structuredMetricKeys.includes(key) && typeof value !== 'object')),
})
const contentLanguage = ref(form.original_locale)
const content = computed(() => contentLanguage.value === form.original_locale ? form : form.translations[contentLanguage.value])
const contentFields = ['title', 'description', 'instructions', 'notes', 'todos']
const fieldLimits = { title: 160, description: 3000, instructions: 10000, notes: 3000, todos: 3000 }
const choices = computed(() => sports.map((sport) => ({ ...sport, label: t(sport.label) })))
const sport = computed(() => choices.value.find((choice) => choice.key === form.sport_type) || choices.value[1])
const previews = ref(Object.fromEntries((item.explanation_images || []).map((image) => [image.id, image.url])))
const objectUrls = new Set()
const imageError = ref('')
let saved = false
let stopNavigation
const cancelUrl = () => item.id ? route('auth.training.plans.items.show', [props.plan.id, item.id]) : route('auth.training.index')
const beforeUnload = (event) => { if (form.isDirty && !saved) { event.preventDefault(); event.returnValue = '' } }
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload)
    stopNavigation = router.on('before', (event) => {
        if (event.detail.visit.method === 'get' && form.isDirty && !saved && !window.confirm(ec('leave'))) event.preventDefault()
    })
})
onBeforeUnmount(() => { stopNavigation?.(); window.removeEventListener('beforeunload', beforeUnload); objectUrls.forEach((url) => URL.revokeObjectURL(url)) })
const addImages = (event) => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (form.explanation_images.length + files.length > 12 || files.some((file) => file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) { imageError.value = ec('limit'); return }
    imageError.value = ''
    for (const file of files) {
        const id = crypto.randomUUID()
        const url = URL.createObjectURL(file)
        objectUrls.add(url)
        previews.value[id] = url
        form.explanation_images.push({ id, file, texts: {} })
    }
}
const imageTexts = (image) => {
    // Initialize only on editing, not during rendering, to keep pristine forms clean.
    return image.texts[contentLanguage.value] || {}
}
const updateImageText = (image, field, value) => {
    image.texts[contentLanguage.value] ||= { caption: '', alt: '' }
    image.texts[contentLanguage.value][field] = value
}
const moveImage = (index, offset) => {
    const images = form.explanation_images
    const [image] = images.splice(index, 1)
    images.splice(index + offset, 0, image)
}
const applyTemplate = (template) => {
    for (const field of ['sport_type', 'title', 'focus', 'duration_minutes', 'todos', 'session_block', 'goal', 'level', 'equipment']) {
        if (template[field] != null) form[field] = template[field]
    }
    form.metrics = { ...template.metrics }
    contentLanguage.value = form.original_locale
}
const submit = () => {
    if (form.processing) return
    form.transform((data) => ({
        ...data, editor_page: '1', ...(item.id ? { _method: 'put' } : {}),
        translations: Object.fromEntries(Object.entries(data.translations).filter(([code]) => code !== data.original_locale)),
        // Multipart sends no entry for an empty array; mark explicit removal separately.
        explanation_images_empty: data.explanation_images.length === 0 ? '1' : '0',
        metrics: { ...data.metrics, Abschnitt: trainingSessionBlocks.find((block) => block.key === data.session_block)?.label || data.session_block, Trainingsziel: data.goal, Niveau: levelLabels[data.level] || data.level, Equipment: data.equipment },
    })).post(item.id ? route('auth.training.plans.items.update', [props.plan.id, item.id]) : route('auth.training.plans.items.store', props.plan.id), {
        forceFormData: true,
        onSuccess: () => { saved = true },
        onError: () => { document.querySelector('[role="alert"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }) },
    })
}
</script>

<template>
    <Head :title="ec(item.id ? 'edit' : 'create')" />
    <div class="mx-auto max-w-5xl space-y-5 pb-16">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-semibold text-primary">{{ ec(item.id ? 'edit' : 'create') }}</h1><p class="mt-1 text-sm text-secondary">{{ plan.title }}</p></div>
            <button type="button" class="rounded-xl border border-border px-4 py-2 text-primary" :disabled="form.processing" @click="router.visit(cancelUrl())">{{ ec('cancel') }}</button>
        </header>
        <div v-if="Object.keys(form.errors).length" role="alert" tabindex="-1" class="rounded-xl border border-danger p-4 text-danger"><ul class="list-inside list-disc"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul></div>
        <TrainingPlanItemForm class="rounded-2xl border border-border bg-card" :content-editor="true" :form="form" :exercise-library="exerciseLibrary" :sports="choices" :sport="sport" :sport-label="(key) => choices.find((choice) => choice.key === key)?.label || key" :training-session-blocks="trainingSessionBlocks.map((block) => ({ ...block, label: t(block.label) }))" :training-goals="trainingGoals.map((goal) => ({ ...goal, label: t(goal.label) }))" :equipment-presets="equipmentPresets.map((value) => t(value))" :sport-routes="sportRoutes" :submit-label="ec('save')" @apply-template="applyTemplate" @submit="submit">
            <template #content>
                <section class="space-y-4 rounded-xl border border-border p-4 md:col-span-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold text-primary">{{ ec('original') }}<select v-model="form.original_locale" class="mt-2 w-full rounded-xl border-border bg-inputBg" @change="contentLanguage = form.original_locale"><option v-for="(label, code) in exerciseLanguages" :key="code" :value="code">{{ label }}</option></select></label>
                        <label class="text-sm font-semibold text-primary">{{ ec('language') }}<select v-model="contentLanguage" class="mt-2 w-full rounded-xl border-border bg-inputBg"><option v-for="(label, code) in exerciseLanguages" :key="code" :value="code">{{ label }}{{ code === form.original_locale ? ` · ${ec('original')}` : '' }}</option></select></label>
                    </div>
                    <p class="text-sm text-secondary">{{ ec('hint') }}</p>
                    <div :dir="contentLanguage === 'ar' ? 'rtl' : 'ltr'" :lang="contentLanguage" class="space-y-4">
                        <label v-for="field in contentFields" :key="field" class="block text-sm font-semibold text-primary">{{ ec(field) }}
                            <input v-if="field === 'title'" v-model="content[field]" :required="contentLanguage === form.original_locale" :maxlength="fieldLimits[field]" :placeholder="contentLanguage !== form.original_locale ? form[field] : ''" class="mt-2 w-full rounded-xl border-border bg-inputBg" />
                            <textarea v-else v-model="content[field]" :rows="field === 'instructions' ? 6 : 3" :maxlength="fieldLimits[field]" :placeholder="contentLanguage !== form.original_locale ? form[field] : ''" class="mt-2 w-full rounded-xl border-border bg-inputBg" />
                        </label>
                    </div>
                </section>
            </template>
            <template #media>
                <section class="space-y-4 rounded-xl border border-border p-4 md:col-span-2">
                    <h2 class="font-semibold text-primary">{{ ec('images') }}</h2><p class="text-sm text-secondary">{{ ec('imageHint') }}</p>
                    <label class="block text-sm font-semibold text-primary">{{ ec('addImages') }}<input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full" :disabled="form.processing" @change="addImages" /></label>
                    <p v-if="imageError" role="alert" class="text-sm text-danger">{{ imageError }}</p>
                    <article v-for="(image, index) in form.explanation_images" :key="image.id" class="grid gap-4 rounded-xl border border-border p-3 sm:grid-cols-[160px_1fr]">
                        <img :src="previews[image.id]" :alt="imageTexts(image).alt || ''" class="h-40 w-full rounded-lg bg-inputBg object-contain" />
                        <div class="space-y-3">
                            <p class="text-xs text-secondary">{{ index + 1 }} · {{ exerciseLanguages[contentLanguage] }}</p>
                            <label v-for="field in ['caption', 'alt']" :key="field" class="block text-sm text-primary">{{ ec(field) }}<input :value="imageTexts(image)[field] || ''" :placeholder="image.texts[form.original_locale]?.[field] || ''" :dir="contentLanguage === 'ar' ? 'rtl' : 'ltr'" :lang="contentLanguage" maxlength="500" class="mt-1 w-full rounded-xl border-border bg-inputBg" @input="updateImageText(image, field, $event.target.value)" /></label>
                            <div class="flex flex-wrap gap-2"><button type="button" :disabled="index === 0" class="rounded-lg border border-border px-3 py-1 text-sm text-primary disabled:opacity-40" @click="moveImage(index, -1)">{{ ec('up') }}</button><button type="button" :disabled="index === form.explanation_images.length - 1" class="rounded-lg border border-border px-3 py-1 text-sm text-primary disabled:opacity-40" @click="moveImage(index, 1)">{{ ec('down') }}</button><button type="button" class="rounded-lg border border-border px-3 py-1 text-sm text-danger" @click="form.explanation_images.splice(index, 1)">{{ ec('remove') }}</button></div>
                        </div>
                    </article>
                    <div v-if="item.image_url" class="space-y-2 border-t border-border pt-3"><p class="text-sm text-secondary">{{ ec('legacyImage') }}</p><img :src="item.image_url" alt="" class="h-32 rounded-xl object-contain" /><label class="block text-sm text-primary">{{ ec('replaceImage') }}<input type="file" accept="image/*" class="mt-2 block w-full" @change="form.image = $event.target.files?.[0] || null" /></label></div>
                    <p v-if="form.progress" aria-live="polite" class="text-sm text-secondary">{{ ec('progress') }}: {{ form.progress.percentage }}%</p>
                </section>
            </template>
        </TrainingPlanItemForm>
    </div>
</template>
