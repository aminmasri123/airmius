<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    course: { type: Object, required: true },
    enrollment: { type: Object, default: null },
    canUseLearningRoom: Boolean,
    myReview: { type: Object, default: null },
})

const page = usePage()
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
    if (canUseLearningRoom.value) return 'Zum Lernraum'
    if (isPaidCourse.value && props.course.purchase_url) return 'Kurs kaufen'
    if (isPaidCourse.value) return 'Kostenpflichtiger Kurs'
    return 'Kostenlos einschreiben'
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
const lessonCompleteForm = useForm({})
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
    ? new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
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

    router.post(route('auth.learning.courses.enroll', props.course.id), {}, { preserveScroll: true })
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

const completeLesson = () => {
    if (!selectedLesson.value || !canTrackSelectedLesson.value || selectedLesson.value.completed) return

    lessonCompleteForm.put(route('auth.learning.lessons.complete', [props.course.id, selectedLesson.value.id]), {
        preserveScroll: true,
    })
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

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)
</script>

<template>
    <SeoHead :title="course.title" :description="course.subtitle || course.description || 'Airmius Sportschule Kurs ansehen.'" />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div>
                    <Link :href="route('guest.e-learning')" class="text-sm font-semibold text-air-orange">Zurück zur Sportschule</Link>
                    <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
                        <div class="flex aspect-[16/8] items-center justify-center bg-inputBg">
                            <img v-if="course.cover_image" :src="course.cover_image" :alt="course.title" class="h-full w-full object-cover">
                            <div v-else class="text-center">
                                <i class="las la-graduation-cap text-6xl text-air-orange"></i>
                                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-secondary">Airmius Sportschule</p>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-air-orange/10 px-3 py-1 text-xs font-bold text-air-orange">{{ course.category }}</span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                                <span v-if="course.sport_type" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ course.sport_type }}</span>
                            </div>
                            <h1 class="mt-4 font-heading text-3xl font-900 leading-tight sm:text-5xl">{{ course.title }}</h1>
                            <p class="mt-4 max-w-3xl text-lg leading-relaxed text-secondary">{{ course.subtitle || course.description }}</p>
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">Lektionen</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ course.lessons_count }}</p>
                                </div>
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">Kapitel</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ course.sections_count }}</p>
                                </div>
                                <div class="rounded-lg border border-border bg-bg p-3">
                                    <p class="text-xs uppercase text-secondary">Dauer</p>
                                    <p class="mt-1 text-xl font-black text-primary">{{ formatMinutes(course.estimated_minutes) }}</p>
                                </div>
                            </div>
                            <div v-if="course.reviews_count" class="mt-4 flex flex-wrap items-center gap-2 text-sm text-secondary">
                                <span class="font-bold text-primary">{{ course.average_rating }} / 5</span>
                                <span class="text-air-orange">5 Sterne Skala</span>
                                <span>{{ course.reviews_count }} Bewertungen</span>
                            </div>
                        </div>
                    </div>

                    <section class="mt-6 grid gap-6 lg:grid-cols-2">
                        <article class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">Das lernst du</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="goal in course.learning_goals" :key="goal" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-check mt-0.5 text-air-orange"></i>
                                    <span>{{ goal }}</span>
                                </li>
                                <li v-if="!course.learning_goals?.length" class="text-sm text-secondary">Der Tutor hat noch keine Lernziele hinterlegt.</li>
                            </ul>
                        </article>
                        <article v-if="course.sales_points?.length" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">Warum dieser Kurs</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="point in course.sales_points" :key="point" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-star mt-0.5 text-air-blue"></i>
                                    <span>{{ point }}</span>
                                </li>
                            </ul>
                        </article>
                        <article class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">Für wen ist der Kurs?</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="group in course.target_groups" :key="group" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-user-check mt-0.5 text-air-orange"></i>
                                    <span>{{ group }}</span>
                                </li>
                                <li v-if="!course.target_groups?.length" class="text-sm text-secondary">Geeignet für Sportler, Trainer und Teams mit Interesse am Thema.</li>
                            </ul>
                        </article>
                    </section>

                    <section v-if="course.guarantee_text || course.faq_items?.length" class="mt-6 grid gap-6 lg:grid-cols-2">
                        <article v-if="course.guarantee_text" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">Betreuung und Garantie</h2>
                            <p class="mt-3 text-sm leading-relaxed text-secondary whitespace-pre-line">{{ course.guarantee_text }}</p>
                        </article>
                        <article v-if="course.faq_items?.length" class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">FAQ</h2>
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
                            <h2 class="text-lg font-bold text-primary">Kursplan</h2>
                            <p class="mt-1 text-sm text-secondary">Kapitel, Themen und Lektionen in Reihenfolge.</p>
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
                                        <span v-if="lesson.completed" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">Erledigt</span>
                                        <span v-else-if="lesson.drip_locked" class="rounded-full bg-warning/10 px-2 py-1 text-xs font-semibold text-warning">Ab {{ formatDateTime(lesson.available_at) }}</span>
                                        <span v-else-if="lesson.is_preview || canUseLearningRoom" class="rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">Öffnen</span>
                                        <i v-else class="las la-lock text-secondary"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="course.reviews?.length" class="mt-6 surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <h2 class="text-lg font-bold text-primary">Bewertungen</h2>
                            <p class="mt-1 text-sm text-secondary">Echte Rückmeldungen von eingeschriebenen Teilnehmern.</p>
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-orange">Tutor</p>
                        <div class="mt-3 flex items-center gap-3">
                            <img v-if="course.tutor?.profile_photo_path" :src="course.tutor.profile_photo_path" :alt="course.tutor.name" class="h-12 w-12 rounded-full object-cover">
                            <div v-else class="flex h-12 w-12 items-center justify-center rounded-full bg-inputBg font-bold text-primary">
                                {{ course.tutor?.name?.slice(0, 2) || 'AI' }}
                            </div>
                            <div>
                                <p class="font-bold text-primary">{{ course.tutor?.name || 'Airmius Tutor' }}</p>
                                <p class="text-sm text-secondary">Trainer / Kursleitung</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-5 w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isPaidCourse && !canUseLearningRoom && !course.purchase_url"
                            @click="enroll"
                        >
                            {{ ctaLabel }}
                        </button>
                        <p class="mt-3 text-xs text-secondary">
                            <span v-if="isPaidCourse && !canUseLearningRoom">
                                Preis: {{ formatMoney(course.price_cents, course.currency) }}.
                                <template v-if="course.purchase_url">Nach dem Kauf wird der Kurs deinem Konto freigeschaltet.</template>
                                <template v-else>Der Kaufzugang ist noch nicht verknüpft.</template>
                            </span>
                            <span v-else>
                                Nach der Einschreibung kannst du Notizen schreiben und Fragen im Kurs posten.
                            </span>
                        </p>
                        <div v-if="enrollment" class="mt-4 rounded-lg border border-border bg-bg p-3">
                            <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                <span>Fortschritt</span>
                                <span>{{ enrollment.progress_percent || 0 }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                <div class="h-full rounded-full bg-air-orange" :style="{ width: `${enrollment.progress_percent || 0}%` }"></div>
                            </div>
                        </div>
                        <div v-if="completionRequirements.length" class="mt-4 rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">Abschlussanforderungen</p>
                            <div class="mt-3 grid gap-2">
                                <div v-for="item in completionRequirements" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-secondary">{{ item.label }}</span>
                                    <span class="font-semibold text-primary">{{ item.completed }} / {{ item.total }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="enrollment?.certificate" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                            <p class="font-bold">Kurs abgeschlossen</p>
                            <p class="mt-1 text-xs">Zertifikat: {{ enrollment.certificate.code }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a v-if="enrollment.certificate.download_url" :href="enrollment.certificate.download_url" class="inline-flex rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    Zertifikat PDF
                                </a>
                                <a v-if="enrollment.certificate.verify_url" :href="enrollment.certificate.verify_url" class="inline-flex rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success">
                                    Öffentlich prüfen
                                </a>
                            </div>
                        </div>
                    </article>

                    <article v-if="selectedLesson" class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <p class="text-xs font-semibold uppercase text-secondary">Lernraum</p>
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
                                <a v-else-if="selectedLesson.video_url" :href="selectedLesson.video_url" target="_blank" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Video öffnen</a>
                                <div v-if="canTrackSelectedLesson && selectedLesson.video_url" class="rounded-lg border border-border bg-bg p-3">
                                    <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                        <span>Video-Fortschritt</span>
                                        <span>{{ selectedLesson.watch_percent || 0 }}% - {{ formatSeconds(selectedLesson.watch_seconds) }}</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                        <div class="h-full rounded-full bg-air-blue" :style="{ width: `${selectedLesson.watch_percent || 0}%` }"></div>
                                    </div>
                                    <p class="mt-2 text-xs text-secondary">Ab 80% Watch-Time wird die Lektion automatisch abgeschlossen.</p>
                                </div>
                                <div v-if="selectedLesson.attachments?.length" class="grid gap-2">
                                    <a v-for="attachment in selectedLesson.attachments" :key="attachment.url || attachment.name" :href="attachment.url" target="_blank" class="rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        {{ attachment.name || 'Material öffnen' }}
                                    </a>
                                </div>
                                <div v-if="selectedLesson.assignments?.length" class="grid gap-3">
                                    <article v-for="assignment in selectedLesson.assignments" :key="assignment.id" class="rounded-lg border border-border bg-bg p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <h3 class="font-semibold text-primary">{{ assignment.title }}</h3>
                                            <div class="flex flex-wrap gap-2">
                                                <span v-if="assignment.is_required" class="rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">Pflicht</span>
                                                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ assignment.points }} Punkte</span>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-secondary whitespace-pre-line">{{ assignment.instructions }}</p>
                                        <p v-if="assignment.due_at" class="mt-2 text-xs font-semibold text-secondary">Fällig bis {{ formatDateTime(assignment.due_at) }}</p>
                                        <p v-if="assignment.submission" class="mt-3 text-xs font-semibold" :class="assignment.submission.status === 'passed' ? 'text-success' : 'text-warning'">
                                            Status: {{ assignment.submission.status }}<span v-if="assignment.submission.score !== null"> - {{ assignment.submission.score }} Punkte</span>
                                        </p>
                                        <p v-if="assignment.submission?.feedback" class="mt-2 rounded-lg bg-card p-3 text-sm text-secondary">{{ assignment.submission.feedback }}</p>
                                        <form v-if="canUseLearningRoom" class="mt-3 grid gap-2" @submit.prevent="submitAssignment(assignment)">
                                            <textarea v-model="assignmentForms[String(assignment.id)].body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Deine Antwort"></textarea>
                                            <input v-model="assignmentForms[String(assignment.id)].attachment_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Optionaler Link zum Anhang">
                                            <button class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Aufgabe einreichen</button>
                                        </form>
                                    </article>
                                </div>
                                <button
                                    v-if="canTrackSelectedLesson"
                                    type="button"
                                    class="rounded-lg border px-4 py-2 text-sm font-semibold"
                                    :class="selectedLesson.completed ? 'border-success/30 bg-success/10 text-success' : 'border-border text-primary hover:bg-muted'"
                                    :disabled="selectedLesson.completed || lessonCompleteForm.processing"
                                    @click="completeLesson"
                                >
                                    {{ selectedLesson.completed ? 'Lektion abgeschlossen' : 'Lektion abschließen' }}
                                </button>
                                <form v-if="canUseLearningRoom" class="grid gap-2" @submit.prevent="submitNote">
                                    <textarea v-model="noteForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Private Notiz zu dieser Lektion"></textarea>
                                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Notiz speichern</button>
                                </form>
                                <form v-if="canUseLearningRoom" class="grid gap-2" @submit.prevent="submitComment">
                                    <textarea v-model="commentForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Frage an den Tutor oder Kurschat"></textarea>
                                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Frage senden</button>
                                </form>
                                <div v-if="canUseLearningRoom && selectedLesson.comments?.length" class="grid gap-3">
                                    <p class="text-xs font-semibold uppercase text-secondary">Fragen und Antworten</p>
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
                                    Das ist eine freigegebene Vorschau. Für Notizen und Kursfragen brauchst du Zugriff auf den Kurs.
                                </div>
                            </div>
                            <div v-else-if="selectedLesson.drip_locked" class="mt-5 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
                                Diese Lektion wird am {{ formatDateTime(selectedLesson.available_at) }} freigeschaltet.
                            </div>
                            <div v-else class="mt-5 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                                Schreibe dich ein, um Inhalte, Notizen und Kursfragen zu nutzen.
                            </div>
                        </div>
                    </article>

                    <article v-if="canUseLearningRoom && course.quizzes?.length" class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <p class="text-xs font-semibold uppercase text-secondary">Wissenscheck</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">Quiz</h2>
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
                                        Ab {{ formatDateTime(quiz.available_at) }}
                                    </span>
                                </div>
                                <div v-if="quiz.locked" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                                    Dieser Wissenscheck wird zusammen mit der Lektion freigeschaltet.
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
                                <button :disabled="quiz.locked" class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60">
                                    Quiz abgeben
                                </button>
                            </form>
                        </div>
                    </article>

                    <article v-if="enrollment" class="surface-card p-5">
                        <h2 class="text-lg font-bold text-primary">Kurs bewerten</h2>
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
                            <textarea v-model="reviewForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was hat dir geholfen?"></textarea>
                            <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                Bewertung speichern
                            </button>
                        </form>
                    </article>
                </aside>
            </section>
        </main>

        <Footer />
    </div>
</template>
