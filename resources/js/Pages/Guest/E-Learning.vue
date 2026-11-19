<script setup>
import { Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import learningContentLocalization from '@/i18n/learningContentLocalization.json'
import LearningCourseCover from '@/Components/Learning/LearningCourseCover.vue'
import { learningText } from '@/support/learningExperience'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    learningProducts: { type: Array, default: () => [] },
    learningCourses: { type: Object, default: () => ({ data: [], links: [], total: 0 }) },
    filters: { type: Object, default: () => ({}) },
    facets: { type: Object, default: () => ({ categories: [], levels: [] }) },
})

const { locale } = useI18n()
const ex = key => learningText(locale.value,key)
const localizationCopy = computed(() => learningContentLocalization[locale.value] || learningContentLocalization.de)
const lx = (key, values = {}) => Object.entries(values).reduce(
    (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
    localizationCopy.value[key] || learningContentLocalization.de[key] || key,
)
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)
const courseItems = computed(() => props.learningCourses?.data || [])
const courseLinks = computed(() => (props.learningCourses?.links || []).filter((link) => link.url))
const filtering = ref(false)
const filterForm = reactive({
    q: props.filters.q || '',
    category: props.filters.category || '',
    level: props.filters.level || '',
    price: props.filters.price || '',
    language: props.filters.language || '',
})
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

watch(() => props.filters, (filters) => {
    filterForm.q = filters?.q || ''
    filterForm.category = filters?.category || ''
    filterForm.level = filters?.level || ''
    filterForm.price = filters?.price || ''
    filterForm.language = filters?.language || ''
})

const applyFilters = () => {
    const query = Object.fromEntries(
        Object.entries(filterForm).filter(([, value]) => String(value || '').trim() !== ''),
    )

    router.get(route('guest.e-learning'), query, {
        only: ['learningCourses', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { filtering.value = true },
        onFinish: () => { filtering.value = false },
    })
}
</script>

<template>
    <SeoHead
        :title="$t('E-Learning für Sportorganisation')"
        :description="$t('Lerne moderne Sportorganisation mit Airmius: Kommunikation, Trainingsplanung, Datenschutz und digitale Vereinsprozesse einfach erklärt.')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1">
            <section class="mx-auto max-w-7xl">
              <p class="text-sm font-semibold text-air-blue">{{ ex('discover') }}</p>
              <h1 class="mt-2 text-3xl font-bold">{{ ex('intro') }}</h1>
              <div class="mt-4 flex flex-wrap gap-3">
                <Link :href="route('auth.learning.my-courses.index')" class="rounded-lg border border-border px-4 py-2">{{ ex('mine') }}</Link>
                <Link :href="canLogin ? route('auth.learning.studio.index') : route('register')" class="rounded-lg border border-border px-4 py-2">{{ $t('Tutor-Studio öffnen') }}</Link>
              </div>
            </section>
            <section class="mx-auto mt-6 max-w-7xl pb-20">
                <div class="mb-12">
                    <form class="mb-8 grid gap-3 rounded-lg border border-border bg-card p-4 lg:grid-cols-6" method="get" :action="route('guest.e-learning')" @submit.prevent="applyFilters">
                        <input v-model="filterForm.q" name="q" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="$t('Kurse suchen')" :placeholder="$t('Kurse suchen')">
                        <select v-model="filterForm.category" name="category" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="$t('Alle Kategorien')">
                            <option value="">{{ $t('Alle Kategorien') }}</option>
                            <option v-for="category in facets.categories" :key="category" :value="category">{{ category }}</option>
                        </select>
                        <select v-model="filterForm.level" name="level" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="$t('Alle Level')">
                            <option value="">{{ $t('Alle Level') }}</option>
                            <option v-for="level in facets.levels" :key="level" :value="level">{{ level }}</option>
                        </select>
                        <select v-model="filterForm.price" name="price" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="$t('Alle Preise')">
                            <option value="">{{ $t('Alle Preise') }}</option>
                            <option value="free">{{ $t('Kostenlos') }}</option>
                            <option value="paid">{{ $t('Kostenpflichtig') }}</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60" :disabled="filtering">
                            {{ filtering ? $t('Wird geladen ...') : $t('Filtern') }}
                        </button>
                        <select v-model="filterForm.language" name="language" class="rounded-lg border-border bg-inputBg text-sm" :aria-label="ex('language')"><option value="">{{ ex('language') }} · {{ ex('all') }}</option><option v-for="language in ['de','en','fr','ar']" :key="language" :value="language">{{ lx(language) }}</option></select>
                        <button type="button" class="text-sm underline" @click="Object.keys(filterForm).forEach(key=>filterForm[key]='');applyFilters()">{{ ex('reset') }}</button>
                    </form>
                    <p v-if="!courseItems.length" role="status" class="rounded-xl border border-dashed border-border p-8 text-center">{{ ex('empty') }}</p>

                    <p class="sr-only" aria-live="polite">{{ filtering ? $t('Wird geladen ...') : `${learningCourses.total || courseItems.length} ${$t('Kurse')}` }}</p>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="course in courseItems" :key="course.id" class="surface-card overflow-hidden" :lang="course.language" :dir="course.content_direction">
                            <Link :href="course.show_url" class="block">
                                <LearningCourseCover :src="course.cover_image" :title="course.title" />
                            </Link>
                            <div class="p-5">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-air-orange/10 px-3 py-1 text-xs font-bold text-air-orange">{{ course.category }}</span>
                                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold uppercase text-secondary">{{ course.language }}</span>
                                </div>
                                <Link :href="course.show_url" class="text-lg font-bold text-primary hover:text-air-orange">
                                    {{ course.title }}
                                </Link>
                                <p class="mt-2 text-sm">{{ ex('instructor') }}: {{ course.tutor?.name }}</p>
                                <p v-if="course.reviews_count" class="mt-2 text-sm">★ {{ course.average_rating }} · {{ course.reviews_count }}</p>
                                <p class="mt-2 font-bold">{{ course.is_free ? ex('free') : formatMoney(course.price_cents,course.currency) }}</p>
                                <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-secondary">{{ course.subtitle || course.description }}</p>
                                <p v-if="course.is_locale_fallback" class="mt-3 rounded-lg border border-air-orange/35 bg-air-orange/10 px-3 py-2 text-xs leading-relaxed text-air-orange" role="status">
                                    {{ lx('fallback_notice', { language: lx(course.language) }) }}
                                </p>
                                <ul v-if="course.learning_goals?.length" class="mt-4 space-y-2 text-sm text-secondary">
                                    <li v-for="goal in course.learning_goals.slice(0, 3)" :key="goal" class="flex gap-2">
                                        <i class="las la-check mt-0.5 text-air-orange"></i>
                                        <span>{{ goal }}</span>
                                    </li>
                                </ul>
                                <div class="mt-5 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase text-secondary">{{ $t('Umfang') }}</p>
                                        <p class="text-xl font-black text-primary">{{ course.lessons_count }} {{ $t('Lektionen') }}</p>
                                        <p class="text-xs text-secondary">{{ course.estimated_minutes }} {{ $t('Minuten') }}</p>
                                    </div>
                                    <Link :href="course.show_url" class="rounded-full bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary">
                                        {{ $t('Ansehen') }}
                                    </Link>
                                </div>
                            </div>
                        </article>
                    </div>

                    <nav v-if="courseLinks.length > 1" class="mt-8 flex flex-wrap justify-center gap-2" :aria-label="$t('Seitennavigation')">
                        <Link
                            v-for="link in courseLinks"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            :only="['learningCourses', 'filters']"
                            preserve-state
                            preserve-scroll
                            class="rounded border px-3 py-2 text-sm font-bold"
                            :class="link.active ? 'border-borderHover bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </nav>
                </div>

            </section>
        </main>

        <Footer />
    </div>
</template>
