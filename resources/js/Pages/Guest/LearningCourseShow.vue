<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'
import learningContentLocalization from '@/i18n/learningContentLocalization.json'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    course: { type: Object, required: true },
    enrollment: { type: Object, default: null },
    canUseLearningRoom: Boolean,
    myReview: { type: Object, default: null },
    translations: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t, locale } = useI18n()
const enrolling = ref(false)
const localizationCopy = computed(() => learningContentLocalization[locale.value] || learningContentLocalization.de)
const lx = (key, values = {}) => Object.entries(values).reduce(
    (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
    localizationCopy.value[key] || learningContentLocalization.de[key] || key,
)
const allLessons = computed(() => props.course.sections?.flatMap((section) => section.lessons || []) || [])
const firstLesson = computed(() => allLessons.value[0] || null)
const selectedLesson = ref(firstLesson.value)
const isAuthed = computed(() => Boolean(page.props.auth?.user))
const canUseLearningRoom = computed(() => props.canUseLearningRoom || (isAuthed.value && (props.enrollment || page.props.auth?.user?.id === props.course.tutor?.id)))
const canOpenSelectedLesson = computed(() => (canUseLearningRoom.value || Boolean(selectedLesson.value?.is_preview)) && !selectedLesson.value?.locked)
const canTrackSelectedLesson = computed(() => canUseLearningRoom.value && Boolean(props.enrollment) && Boolean(selectedLesson.value))
const isPaidCourse = computed(() => !props.course.is_free)
const completionRequirements = computed(() => {
    const requirements = props.course.completion_requirements
    if (!requirements) return []

    return ['lessons', 'quizzes', 'assignments']
        .map((key) => requirements[key])
        .filter((item) => item && Number(item.total || 0) > 0)
})
const ctaLabel = computed(() => {
    if (canUseLearningRoom.value) return t('guest.learning.cta.room')
    if (isPaidCourse.value && props.course.purchase_url) return t('guest.learning.cta.buy')
    if (isPaidCourse.value) return t('guest.learning.cta.paid')
    return t('guest.learning.cta.free')
})

watch(firstLesson, (lesson) => {
    if (!selectedLesson.value && lesson) selectedLesson.value = lesson
})

watch(allLessons, (lessons) => {
    if (!selectedLesson.value) {
        selectedLesson.value = lessons[0] || null
        return
    }

    const freshLesson = lessons.find((lesson) => lesson.id === selectedLesson.value.id)
    if (freshLesson) selectedLesson.value = freshLesson
})

watch(allLessons, (lessons) => {
    const forms = {}
    lessons.flatMap((lesson) => lesson.assignments || []).forEach((assignment) => {
        forms[String(assignment.id)] = {
            body: assignment.submission?.body || '',
            attachment_url: assignment.submission?.attachment_url || '',
        }
    })
    assignmentForms.value = forms
}, { immediate: true })

const noteForm = useForm({ body: '' })
const commentForm = useForm({ body: '' })
const lessonCompleting = ref(false)
const completionNotice = ref(null)
const completionBadges = ref([])
const quizAnswers = ref({})
const lastProgressSync = ref({})
const assignmentForms = ref({})
const reviewForm = useForm({
    rating: props.myReview?.rating || 5,
    body: props.myReview?.body || '',
})

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0)
    if (value < 60) return `${value} Min.`
    return `${Math.floor(value / 60)} Std. ${value % 60} Min.`
}

const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    : ''

const enroll = () => {
    if (canUseLearningRoom.value) return

    if (isPaidCourse.value) {
        if (props.course.purchase_url) {
            router.visit(props.course.purchase_url)
        }

        return
    }

    if (!isAuthed.value) {
        router.visit(route('login', { redirect: route('guest.learning.courses.show', props.course.id) }))
        return
    }

    router.post(route('auth.learning.courses.enroll', props.course.id), {}, {
        preserveScroll: true,
        onStart: () => { enrolling.value = true },
        onFinish: () => { enrolling.value = false },
    })
}

const submitNote = () => {
    if (!selectedLesson.value) return
    noteForm.post(route('auth.learning.lessons.notes.store', [props.course.id, selectedLesson.value.id]), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    })
}

const submitComment = () => {
    if (!selectedLesson.value) return
    commentForm.post(route('auth.learning.lessons.comments.store', [props.course.id, selectedLesson.value.id]), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    })
}

const completeLesson = async () => {
    if (!selectedLesson.value || !canTrackSelectedLesson.value || selectedLesson.value.completed) return

    lessonCompleting.value = true
    completionNotice.value = null
    try {
        const response = await window.axios.put(
            route('auth.learning.lessons.complete', [props.course.id, selectedLesson.value.id]),
            {},
            { headers: { Accept: 'application/json' } },
        )
        const data = response.data?.data || {}
        selectedLesson.value.completed = true
        if (props.enrollment) {
            props.enrollment.progress_percent = data.progress_percent ?? props.enrollment.progress_percent
            props.enrollment.completed_at = data.completed_at ?? props.enrollment.completed_at
            props.enrollment.certificate = data.certificate ?? props.enrollment.certificate
        }
        if (data.completion_requirements) {
            props.course.completion_requirements = data.completion_requirements
        }
        completionBadges.value = data.new_badges || []
        completionNotice.value = {
            type: 'success',
            message: response.data?.message || t('Lektion abgeschlossen'),
        }
    } catch (error) {
        completionNotice.value = {
            type: 'error',
            message: error.response?.data?.message || t('Die Lektion konnte nicht abgeschlossen werden.'),
        }
    } finally {
        lessonCompleting.value = false
    }
}

const formatSeconds = (seconds) => {
    const value = Math.max(0, Number(seconds || 0))
    const minutes = Math.floor(value / 60)
    const rest = Math.floor(value % 60)

    return `${minutes}:${String(rest).padStart(2, '0')}`
}

const isVideoUrl = (url) => /\.(mp4|webm|ogg)(\?.*)?$/i.test(String(url || ''))

const trackVideoProgress = (event) => {
    if (!selectedLesson.value || !canTrackSelectedLesson.value) return

    const seconds = Math.floor(event.target.currentTime || 0)
    const lessonId = String(selectedLesson.value.id)

    if (seconds < 5 || seconds - (lastProgressSync.value[lessonId] || 0) < 10) return

    lastProgressSync.value = {
        ...lastProgressSync.value,
        [lessonId]: seconds,
    }

    window.axios.put(route('auth.learning.lessons.progress.update', [props.course.id, selectedLesson.value.id]), {
        watch_seconds: seconds,
    }).then((response) => {
        selectedLesson.value.watch_seconds = response.data.watch_seconds
        selectedLesson.value.watch_percent = response.data.watch_percent
        if (response.data.completed) selectedLesson.value.completed = true
    }).catch(() => {})
}

const finishVideoProgress = (event) => {
    if (!selectedLesson.value || !canTrackSelectedLesson.value) return

    const seconds = Math.floor(event.target.currentTime || 0)
    window.axios.put(route('auth.learning.lessons.progress.update', [props.course.id, selectedLesson.value.id]), {
        watch_seconds: seconds,
    }).then((response) => {
        selectedLesson.value.watch_seconds = response.data.watch_seconds
        selectedLesson.value.watch_percent = response.data.watch_percent
        if (response.data.completed) selectedLesson.value.completed = true
    }).catch(() => {})
}

const toggleQuizAnswer = (quiz, question, option) => {
    const quizId = String(quiz.id)
    const questionId = String(question.id)
    const current = quizAnswers.value[quizId]?.[questionId] || []
    const next = current.includes(option)
        ? current.filter((item) => item !== option)
        : [...current, option]

    quizAnswers.value = {
        ...quizAnswers.value,
        [quizId]: {
            ...(quizAnswers.value[quizId] || {}),
            [questionId]: next,
        },
    }
}

const quizAnswerChecked = (quiz, question, option) => (quizAnswers.value[String(quiz.id)]?.[String(question.id)] || []).includes(option)

const submitQuiz = (quiz) => {
    router.post(route('auth.learning.quizzes.attempts.store', [props.course.id, quiz.id]), {
        answers: quizAnswers.value[String(quiz.id)] || {},
    }, { preserveScroll: true })
}

const submitReview = () => {
    reviewForm.post(route('auth.learning.reviews.store', props.course.id), { preserveScroll: true })
}

const submitAssignment = (assignment) => {
    router.post(route('auth.learning.assignments.submissions.store', [props.course.id, assignment.id]), assignmentForms.value[String(assignment.id)] || {}, {
        preserveScroll: true,
    })
}

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(locale.value === 'ar' ? 'ar-EG' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)
</script>

<template>
    <SeoHead
        :title="course.title"
        :description="course.subtitle || course.description || 'Airmius Sportschule Kurs ansehen.'"
        :canonical="seo.canonical || course.show_url"
        :canonical-locale="seo.canonical_locale || course.language"
        :alternates="seo.alternates || []"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1" :lang="course.language" :dir="course.content_direction">
            <section class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div>
                    <Link :href="route('guest.e-learning')" class="text-sm font-semibold text-air-orange">{{ t('Zurück zur Sportschule') }}</Link>
                    <div v-if="course.is_locale_fallback" class="mt-5 rounded-lg border border-air-orange/40 bg-air-orange/10 px-4 py-3 text-sm leading-relaxed text-air-orange" role="status">
                        {{ lx('fallback_notice', { language: lx(course.language) }) }}
                    </div>
                    <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
                        <div class="flex aspect-[16/8] items-center justify-center bg-inputBg">
                            <img v-if="course.cover_image" :src="course.cover_image" :alt="course.title" loading="eager" decoding="async" fetchpriority="high" class="h-full w-full object-cover">
                            <div v-else class="text-center">
                                <i class="las la-graduation-cap text-6xl text-air-orange"></i>
                                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-secondary">{{ t('Sportschule') }}</p>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-air-orange/10 px-3 py-1 text-xs font-bold text-air-orange">{{ course.category }}</span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                                <span v-if="course.sport_type" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.sport_type }}</span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold uppercase text-secondary">{{ course.language }}</span>
                            </div>
                            <h1 class="mt-4 font-heading text-3xl font-900 leading-tight sm:text-5xl">{{ course.title }}</h1>
                            <p class="mt-4 max-w-3xl text-lg leading-relaxed text-secondary">{{ course.subtitle || course.description }}</p>
                            <nav v-if="translations.length > 1" class="mt-4 flex flex-wrap items-center gap-2" :aria-label="lx('available_languages')">
                                <span class="text-xs font-semibold text-secondary">{{ lx('available_languages') }}:</span>
                                <Link
                                    v-for="translation in translations"
                                    :key="translation.locale"
                                    :href="translation.url"
                                    :hreflang="translation.locale"
                                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                                    :class="translation.locale === course.language ? 'border-air-orange bg-air-orange/10 text-air-orange' : 'border-border text-secondary hover:bg-muted'"
                                    :aria-current="translation.locale === course.language ? 'page' : undefined"
                                >
                                    {{ lx(translation.locale) }}
                                </Link>
                            </nav>
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">{{ t('Lektionen') }}</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ course.lessons_count }}</p>
                                </div>
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">{{ t('Kapitel') }}</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ course.sections_count }}</p>
                                </div>
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">{{ t('Dauer') }}</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ formatMinutes(course.estimated_minutes) }}</p>
                                </div>
                            </div>
                            <div v-if="course.reviews_count" class="mt-4 flex flex-wrap items-center gap-2 text-sm text-secondary">
                                <span class="font-bold text-primary">{{ course.average_rating }} / 5</span>
                                <span class="text-air-orange">{{ t('5 Sterne Skala') }}</span>
                                <span>{{ course.reviews_count }} Bewertungen</span>
                            </div>
                        </div>
                    </div>

                    <section class="mt-6 grid gap-6 lg:grid-cols-2">
                        <article class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Das lernst du') }}</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="goal in course.learning_goals" :key="goal" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-check mt-0.5 text-air-orange"></i>
                                    <span>{{ goal }}</span>
                                </li>
                                <li v-if="!course.learning_goals?.length" class="text-sm text-secondary">{{ t('Der Tutor hat noch keine Lernziele hinterlegt.') }}</li>
                            </ul>
                        </article>
                        <article v-if="course.sales_points?.length" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Warum dieser Kurs') }}</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="point in course.sales_points" :key="point" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-star mt-0.5 text-air-blue"></i>
                                    <span>{{ point }}</span>
                                </li>
                            </ul>
                        </article>
                        <article class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Für wen ist der Kurs?') }}</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="group in course.target_groups" :key="group" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-user-check mt-0.5 text-air-orange"></i>
                                    <span>{{ group }}</span>
                                </li>
                                <li v-if="!course.target_groups?.length" class="text-sm text-secondary">{{ t('Geeignet für Sportler, Trainer und Teams mit Interesse am Thema.') }}</li>
                            </ul>
                        </article>
                    </section>

                    <section v-if="course.guarantee_text || course.faq_items?.length" class="mt-6 grid gap-6 lg:grid-cols-2">
                        <article v-if="course.guarantee_text" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Betreuung und Garantie') }}</h2>
                            <p class="mt-3 text-sm leading-relaxed text-secondary whitespace-pre-line">{{ course.guarantee_text }}</p>
                        </article>
                        <article v-if="course.faq_items?.length" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('FAQ') }}</h2>
                            <div class="mt-4 grid gap-3">
                                <div v-for="item in course.faq_items" :key="item.question" class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-sm font-semibold text-primary">{{ item.question }}</p>
                                    <p class="mt-1 text-sm text-secondary">{{ item.answer }}</p>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="mt-6 surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Kursplan') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ t('Kapitel, Themen und Lektionen in Reihenfolge.') }}</p>
                        </div>
                        <div class="divide-y divide-border">
                            <div v-for="section in course.sections" :key="section.id" class="p-5">
                                <h3 class="font-bold text-primary">{{ section.title }}</h3>
                                <p v-if="section.description" class="mt-1 text-sm text-secondary">{{ section.description }}</p>
                                <div class="mt-4 grid gap-2">
                                    <button
                                        v-for="lesson in section.lessons"
                                        :key="lesson.id"
                                        type="button"
                                        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-bg p-3 text-left transition hover:border-air-orange"
                                        @click="selectedLesson = lesson"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-primary">{{ lesson.title }}</p>
                                            <p class="text-xs text-secondary">{{ lesson.type }} - {{ formatMinutes(lesson.duration_minutes) }}</p>
                                        </div>
                                        <span v-if="lesson.completed" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">{{ t('Erledigt') }}</span>
                                        <span v-else-if="lesson.drip_locked" class="rounded-full bg-warning/10 px-2 py-1 text-xs font-semibold text-warning">{{ t('Ab') }} {{ formatDateTime(lesson.available_at) }}</span>
                                        <span v-else-if="lesson.is_preview || canUseLearningRoom" class="rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">{{ t('Öffnen') }}</span>
                                        <i v-else class="las la-lock text-secondary"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="course.reviews?.length" class="mt-6 surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <h2 class="text-lg font-bold text-primary">{{ t('Bewertungen') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ t('Echte Rückmeldungen von eingeschriebenen Teilnehmern.') }}</p>
                        </div>
                        <div class="grid gap-3 p-5 md:grid-cols-2">
                            <article v-for="review in course.reviews" :key="review.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-primary">{{ review.user?.name || 'Teilnehmer' }}</p>
                                    <p class="text-sm font-bold text-air-orange">{{ review.rating }} / 5</p>
                                </div>
                                <p v-if="review.body" class="mt-2 text-sm leading-relaxed text-secondary">{{ review.body }}</p>
                            </article>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start">
                    <article class="surface-card p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-orange">{{ t('Tutor') }}</p>
                        <div class="mt-3 flex items-center gap-3">
                            <img v-if="course.tutor?.profile_photo_path" :src="course.tutor.profile_photo_path" :alt="course.tutor.name" loading="lazy" decoding="async" class="h-12 w-12 rounded-full object-cover">
                            <div v-else class="flex h-12 w-12 items-center justify-center rounded-full bg-inputBg font-bold text-primary">
                                {{ course.tutor?.name?.slice(0, 2) || 'AI' }}
                            </div>
                            <div>
                                <p class="font-bold text-primary">{{ course.tutor?.name || 'Airmius Tutor' }}</p>
                                <p class="text-sm text-secondary">{{ t('Trainer / Kursleitung') }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-5 w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="enrolling || (isPaidCourse && !canUseLearningRoom && !course.purchase_url)"
                            :aria-busy="enrolling"
                            @click="enroll"
                        >
                            {{ enrolling ? t('Wird eingeschrieben…') : ctaLabel }}
                        </button>
                        <p class="mt-3 text-xs text-secondary">
                            <span v-if="isPaidCourse && !canUseLearningRoom">
                                {{ t('guest.learning.cta.price') }} {{ formatMoney(course.price_cents, course.currency) }}.
                                <template v-if="course.purchase_url">{{ t('Nach dem Kauf wird der Kurs deinem Konto freigeschaltet.') }}</template>
                                <template v-else>{{ t('Der Kaufzugang ist noch nicht verknüpft.') }}</template>
                            </span>
                            <span v-else>
                                {{ t('Nach der Einschreibung kannst du Notizen schreiben und Fragen im Kurs posten.') }}
                            </span>
                        </p>
                        <div v-if="enrollment" class="mt-4 rounded-lg border border-border bg-bg p-3">
                            <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                <span>{{ t('Fortschritt') }}</span>
                                <span>{{ enrollment.progress_percent || 0 }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                <div class="h-full rounded-full bg-air-orange" :style="{ width: `${enrollment.progress_percent || 0}%` }"></div>
                            </div>
                        </div>
                        <div v-if="completionRequirements.length" class="mt-4 rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ t('Abschlussanforderungen') }}</p>
                            <div class="mt-3 grid gap-2">
                                <div v-for="item in completionRequirements" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-secondary">{{ item.label }}</span>
                                    <span class="font-semibold text-primary">{{ item.completed }} / {{ item.total }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="enrollment?.certificate" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                            <p class="font-bold">{{ t('Kurs abgeschlossen') }}</p>
                            <p class="mt-1 text-xs">{{ t('guest.learning.cta.certificate') }} {{ enrollment.certificate.code }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a v-if="enrollment.certificate.download_url" :href="enrollment.certificate.download_url" class="inline-flex rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    {{ t('Zertifikat PDF') }}
                                </a>
                                <a v-if="enrollment.certificate.verify_url" :href="enrollment.certificate.verify_url" class="inline-flex rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    {{ t('Öffentlich prüfen') }}
                                </a>
                            </div>
                        </div>
                        <div v-if="completionBadges.length" class="mt-4 rounded-lg border border-air-orange/30 bg-air-orange/10 p-3 text-sm text-primary" role="status">
                            <p class="font-bold">{{ t('Neuer Badge erhalten') }}</p>
                            <div v-for="award in completionBadges" :key="award.id" class="mt-2 flex items-start gap-2">
                                <i :class="[award.badge?.icon || 'las la-graduation-cap', 'mt-0.5 text-xl text-air-orange']"></i>
                                <div>
                                    <p class="font-semibold">{{ award.badge?.name }}</p>
                                    <p v-if="award.badge?.description" class="mt-1 text-xs text-secondary">{{ award.badge.description }}</p>
                                </div>
                            </div>
                            <Link :href="route('auth.badges.index')" class="mt-3 inline-flex rounded-lg border border-air-orange/40 px-3 py-2 text-xs font-semibold text-air-orange">
                                {{ t('Meine Badges öffnen') }}
                            </Link>
                        </div>
                    </article>

                    <article v-if="selectedLesson" class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ t('Lernraum') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">{{ selectedLesson.title }}</h2>
                        </div>
                        <div class="p-5">
                            <p class="text-sm leading-relaxed text-secondary">{{ selectedLesson.summary || 'Wähle eine Lektion, um Inhalte, Notizen und Fragen zu bearbeiten.' }}</p>
                            <div v-if="canOpenSelectedLesson" class="mt-5 grid gap-4">
                                <div v-if="selectedLesson.content" class="rounded-lg border border-border bg-bg p-4 text-sm leading-relaxed text-primary whitespace-pre-line">{{ selectedLesson.content }}</div>
                                <div v-if="selectedLesson.video_url && isVideoUrl(selectedLesson.video_url)" class="overflow-hidden rounded-lg border border-border bg-black">
                                    <video
                                        class="aspect-video w-full"
                                        controls
                                        playsinline
                                        :src="selectedLesson.secure_video_url || selectedLesson.video_url"
                                        @timeupdate="trackVideoProgress"
                                        @ended="finishVideoProgress"
                                    ></video>
                                </div>
                                <a v-else-if="selectedLesson.video_url" :href="selectedLesson.video_url" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">{{ t('Video öffnen') }}</a>
                                <div v-if="canTrackSelectedLesson && selectedLesson.video_url" class="rounded-lg border border-border bg-bg p-3">
                                    <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                        <span>{{ t('Video-Fortschritt') }}</span>
                                        <span>{{ selectedLesson.watch_percent || 0 }}% - {{ formatSeconds(selectedLesson.watch_seconds) }}</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                        <div class="h-full rounded-full bg-air-blue" :style="{ width: `${selectedLesson.watch_percent || 0}%` }"></div>
                                    </div>
                                    <p class="mt-2 text-xs text-secondary">{{ t('Ab 80% Watch-Time wird die Lektion automatisch abgeschlossen.') }}</p>
                                </div>
                                <div v-if="selectedLesson.attachments?.length" class="grid gap-2">
                                    <a v-for="attachment in selectedLesson.attachments" :key="attachment.url || attachment.name" :href="attachment.url" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        {{ attachment.name || t('Material öffnen') }}
                                    </a>
                                </div>
                                <div v-if="selectedLesson.assignments?.length" class="grid gap-3">
                                    <article v-for="assignment in selectedLesson.assignments" :key="assignment.id" class="rounded-lg border border-border bg-bg p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <h3 class="font-semibold text-primary">{{ assignment.title }}</h3>
                                            <div class="flex flex-wrap gap-2">
                                                <span v-if="assignment.is_required" class="rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">{{ t('Pflicht') }}</span>
                                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ assignment.points }} Punkte</span>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-secondary whitespace-pre-line">{{ assignment.instructions }}</p>
                                        <p v-if="assignment.due_at" class="mt-2 text-xs font-semibold text-secondary">{{ t('Fällig bis') }} {{ formatDateTime(assignment.due_at) }}</p>
                                        <p v-if="assignment.submission" class="mt-3 text-xs font-semibold" :class="assignment.submission.status === 'passed' ? 'text-success' : 'text-warning'">
                                            {{ t('Status:') }} {{ assignment.submission.status }}<span v-if="assignment.submission.score !== null"> - {{ assignment.submission.score }} {{ t('Punkte') }}</span>
                                        </p>
                                        <p v-if="assignment.submission?.feedback" class="mt-2 rounded-lg bg-card p-3 text-sm text-secondary">{{ assignment.submission.feedback }}</p>
                                        <form v-if="canUseLearningRoom" class="mt-3 grid gap-2" @submit.prevent="submitAssignment(assignment)">
                                            <textarea v-model="assignmentForms[String(assignment.id)].body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('Deine Antwort')" :placeholder="t('Deine Antwort')"></textarea>
                                            <input v-model="assignmentForms[String(assignment.id)].attachment_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('Optionaler Link zum Anhang')" :placeholder="t('Optionaler Link zum Anhang')">
                                            <button type="submit" class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ t('Aufgabe einreichen') }}</button>
                                        </form>
                                    </article>
                                </div>
                                <button
                                    v-if="canTrackSelectedLesson"
                                    type="button"
                                    class="rounded-lg border px-4 py-2 text-sm font-semibold"
                                    :class="selectedLesson.completed ? 'border-success/30 bg-success/10 text-success' : 'border-border text-primary hover:bg-muted'"
                                    :disabled="selectedLesson.completed || lessonCompleting"
                                    :aria-busy="lessonCompleting"
                                    @click="completeLesson"
                                >
                                    {{ lessonCompleting ? t('Wird abgeschlossen…') : (selectedLesson.completed ? t('Lektion abgeschlossen') : t('Lektion abschließen')) }}
                                </button>
                                <p
                                    v-if="completionNotice"
                                    role="status"
                                    class="rounded-lg border px-3 py-2 text-sm font-semibold"
                                    :class="completionNotice.type === 'error' ? 'border-error/30 bg-error/10 text-error' : 'border-success/30 bg-success/10 text-success'"
                                >
                                    {{ completionNotice.message }}
                                </p>
                                <form v-if="canUseLearningRoom" class="grid gap-2" @submit.prevent="submitNote">
                                    <textarea v-model="noteForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('Private Notiz zu dieser Lektion')" :placeholder="t('Private Notiz zu dieser Lektion')"></textarea>
                                    <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">{{ t('Notiz speichern') }}</button>
                                </form>
                                <form v-if="canUseLearningRoom" class="grid gap-2" @submit.prevent="submitComment">
                                    <textarea v-model="commentForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('Frage an den Tutor oder Kurschat')" :placeholder="t('Frage an den Tutor oder Kurschat')"></textarea>
                                    <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ t('Frage senden') }}</button>
                                </form>
                                <div v-if="canUseLearningRoom && selectedLesson.comments?.length" class="grid gap-3">
                                    <p class="text-xs font-semibold uppercase text-secondary">{{ t('Fragen und Antworten') }}</p>
                                    <article v-for="comment in selectedLesson.comments" :key="comment.id" class="rounded-lg border border-border bg-bg p-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-primary">{{ comment.user?.name || 'Teilnehmer' }}</p>
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="comment.status === 'resolved' ? 'bg-success/10 text-success' : comment.status === 'answered' ? 'bg-air-blue/10 text-air-blue' : 'bg-warning/10 text-warning'">
                                                {{ comment.status }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm leading-relaxed text-secondary">{{ comment.body }}</p>
                                        <div v-if="comment.replies?.length" class="mt-3 grid gap-2 border-l border-border pl-3">
                                            <div v-for="reply in comment.replies" :key="reply.id" class="rounded-lg bg-card p-3">
                                                <p class="text-xs font-semibold text-air-blue">{{ reply.user?.name || 'Tutor' }}</p>
                                                <p class="mt-1 text-sm leading-relaxed text-secondary">{{ reply.body }}</p>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                                <div v-if="!canUseLearningRoom" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                                    {{ t('Das ist eine freigegebene Vorschau. Für Notizen und Kursfragen brauchst du Zugriff auf den Kurs.') }}
                                </div>
                            </div>
                            <div v-else-if="selectedLesson.drip_locked" class="mt-5 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
                                {{ t('guest.learning.cta.lesson_unlock', { date: formatDateTime(selectedLesson.available_at) }) }}
                            </div>
                            <div v-else class="mt-5 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                                {{ t('Schreibe dich ein, um Inhalte, Notizen und Kursfragen zu nutzen.') }}
                            </div>
                        </div>
                    </article>

                    <article v-if="canUseLearningRoom && course.quizzes?.length" class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ t('Wissenscheck') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">{{ t('Quiz') }}</h2>
                        </div>
                        <div class="grid gap-4 p-5">
                            <form v-for="quiz in course.quizzes" :key="quiz.id" class="rounded-lg border border-border bg-bg p-4" @submit.prevent="submitQuiz(quiz)">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-primary">{{ quiz.title }}</p>
                                        <p v-if="quiz.description" class="mt-1 text-sm text-secondary">{{ quiz.description }}</p>
                                    </div>
                                    <span v-if="quiz.attempt" class="rounded-full px-2 py-1 text-xs font-semibold" :class="quiz.attempt.passed ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                        {{ quiz.attempt.score_percent }}%
                                    </span>
                                    <span v-else-if="quiz.locked" class="rounded-full bg-warning/10 px-2 py-1 text-xs font-semibold text-warning">
                                        {{ t('Ab') }} {{ formatDateTime(quiz.available_at) }}
                                    </span>
                                </div>
                                <div v-if="quiz.locked" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                                    {{ t('Dieser Wissenscheck wird zusammen mit der Lektion freigeschaltet.') }}
                                </div>
                                <div v-else class="mt-4 grid gap-4">
                                    <fieldset v-for="question in quiz.questions" :key="question.id" class="grid gap-2">
                                        <legend class="text-sm font-semibold text-primary">{{ question.question }}</legend>
                                        <label v-for="option in question.options" :key="option" class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                            <input type="checkbox" class="rounded border-border bg-inputBg" :checked="quizAnswerChecked(quiz, question, option)" @change="toggleQuizAnswer(quiz, question, option)">
                                            <span>{{ option }}</span>
                                        </label>
                                    </fieldset>
                                </div>
                                <button type="submit" :disabled="quiz.locked" class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60">
                                    {{ t('Quiz abgeben') }}
                                </button>
                            </form>
                        </div>
                    </article>

                    <article v-if="enrollment" class="surface-card p-5">
                        <h2 class="text-lg font-bold text-primary">{{ t('Kurs bewerten') }}</h2>
                        <form class="mt-4 grid gap-3" @submit.prevent="submitReview">
                            <div class="flex gap-2">
                                <button
                                    v-for="rating in [1, 2, 3, 4, 5]"
                                    :key="rating"
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-sm font-bold"
                                    :class="reviewForm.rating >= rating ? 'border-air-orange bg-air-orange/10 text-air-orange' : 'border-border text-secondary'"
                                    @click="reviewForm.rating = rating"
                                >
                                    {{ rating }}
                                </button>
                            </div>
                            <textarea v-model="reviewForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="t('Was hat dir geholfen?')" :placeholder="t('Was hat dir geholfen?')"></textarea>
                            <button type="submit" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                {{ t('Bewertung speichern') }}
                            </button>
                        </form>
                    </article>
                </aside>
            </section>
        </main>

        <Footer />
    </div>
</template>
