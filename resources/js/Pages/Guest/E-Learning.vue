<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    learningProducts: { type: Array, default: () => [] },
    learningCourses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    facets: { type: Object, default: () => ({ categories: [], levels: [] }) },
})

const courses = [
    ['las la-calendar-alt', 'guest.learning.courses.organization.title', 'guest.learning.courses.organization.text'],
    ['las la-comments', 'guest.learning.courses.communication.title', 'guest.learning.courses.communication.text'],
    ['las la-chart-pie', 'guest.learning.courses.analysis.title', 'guest.learning.courses.analysis.text'],
    ['las la-shield-alt', 'guest.learning.courses.privacy.title', 'guest.learning.courses.privacy.text'],
]

const steps = [
    'guest.learning.steps.learn',
    'guest.learning.steps.apply',
    'guest.learning.steps.improve',
]

const { locale } = useI18n()
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)
</script>

<template>
    <SeoHead
        :title="$t('E-Learning für Sportorganisation')"
        :description="$t('Lerne moderne Sportorganisation mit Airmius: Kommunikation, Trainingsplanung, Datenschutz und digitale Vereinsprozesse einfach erklaert.')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[.95fr_1.05fr]">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-wider text-air-orange">{{ $t('E-Learning') }}</span>
                    <h1 class="mt-3 font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ $t('guest.learning.hero.title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                        {{ $t('guest.learning.hero.subtitle') }}
                    </p>
                    <div class="mt-8">
                        <Link
                            :href="canLogin ? route('auth.learning.studio.index') : route('register')"
                            class="inline-flex rounded-full bg-air-orange px-6 py-3 font-bold text-white transition hover:bg-orange-600"
                        >
                            {{ $t('Sportschule starten') }}
                        </Link>
                    </div>
                </div>

                <div class="surface-card p-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="(step, index) in steps" :key="step" class="rounded-lg border border-border bg-inputBg p-4">
                            <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-full bg-air-orange text-sm font-bold text-white">
                                {{ index + 1 }}
                            </div>
                            <p class="text-sm font-semibold text-primary">{{ $t(step) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-16 max-w-7xl pb-20">
                <div v-if="learningCourses.length" class="mb-12">
                    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-primary">{{ $t('Online-Sportschule') }}</h2>
                            <p class="mt-2 text-secondary">{{ $t('Strukturierte Kurse mit Kapiteln, Lektionen, Quiz, Notizen und Tutor-Betreuung.') }}</p>
                        </div>
                        <Link :href="canLogin ? route('auth.learning.studio.index') : route('register')" class="text-sm font-semibold text-air-orange">
                            {{ $t('Tutor-Studio öffnen') }}
                        </Link>
                    </div>

                    <form class="mb-8 grid gap-3 rounded-lg border border-border bg-card p-4 lg:grid-cols-[minmax(0,1fr)_12rem_12rem_10rem_auto]" method="get" :action="route('guest.e-learning')">
                        <input name="q" :value="filters.q" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="$t('Kurse suchen')" :placeholder="$t('Kurse suchen')">
                        <select name="category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="">{{ $t('Alle Kategorien') }}</option>
                            <option v-for="category in facets.categories" :key="category" :value="category" :selected="filters.category === category">{{ category }}</option>
                        </select>
                        <select name="level" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="">{{ $t('Alle Level') }}</option>
                            <option v-for="level in facets.levels" :key="level" :value="level" :selected="filters.level === level">{{ level }}</option>
                        </select>
                        <select name="price" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="">{{ $t('Alle Preise') }}</option>
                            <option value="free" :selected="filters.price === 'free'">{{ $t('Kostenlos') }}</option>
                            <option value="paid" :selected="filters.price === 'paid'">{{ $t('Kostenpflichtig') }}</option>
                        </select>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ $t('Filtern') }}</button>
                    </form>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="course in learningCourses" :key="course.id" class="surface-card overflow-hidden">
                            <Link :href="course.show_url" class="block">
                                <div class="flex aspect-[16/9] items-center justify-center bg-inputBg">
                                    <img v-if="course.cover_image" :src="course.cover_image" :alt="course.title" class="h-full w-full object-cover">
                                    <i v-else class="las la-graduation-cap text-5xl text-air-orange"></i>
                                </div>
                            </Link>
                            <div class="p-5">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-air-orange/10 px-3 py-1 text-xs font-bold text-air-orange">{{ course.category }}</span>
                                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                                </div>
                                <Link :href="course.show_url" class="text-lg font-bold text-primary hover:text-air-orange">
                                    {{ course.title }}
                                </Link>
                                <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-secondary">{{ course.subtitle || course.description }}</p>
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
                </div>

                <div v-else-if="learningProducts.length" class="mb-12">
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-primary">{{ $t('Aktuelle Kurse und Trainingspläne') }}</h2>
                        <p class="mt-2 text-secondary">{{ $t('Bestehende digitale Angebote, bis die Sportschule vollständig befüllt ist.') }}</p>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-primary">{{ $t('guest.learning.library') }}</h2>
                    <p class="mt-2 text-secondary">{{ $t('guest.learning.library_text') }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <article v-for="[icon, title, text] in courses" :key="title" class="surface-card p-5">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[icon, 'text-2xl text-air-orange']"></i>
                        </div>
                        <h3 class="font-bold text-primary">{{ $t(title) }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                    </article>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
