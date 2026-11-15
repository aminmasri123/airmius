<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { exerciseLanguages, useExerciseCopy } from '@/support/exerciseContent'

defineOptions({ layout: AppLayout })
const props = defineProps({ exercise: { type: Object, default: null }, clubs: { type: Array, default: () => [] }, teams: { type: Array, default: () => [] } })
const { t, locale } = useI18n()
const ec = useExerciseCopy()
const source = props.exercise || {}
const original = source.original_content || source
const form = useForm({
    title: original.title || source.name || '', description: original.description || '', instructions: original.instructions || '', notes: original.notes || '', todos: (original.todos || []).join('\n'),
    original_locale: source.original_locale || locale.value, translations: Object.fromEntries(Object.keys(exerciseLanguages).map((code) => [code, { title: '', description: '', instructions: '', notes: '', todos: '', ...(source.translations?.[code] || {}) }])),
    explanation_images: (source.explanation_images || []).map((image) => ({ id: image.id, file: null, texts: image.texts || {} })),
    sport_type: source.sport_type || '', difficulty: source.difficulty || 'all', equipment: (source.equipment || []).join('\n'), muscle_groups: (source.muscle_groups || []).join('\n'), video_url: source.video_url || '', scope: 'personal',
})
const language = ref(form.original_locale)
const content = computed(() => language.value === form.original_locale ? form : form.translations[language.value])
const previews = ref(Object.fromEntries((source.explanation_images || []).map((image) => [image.id, image.url])))
const urls = new Set()
const imageError = ref('')
let stopNavigation
let saved = false
const beforeUnload = (event) => { if (form.isDirty && !saved) { event.preventDefault(); event.returnValue = '' } }
onMounted(() => { window.addEventListener('beforeunload', beforeUnload); stopNavigation = router.on('before', (event) => { if (event.detail.visit.method === 'get' && form.isDirty && !saved && !window.confirm(ec('leave'))) event.preventDefault() }) })
onBeforeUnmount(() => { stopNavigation?.(); window.removeEventListener('beforeunload', beforeUnload); urls.forEach((url) => URL.revokeObjectURL(url)) })
const addImages = (event) => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (files.length + form.explanation_images.length > 12 || files.some((file) => file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) { imageError.value = ec('limit'); return }
    imageError.value = ''
    for (const file of files) { const id = crypto.randomUUID(); const url = URL.createObjectURL(file); urls.add(url); previews.value[id] = url; form.explanation_images.push({ id, file, texts: {} }) }
}
const imageText = (image, field, value) => { image.texts[language.value] ||= { caption: '', alt: '' }; image.texts[language.value][field] = value }
const move = (index, offset) => { const [image] = form.explanation_images.splice(index, 1); form.explanation_images.splice(index + offset, 0, image) }
const list = (value) => value.split(/[,\n]/).map((text) => text.trim()).filter(Boolean)
const submit = () => {
    if (form.processing) return
    form.transform((data) => ({ ...data, name: data.title, content_empty_arrays: ['todos', 'equipment', 'muscle_groups'].filter((key) => !data[key].trim()), todos: data.todos.split(/\r?\n/).map((text) => text.trim()).filter(Boolean), equipment: list(data.equipment), muscle_groups: list(data.muscle_groups), translations: Object.fromEntries(Object.entries(data.translations).filter(([code]) => code !== data.original_locale)), scope: data.scope.split(':')[0], ...(data.scope.startsWith('club:') ? { club_id: Number(data.scope.split(':')[1]) } : {}), ...(data.scope.startsWith('team:') ? { team_id: Number(data.scope.split(':')[1]) } : {}), explanation_images_empty: data.explanation_images.length ? '0' : '1', ...(source.id ? { _method: 'put' } : {}) })).post(source.id ? route('auth.training.exercises.update', source.id) : route('auth.training.exercises.store'), { forceFormData: true, onSuccess: () => { saved = true } })
}
</script>

<template>
    <Head :title="ec(source.id ? 'edit' : 'create')" />
    <div class="mx-auto max-w-4xl space-y-5 pb-16">
        <header class="flex flex-wrap justify-between gap-3"><h1 class="text-2xl font-semibold text-primary">{{ ec(source.id ? 'edit' : 'create') }}</h1><button type="button" :disabled="form.processing" class="rounded-xl border border-border px-4 py-2 text-primary" @click="router.visit(route('auth.training.exercises.index'))">{{ ec('cancel') }}</button></header>
        <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl border border-danger p-4 text-danger"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <form class="space-y-5 rounded-2xl border border-border bg-card p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm text-primary">{{ ec('original') }}<select v-model="form.original_locale" class="mt-2 w-full rounded-xl border-border bg-inputBg" @change="language = form.original_locale"><option v-for="(label, code) in exerciseLanguages" :key="code" :value="code">{{ label }}</option></select></label>
                <label class="text-sm text-primary">{{ ec('language') }}<select v-model="language" class="mt-2 w-full rounded-xl border-border bg-inputBg"><option v-for="(label, code) in exerciseLanguages" :key="code" :value="code">{{ label }}</option></select></label>
            </div>
            <p class="text-sm text-secondary">{{ ec('hint') }}</p>
            <div :lang="language" :dir="language === 'ar' ? 'rtl' : 'ltr'" class="space-y-4">
                <label v-for="field in ['title', 'description', 'instructions', 'notes', 'todos']" :key="field" class="block text-sm font-semibold text-primary">{{ ec(field) }}
                    <input v-if="field === 'title'" v-model="content[field]" :required="language === form.original_locale" minlength="2" maxlength="160" :placeholder="language !== form.original_locale ? form[field] : ''" class="mt-2 w-full rounded-xl border-border bg-inputBg" />
                    <textarea v-else v-model="content[field]" :maxlength="field === 'instructions' ? 10000 : 3000" :rows="field === 'instructions' ? 6 : 3" :placeholder="language !== form.original_locale ? form[field] : ''" class="mt-2 w-full rounded-xl border-border bg-inputBg" />
                </label>
            </div>
            <label class="block text-sm font-semibold text-primary">{{ t('Sportart') }}<input v-model="form.sport_type" maxlength="80" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
            <label v-if="!source.id" class="block text-sm font-semibold text-primary">{{ t('Sichtbarkeit') }}<select v-model="form.scope" class="mt-2 w-full rounded-xl border-border bg-inputBg"><option value="personal">{{ ec('personal') }}</option><option v-for="club in clubs" :key="`club-${club.id}`" :value="`club:${club.id}`">{{ club.name }}</option><option v-for="team in teams" :key="`team-${team.id}`" :value="`team:${team.id}`">{{ team.name }}</option></select></label>
            <label class="block text-sm font-semibold text-primary">{{ t('Niveau') }}<select v-model="form.difficulty" class="mt-2 w-full rounded-xl border-border bg-inputBg"><option value="all">{{ t('Alle') }}</option><option value="beginner">{{ t('Einsteiger') }}</option><option value="intermediate">{{ t('Fortgeschritten') }}</option><option value="advanced">{{ t('Advanced') }}</option></select></label>
            <label class="block text-sm font-semibold text-primary">{{ t('Equipment') }}<textarea v-model="form.equipment" rows="3" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
            <label class="block text-sm font-semibold text-primary">{{ ec('muscles') }}<textarea v-model="form.muscle_groups" rows="3" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
            <label class="block text-sm font-semibold text-primary">{{ t('Video-Link') }}<input v-model="form.video_url" type="url" maxlength="2048" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
            <section class="space-y-4 border-t border-border pt-5"><h2 class="font-semibold text-primary">{{ ec('images') }}</h2><p class="text-sm text-secondary">{{ ec('imageHint') }}</p><label class="block text-sm text-primary">{{ ec('addImages') }}<input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full" @change="addImages" /></label><p v-if="imageError" role="alert" class="text-danger">{{ imageError }}</p>
                <article v-for="(image, index) in form.explanation_images" :key="image.id" class="space-y-3 rounded-xl border border-border p-3"><img :src="previews[image.id]" :alt="image.texts[language]?.alt || ''" class="max-h-64 w-full rounded-xl object-contain" /><p class="text-xs text-secondary">{{ index + 1 }} · {{ exerciseLanguages[language] }}</p><label v-for="field in ['caption', 'alt']" :key="field" class="block text-sm text-primary">{{ ec(field) }}<input :value="image.texts[language]?.[field] || ''" :placeholder="image.texts[form.original_locale]?.[field] || ''" maxlength="500" :dir="language === 'ar' ? 'rtl' : 'ltr'" class="mt-1 w-full rounded-xl border-border bg-inputBg" @input="imageText(image, field, $event.target.value)" /></label><div class="flex flex-wrap gap-2"><button type="button" :disabled="index === 0" class="rounded-lg border border-border px-3 py-1 text-primary disabled:opacity-40" @click="move(index, -1)">{{ ec('up') }}</button><button type="button" :disabled="index === form.explanation_images.length - 1" class="rounded-lg border border-border px-3 py-1 text-primary disabled:opacity-40" @click="move(index, 1)">{{ ec('down') }}</button><button type="button" class="rounded-lg border border-border px-3 py-1 text-danger" @click="form.explanation_images.splice(index, 1)">{{ ec('remove') }}</button></div></article>
            </section>
            <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary disabled:opacity-50">{{ ec('save') }}<span v-if="form.progress"> · {{ form.progress.percentage }}%</span></button>
        </form>
    </div>
</template>
