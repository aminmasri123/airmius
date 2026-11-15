<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { exerciseLanguages, localizedExercise, useExerciseCopy } from '@/support/exerciseContent'

defineOptions({ layout: AppLayout })
const props = defineProps({ exercises: { type: Array, default: () => [] }, plans: { type: Array, default: () => [] } })
const { t, locale } = useI18n()
const ec = useExerciseCopy()
const search = ref('')
const planIds = ref({})
const busy = ref(null)
const errors = ref({})
const exercises = computed(() => props.exercises.map((exercise) => localizedExercise(exercise, locale.value)).filter((exercise) => `${exercise.title} ${exercise.description || ''} ${exercise.sport_type || ''}`.toLowerCase().includes(search.value.toLowerCase())))
const add = (exercise) => { if (!planIds.value[exercise.id] || busy.value) return; busy.value = exercise.id; router.post(route('auth.training.exercises.add-to-plan', exercise.id), { training_plan_id: planIds.value[exercise.id] }, { onError: (value) => { errors.value = value }, onFinish: () => { busy.value = null } }) }
</script>

<template>
    <Head :title="ec('library')" />
    <div class="mx-auto max-w-5xl space-y-5 pb-16">
        <header class="flex flex-wrap justify-between gap-3"><h1 class="text-2xl font-semibold text-primary">{{ ec('library') }}</h1><div class="flex gap-2"><Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-primary">{{ t('Zurück') }}</Link><Link :href="route('auth.training.exercises.create')" class="rounded-xl bg-buttonPrimary px-4 py-2 text-buttonTextPrimary">{{ ec('create') }}</Link></div></header>
        <label class="block text-sm text-primary">{{ t('Suchen') }}<input v-model="search" type="search" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
        <p v-for="(error, key) in errors" :key="key" role="alert" class="text-danger">{{ error }}</p>
        <p v-if="!exercises.length" class="text-secondary">{{ ec('empty') }}</p>
        <article v-for="exercise in exercises" :key="exercise.id" class="space-y-4 rounded-2xl border border-border bg-card p-5">
            <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-semibold text-primary">{{ exercise.title }}</h2><p class="text-sm text-secondary">{{ exercise.sport_type }}{{ exercise.club ? ` · ${exercise.club.name}` : '' }}{{ exercise.team ? ` · ${exercise.team.name}` : '' }}</p></div><Link v-if="exercise.can_edit" :href="route('auth.training.exercises.edit', exercise.id)" class="rounded-xl border border-border px-3 py-2 text-sm text-primary">{{ ec('edit') }}</Link></div>
            <p v-if="exercise.uses_original_fallback" class="text-sm text-secondary">{{ ec('fallback') }} {{ exerciseLanguages[exercise.original_locale] || exercise.original_locale }}</p>
            <div v-for="field in ['description', 'instructions', 'notes']" v-show="exercise[field]" :key="field"><h3 class="text-sm font-semibold text-primary">{{ ec(field) }}</h3><p dir="auto" class="mt-1 whitespace-pre-line text-sm text-secondary">{{ exercise[field] }}</p></div>
            <div v-if="exercise.explanation_images.length" class="grid gap-4 sm:grid-cols-2"><figure v-for="image in exercise.explanation_images" :key="image.id"><a :href="image.url" target="_blank" rel="noopener noreferrer"><img :src="image.url" :alt="image.alt || ''" loading="lazy" class="max-h-64 w-full rounded-xl object-contain" /></a><figcaption class="mt-1 text-sm text-secondary">{{ image.caption }}</figcaption></figure></div>
            <a v-if="exercise.video_url" :href="exercise.video_url" target="_blank" rel="noopener noreferrer" class="inline-block rounded-xl border border-border px-4 py-2 text-sm text-primary">{{ t('Video öffnen') }}</a>
            <form v-if="plans.length" class="flex flex-wrap gap-2 border-t border-border pt-4" @submit.prevent="add(exercise)"><select v-model="planIds[exercise.id]" required :aria-label="ec('plan')" class="min-w-0 flex-1 rounded-xl border-border bg-inputBg text-primary"><option :value="undefined" disabled>{{ ec('plan') }}</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.title }}</option></select><button type="submit" :disabled="busy !== null" class="rounded-xl bg-buttonPrimary px-4 py-2 text-buttonTextPrimary disabled:opacity-50">{{ ec('addToPlan') }}</button></form>
        </article>
    </div>
</template>
