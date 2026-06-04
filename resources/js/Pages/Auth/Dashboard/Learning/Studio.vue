<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { majorToCents } from '@/utils/currency'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    courses: { type: Array, default: () => [] },
    selectedCourse: { type: Object, default: null },
})

const page = usePage()
const activePanel = ref('structure')
const editingLesson = ref(null)
const replyForms = ref({})
const uploadState = ref({ key: '', error: '' })

const courseCategories = [
    ['training', 'Training'],
    ['nutrition', 'Ernährung'],
    ['mindset', 'Mindset'],
    ['tactics', 'Taktik'],
    ['rehab', 'Reha & Prävention'],
    ['coaching', 'Coaching'],
    ['club_management', 'Vereinsführung'],
]

const levels = [
    ['beginner', 'Einsteiger'],
    ['intermediate', 'Fortgeschritten'],
    ['advanced', 'Ambitioniert'],
    ['pro', 'Profi'],
]

const lessonTypes = [
    ['lesson', 'Lektion'],
    ['video', 'Video'],
    ['exercise', 'Übung'],
    ['assignment', 'Aufgabe'],
    ['live_session', 'Live-Session'],
]

const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0)
    if (value < 60) return `${value} Min.`
    return `${Math.floor(value / 60)} Std. ${value % 60} Min.`
}

const formatPercent = (part, total) => {
    const base = Number(total || 0)
    if (!base) return '0%'

    return `${Math.round((Number(part || 0) / base) * 100)}%`
}

const uploadLearningAsset = async (purpose, file, onUploaded) => {
    if (!props.selectedCourse || !file) return

    uploadState.value = { key: purpose, error: '' }
    const payload = new FormData()
    payload.append('purpose', purpose)
    payload.append('file', file)

    try {
        const response = await window.axios.post(route('auth.learning.studio.uploads.store', props.selectedCourse.id), payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        onUploaded(response.data)
    } catch (error) {
        uploadState.value = {
            key: purpose,
            error: error?.response?.data?.message || 'Upload fehlgeschlagen.',
        }
        return
    }

    uploadState.value = { key: '', error: '' }
}

const uploadCourseCover = (event) => {
    uploadLearningAsset('cover', event.target.files?.[0], (asset) => {
        courseForm.cover_image = asset.url
    })
    event.target.value = ''
}

const uploadLessonVideo = (event) => {
    uploadLearningAsset('lesson_video', event.target.files?.[0], (asset) => {
        lessonForm.video_url = asset.url
    })
    event.target.value = ''
}

const uploadLessonAttachment = (event) => {
    uploadLearningAsset('lesson_attachment', event.target.files?.[0], (asset) => {
        lessonForm.attachments_text = [lessonForm.attachments_text, asset.url].filter(Boolean).join('\n')
    })
    event.target.value = ''
}

const statusLabel = (status) => ({
    draft: 'Entwurf',
    review: 'Prüfung',
    published: 'Live',
    archived: 'Archiviert',
}[status] || status)

const newCourseForm = useForm({
    title: '',
    subtitle: '',
    description: '',
    category: 'training',
    sport_type: '',
    level: 'beginner',
    language: 'de',
    cover_image: '',
    status: 'draft',
    is_public: false,
    is_free: true,
    price: '',
    learning_goals_text: '',
    requirements_text: '',
    target_groups_text: '',
    sales_points_text: '',
    faq_items_text: '',
    guarantee_text: '',
    certificate_logo_url: '',
    certificate_signature_name: '',
    certificate_footer_text: '',
    tags_text: '',
})

const courseForm = useForm({
    title: '',
    subtitle: '',
    description: '',
    category: 'training',
    sport_type: '',
    level: 'beginner',
    language: 'de',
    cover_image: '',
    status: 'draft',
    is_public: false,
    is_free: true,
    price: '',
    learning_goals_text: '',
    requirements_text: '',
    target_groups_text: '',
    sales_points_text: '',
    faq_items_text: '',
    guarantee_text: '',
    certificate_logo_url: '',
    certificate_signature_name: '',
    certificate_footer_text: '',
    tags_text: '',
})

const sectionForm = useForm({
    title: '',
    description: '',
})

const lessonForm = useForm({
    learning_course_section_id: '',
    title: '',
    type: 'lesson',
    summary: '',
    content: '',
    video_url: '',
    attachments_text: '',
    duration_minutes: '',
    position: '',
    is_preview: false,
    unlock_after_days: 0,
})

const quizForm = useForm({
    learning_lesson_id: '',
    title: '',
    description: '',
    pass_percent: 70,
    question: '',
    options_text: '',
    correct_options_text: '',
    explanation: '',
})

const couponForm = useForm({
    code: '',
    discount_type: 'percent',
    discount_value: 10,
    max_redemptions: '',
    expires_at: '',
    is_active: true,
})

const assignmentForm = useForm({
    learning_lesson_id: '',
    title: '',
    instructions: '',
    points: 100,
    due_after_days: '',
    is_required: true,
})

const enrollmentForm = useForm({ email: '' })
const gradingForms = ref({})

const fillCourseForm = () => {
    if (!props.selectedCourse) return

    courseForm.defaults({
        title: props.selectedCourse.title || '',
        subtitle: props.selectedCourse.subtitle || '',
        description: props.selectedCourse.description || '',
        category: props.selectedCourse.category || 'training',
        sport_type: props.selectedCourse.sport_type || '',
        level: props.selectedCourse.level || 'beginner',
        language: props.selectedCourse.language || 'de',
        cover_image: props.selectedCourse.cover_image || '',
        status: props.selectedCourse.status || 'draft',
        is_public: Boolean(props.selectedCourse.is_public),
        is_free: Boolean(props.selectedCourse.is_free),
        price: props.selectedCourse.price_cents ? String(Number(props.selectedCourse.price_cents) / 100).replace('.', ',') : '',
        learning_goals_text: props.selectedCourse.learning_goals_text || '',
        requirements_text: props.selectedCourse.requirements_text || '',
        target_groups_text: props.selectedCourse.target_groups_text || '',
        sales_points_text: props.selectedCourse.sales_points_text || '',
        faq_items_text: props.selectedCourse.faq_items_text || '',
        guarantee_text: props.selectedCourse.guarantee_text || '',
        certificate_logo_url: props.selectedCourse.certificate_logo_url || '',
        certificate_signature_name: props.selectedCourse.certificate_signature_name || '',
        certificate_footer_text: props.selectedCourse.certificate_footer_text || '',
        tags_text: props.selectedCourse.tags_text || '',
    })
    courseForm.reset()
}

watch(() => props.selectedCourse?.id, () => {
    fillCourseForm()
    editingLesson.value = null
    const forms = {}
    ;(props.selectedCourse?.assignments || []).forEach((assignment) => {
        ;(assignment.submissions || []).forEach((submission) => {
            forms[String(submission.id)] = {
                status: submission.status || 'passed',
                score: submission.score || '',
                feedback: submission.feedback || '',
            }
        })
    })
    gradingForms.value = forms
}, { immediate: true })

const allLessons = computed(() => (props.selectedCourse?.sections || []).flatMap((section) => section.lessons || []))
const courseStats = computed(() => [
    ['Kapitel', props.selectedCourse?.sections?.length || 0],
    ['Lektionen', allLessons.value.length],
    ['Quiz', props.selectedCourse?.quizzes?.length || 0],
    ['Teilnehmer', props.selectedCourse?.enrollments?.length || 0],
])

const payloadWithPrice = (form) => ({
    title: form.title,
    subtitle: form.subtitle,
    description: form.description,
    category: form.category,
    sport_type: form.sport_type,
    level: form.level,
    language: form.language,
    cover_image: form.cover_image,
    status: form.status,
    is_public: form.is_public,
    is_free: form.is_free,
    price_cents: form.is_free ? 0 : majorToCents(form.price),
    learning_goals_text: form.learning_goals_text,
    requirements_text: form.requirements_text,
    target_groups_text: form.target_groups_text,
    sales_points_text: form.sales_points_text,
    faq_items_text: form.faq_items_text,
    guarantee_text: form.guarantee_text,
    certificate_logo_url: form.certificate_logo_url,
    certificate_signature_name: form.certificate_signature_name,
    certificate_footer_text: form.certificate_footer_text,
    tags_text: form.tags_text,
})

const createCourse = () => {
    newCourseForm
        .transform(() => payloadWithPrice(newCourseForm))
        .post(route('auth.learning.studio.courses.store'), {
            preserveScroll: true,
            onSuccess: () => newCourseForm.reset(),
        })
}

const updateCourse = () => {
    if (!props.selectedCourse) return
    courseForm
        .transform(() => payloadWithPrice(courseForm))
        .put(route('auth.learning.studio.courses.update', props.selectedCourse.id), { preserveScroll: true })
}

const createSection = () => {
    if (!props.selectedCourse) return
    sectionForm.post(route('auth.learning.studio.sections.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => sectionForm.reset(),
    })
}

const resetLessonForm = () => {
    editingLesson.value = null
    lessonForm.defaults({
        learning_course_section_id: props.selectedCourse?.sections?.[0]?.id || '',
        title: '',
        type: 'lesson',
        summary: '',
        content: '',
        video_url: '',
        attachments_text: '',
        duration_minutes: '',
        position: '',
        is_preview: false,
        unlock_after_days: 0,
    })
    lessonForm.reset()
}

watch(() => props.selectedCourse?.sections?.[0]?.id, resetLessonForm, { immediate: true })

const editLesson = (lesson) => {
    editingLesson.value = lesson
    lessonForm.defaults({
        learning_course_section_id: lesson.learning_course_section_id || '',
        title: lesson.title || '',
        type: lesson.type || 'lesson',
        summary: lesson.summary || '',
        content: lesson.content || '',
        video_url: lesson.video_url || '',
        attachments_text: (lesson.attachments || []).map((item) => item.url || item).join('\n'),
        duration_minutes: lesson.duration_minutes || '',
        position: lesson.position || '',
        is_preview: Boolean(lesson.is_preview),
        unlock_after_days: lesson.unlock_after_days || 0,
    })
    lessonForm.reset()
}

const submitLesson = () => {
    if (!props.selectedCourse) return

    const url = editingLesson.value
        ? route('auth.learning.studio.lessons.update', [props.selectedCourse.id, editingLesson.value.id])
        : route('auth.learning.studio.lessons.store', props.selectedCourse.id)

    const options = {
        preserveScroll: true,
        onSuccess: resetLessonForm,
    }

    editingLesson.value ? lessonForm.put(url, options) : lessonForm.post(url, options)
}

const moveLesson = (section, lesson, direction) => {
    if (!props.selectedCourse) return

    const lessons = [...(section.lessons || [])].sort((a, b) => (a.position || 0) - (b.position || 0))
    const index = lessons.findIndex((item) => item.id === lesson.id)
    const targetIndex = index + direction

    if (index < 0 || targetIndex < 0 || targetIndex >= lessons.length) return

    const reordered = [...lessons]
    const moved = reordered.splice(index, 1)[0]
    reordered.splice(targetIndex, 0, moved)

    router.put(route('auth.learning.studio.lessons.reorder', props.selectedCourse.id), {
        lessons: reordered.map((item, itemIndex) => ({ id: item.id, position: itemIndex + 1 })),
    }, { preserveScroll: true })
}

const deleteLesson = async (lesson) => {
    if (!props.selectedCourse) return

    const confirmed = await confirmDialog({
        title: 'Lektion löschen',
        message: `Soll die Lektion "${lesson.title}" wirklich gelöscht werden?`,
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('auth.learning.studio.lessons.destroy', [props.selectedCourse.id, lesson.id]), { preserveScroll: true })
}

const createQuiz = () => {
    if (!props.selectedCourse) return
    quizForm.post(route('auth.learning.studio.quizzes.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => quizForm.reset('title', 'description', 'question', 'options_text', 'correct_options_text', 'explanation'),
    })
}

const deleteQuiz = async (quiz) => {
    if (!props.selectedCourse) return

    const confirmed = await confirmDialog({
        title: 'Quiz löschen',
        message: `Soll das Quiz "${quiz.title}" wirklich gelöscht werden?`,
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('auth.learning.studio.quizzes.destroy', [props.selectedCourse.id, quiz.id]), { preserveScroll: true })
}

const createCoupon = () => {
    if (!props.selectedCourse) return
    couponForm.post(route('auth.learning.studio.coupons.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => couponForm.reset('code', 'discount_value', 'max_redemptions', 'expires_at'),
    })
}

const createAssignment = () => {
    if (!props.selectedCourse) return
    assignmentForm.post(route('auth.learning.studio.assignments.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => assignmentForm.reset('title', 'instructions', 'due_after_days'),
    })
}

const grantEnrollment = () => {
    if (!props.selectedCourse) return
    enrollmentForm.post(route('auth.learning.studio.enrollments.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => enrollmentForm.reset(),
    })
}

const revokeEnrollment = async (enrollment) => {
    if (!props.selectedCourse) return

    const confirmed = await confirmDialog({
        title: 'Zugang deaktivieren',
        message: 'Soll der Zugang wirklich deaktiviert werden?',
        confirmLabel: 'Deaktivieren',
        danger: true,
    })

    if (!confirmed) return

    router.put(route('auth.learning.studio.enrollments.revoke', [props.selectedCourse.id, enrollment.id]), {}, { preserveScroll: true })
}

const gradeSubmission = (submission) => {
    if (!props.selectedCourse) return
    const form = gradingForms.value[String(submission.id)] || { status: 'passed', score: submission.score || '', feedback: submission.feedback || '' }
    router.put(route('auth.learning.studio.assignment-submissions.update', [props.selectedCourse.id, submission.id]), form, { preserveScroll: true })
}

const updateQuestionStatus = (question, status) => {
    if (!props.selectedCourse) return

    router.put(route('auth.learning.studio.comments.update', [props.selectedCourse.id, question.id]), {
        status,
    }, { preserveScroll: true })
}

const submitQuestionReply = (question) => {
    if (!props.selectedCourse) return

    router.post(route('auth.learning.studio.comments.replies.store', [props.selectedCourse.id, question.id]), {
        body: replyForms.value[String(question.id)] || '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            replyForms.value = {
                ...replyForms.value,
                [String(question.id)]: '',
            }
        },
    })
}
</script>

<template>
    <Head title="Airmius Sportschule" />

    <div class="space-y-6">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius Sportschule</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kurs-Studio für Trainer und Tutoren</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Plane echte Online-Kurse mit Kapiteln, Lektionen, Aufgaben, Anhängen, Quiz, Notizen und Kurskommunikation.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div v-for="[label, value] in courseStats" :key="label" class="rounded-lg border border-border bg-bg p-3">
                        <p class="text-xs uppercase text-secondary">{{ label }}</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ value }}</p>
                    </div>
                </div>
            </div>
            <div v-if="page.props.flash?.success" class="border-t border-success/30 bg-success/10 px-5 py-3 text-sm font-semibold text-success">
                {{ page.props.flash.success }}
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="space-y-4">
                <article class="surface-card p-4">
                    <h2 class="text-base font-semibold text-primary">Neuen Kurs planen</h2>
                    <form class="mt-4 grid gap-3" @submit.prevent="createCourse">
                        <input v-model="newCourseForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurstitel">
                        <p v-if="newCourseForm.errors.title" class="text-sm text-error">{{ newCourseForm.errors.title }}</p>
                        <input v-model="newCourseForm.subtitle" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurzversprechen">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <select v-model="newCourseForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in courseCategories" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="newCourseForm.level" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in levels" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <input v-model="newCourseForm.sport_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Sportart, z. B. Fußball">
                        <textarea v-model="newCourseForm.description" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Worum geht es in diesem Kurs?"></textarea>
                        <textarea v-model="newCourseForm.learning_goals_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lernziele, je Zeile eins"></textarea>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="newCourseForm.processing">
                            Kurs anlegen
                        </button>
                    </form>
                </article>

                <article class="surface-card overflow-hidden">
                    <div class="border-b border-border p-4">
                        <h2 class="text-base font-semibold text-primary">Meine Kurse</h2>
                    </div>
                    <div class="divide-y divide-border">
                        <Link
                            v-for="course in courses"
                            :key="course.id"
                            :href="route('auth.learning.studio.index', { course: course.id })"
                            class="block p-4 transition hover:bg-muted"
                            :class="selectedCourse?.id === course.id ? 'bg-muted' : ''"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-primary">{{ course.title }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ statusLabel(course.status) }} - {{ course.lessons_count }} Lektionen</p>
                                </div>
                                <span class="rounded-full bg-bg px-2 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                            </div>
                        </Link>
                        <p v-if="!courses.length" class="p-4 text-sm text-secondary">Noch kein Kurs angelegt.</p>
                    </div>
                </article>
            </aside>

            <section v-if="selectedCourse" class="space-y-4">
                <article class="surface-card overflow-hidden">
                    <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Aktueller Kurs</p>
                            <h2 class="mt-1 break-words text-2xl font-bold text-primary">{{ selectedCourse.title }}</h2>
                            <p class="mt-2 text-sm text-secondary">{{ selectedCourse.subtitle || selectedCourse.description || 'Beschreibe den Kurs, damit Sportler sofort wissen, was sie lernen.' }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Status</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ statusLabel(selectedCourse.status) }}</p>
                            <p class="mt-2 text-xs text-secondary">{{ selectedCourse.is_free ? 'Kostenlos' : formatMoney(selectedCourse.price_cents, selectedCourse.currency) }} - {{ formatMinutes(selectedCourse.estimated_minutes) }}</p>
                            <a v-if="selectedCourse.preview_url" :href="selectedCourse.preview_url" target="_blank" class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted">
                                Als Teilnehmer ansehen
                            </a>
                        </div>
                    </div>
                    <div class="grid gap-3 border-t border-border p-5 md:grid-cols-4 xl:grid-cols-10">
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Durchschnitt</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_progress || 0 }}%</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Abschluesse</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.completed_enrollments || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Offene Fragen</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.open_questions || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Bewertung</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_rating || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Verkäufe</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Umsatz netto</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(selectedCourse.analytics?.net_revenue_cents || 0, selectedCourse.currency) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Offene Zahlungen</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.pending_sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Refund/Storno</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.cancelled_sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Security 24h</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.security_events_24h || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">Video-Block 24h</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.blocked_video_attempts_24h || 0 }}</p>
                        </div>
                    </div>
                    <div class="border-t border-border p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">Publish-Check</p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ selectedCourse.publish_checklist?.done_count || 0 }} von {{ selectedCourse.publish_checklist?.total_count || 0 }} Punkten erledigt
                                    ({{ selectedCourse.publish_checklist?.score ?? formatPercent(selectedCourse.publish_checklist?.done_count, selectedCourse.publish_checklist?.total_count) }}%)
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="selectedCourse.publish_checklist?.ready ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                {{ selectedCourse.publish_checklist?.ready ? 'Bereit' : 'Noch offen' }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-2 md:grid-cols-3">
                            <div v-for="item in selectedCourse.publish_checklist?.items || []" :key="item.key" class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm">
                                <i :class="item.done ? 'las la-check-circle text-success' : 'las la-circle text-secondary'"></i>
                                <span :class="item.done ? 'text-primary' : 'text-secondary'">{{ item.label }}</span>
                            </div>
                        </div>
                        <div v-if="selectedCourse.security_events?.length" class="mt-5 rounded-lg border border-border bg-bg p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase text-secondary">Monitoring</p>
                                <span v-if="selectedCourse.analytics?.critical_security_events_24h" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">
                                    {{ selectedCourse.analytics.critical_security_events_24h }} kritisch
                                </span>
                            </div>
                            <div class="mt-3 grid gap-2">
                                <div v-for="event in selectedCourse.security_events" :key="event.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs">
                                    <span class="font-semibold text-primary">{{ event.type }}</span>
                                    <span class="text-secondary">{{ event.lesson_title || 'Kurs' }}</span>
                                    <span class="text-secondary">{{ event.user?.email || 'Unbekannt' }}</span>
                                    <span :class="event.severity === 'critical' ? 'text-error' : 'text-warning'" class="font-semibold">{{ event.severity }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 overflow-x-auto border-t border-border p-2">
                        <button
                            v-for="panel in [
                                ['structure', 'Struktur', 'las la-list'],
                                ['details', 'Kursdaten', 'las la-sliders-h'],
                                ['sales', 'Landingpage', 'las la-bullhorn'],
                                ['quiz', 'Quiz', 'las la-question-circle'],
                                ['assignments', 'Aufgaben', 'las la-clipboard-check'],
                                ['students', 'Teilnehmer', 'las la-users'],
                                ['questions', 'Fragen', 'las la-comments'],
                            ]"
                            :key="panel[0]"
                            type="button"
                            class="flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                            :class="activePanel === panel[0] ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                            @click="activePanel = panel[0]"
                        >
                            <i :class="panel[2]"></i>
                            <span>{{ panel[1] }}</span>
                        </button>
                    </div>
                </article>

                <div v-show="activePanel === 'structure'" class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <article class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <h2 class="text-lg font-semibold text-primary">Kursstruktur</h2>
                            <p class="mt-1 text-sm text-secondary">Kapitel, Themen und Lektionen in didaktischer Reihenfolge.</p>
                        </div>
                        <div class="divide-y divide-border">
                            <div v-for="section in selectedCourse.sections" :key="section.id" class="p-5">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-secondary">Kapitel {{ section.position }}</p>
                                        <h3 class="mt-1 text-lg font-semibold text-primary">{{ section.title }}</h3>
                                        <p v-if="section.description" class="mt-1 text-sm text-secondary">{{ section.description }}</p>
                                    </div>
                                    <span class="rounded-full bg-bg px-3 py-1 text-xs font-semibold text-secondary">{{ section.lessons?.length || 0 }} Lektionen</span>
                                </div>
                                <div class="mt-4 grid gap-3">
                                    <div
                                        v-for="lesson in section.lessons"
                                        :key="lesson.id"
                                        class="grid gap-3 rounded-lg border border-border bg-bg p-3 transition hover:border-air-blue md:grid-cols-[minmax(0,1fr)_8rem_auto] md:items-center"
                                    >
                                        <button type="button" class="min-w-0 text-left" @click="editLesson(lesson)">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-card px-2 py-0.5 text-xs font-semibold text-secondary">{{ lesson.type }}</span>
                                                <span v-if="lesson.is_preview" class="rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">Preview</span>
                                                <span v-if="lesson.unlock_after_days" class="rounded-full bg-warning/10 px-2 py-0.5 text-xs font-semibold text-warning">Tag {{ lesson.unlock_after_days }}</span>
                                            </div>
                                            <p class="mt-2 font-semibold text-primary">{{ lesson.title }}</p>
                                            <p v-if="lesson.summary" class="mt-1 line-clamp-2 text-sm text-secondary">{{ lesson.summary }}</p>
                                        </button>
                                        <p class="text-sm font-semibold text-secondary">{{ formatMinutes(lesson.duration_minutes) }}</p>
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="moveLesson(section, lesson, -1)">Hoch</button>
                                            <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="moveLesson(section, lesson, 1)">Runter</button>
                                            <button type="button" class="rounded border border-error/40 px-2 py-1 text-xs text-error" @click="deleteLesson(lesson)">Löschen</button>
                                        </div>
                                    </div>
                                    <p v-if="!section.lessons?.length" class="rounded-lg border border-dashed border-border bg-bg p-4 text-sm text-secondary">Noch keine Lektionen in diesem Kapitel.</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <aside class="space-y-4">
                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">Kapitel hinzufügen</h2>
                            <form class="mt-4 grid gap-3" @submit.prevent="createSection">
                                <input v-model="sectionForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kapitelname">
                                <textarea v-model="sectionForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurzbeschreibung"></textarea>
                                <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Kapitel erstellen</button>
                            </form>
                        </article>

                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">{{ editingLesson ? 'Lektion bearbeiten' : 'Lektion hinzufügen' }}</h2>
                            <form class="mt-4 grid gap-3" @submit.prevent="submitLesson">
                                <select v-model="lessonForm.learning_course_section_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="section in selectedCourse.sections" :key="section.id" :value="section.id">{{ section.title }}</option>
                                </select>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input v-model="lessonForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lektionstitel">
                                    <select v-model="lessonForm.type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                        <option v-for="[value, label] in lessonTypes" :key="value" :value="value">{{ label }}</option>
                                    </select>
                                </div>
                                <textarea v-model="lessonForm.summary" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Was passiert in dieser Lektion?"></textarea>
                                <textarea v-model="lessonForm.content" rows="7" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Skript, Aufgaben, Hinweise, Coaching-Text"></textarea>
                                <div class="grid gap-2">
                                    <input v-model="lessonForm.video_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Video-URL optional">
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        <i class="las la-video"></i>
                                        Video hochladen
                                        <input type="file" accept="video/*" class="sr-only" @change="uploadLessonVideo">
                                    </label>
                                    <p v-if="uploadState.key === 'lesson_video'" class="text-xs text-secondary">Video wird hochgeladen...</p>
                                    <p v-if="uploadState.error && uploadState.key === 'lesson_video'" class="text-xs text-error">{{ uploadState.error }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <textarea v-model="lessonForm.attachments_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anhang-URLs, je Zeile eine"></textarea>
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        <i class="las la-paperclip"></i>
                                        Material hochladen
                                        <input type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,image/*,video/*" class="sr-only" @change="uploadLessonAttachment">
                                    </label>
                                    <p v-if="uploadState.key === 'lesson_attachment'" class="text-xs text-secondary">Material wird hochgeladen...</p>
                                    <p v-if="uploadState.error && uploadState.key === 'lesson_attachment'" class="text-xs text-error">{{ uploadState.error }}</p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input v-model="lessonForm.duration_minutes" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Dauer in Minuten">
                                    <input v-model="lessonForm.position" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Position">
                                </div>
                                <input v-model="lessonForm.unlock_after_days" type="number" min="0" max="3650" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Freischalten nach Tagen ab Einschreibung">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                                        <input v-model="lessonForm.is_preview" type="checkbox" class="rounded border-border bg-inputBg">
                                        Als Preview freigeben
                                    </label>
                                </div>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                    <button v-if="editingLesson" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="resetLessonForm">Neu anlegen</button>
                                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="lessonForm.processing">
                                        {{ editingLesson ? 'Lektion speichern' : 'Lektion erstellen' }}
                                    </button>
                                </div>
                            </form>
                        </article>
                    </aside>
                </div>

                <article v-show="activePanel === 'details'" class="surface-card p-5">
                    <h2 class="text-lg font-semibold text-primary">Kursdaten und Veröffentlichung</h2>
                    <form class="mt-5 grid gap-4" @submit.prevent="updateCourse">
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
                                <input type="file" accept="image/*" class="sr-only" @change="uploadCourseCover">
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
                            <input v-model="courseForm.certificate_footer_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zertifikat Fußzeile">
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
                                Öffentlich sichtbar
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

                <article v-show="activePanel === 'sales'" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">Landingpage, Gutscheine und Review</h2>
                        <p class="mt-1 text-sm text-secondary">Alles, was Besucher vor dem Kauf brauchen: Nutzen, FAQ, Rabatte und Qualitätsstatus.</p>
                    </div>
                    <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-4">
                            <div class="rounded-lg border border-border bg-bg p-4">
                                <p class="text-xs font-semibold uppercase text-secondary">Qualitätsreview</p>
                                <p class="mt-2 text-lg font-bold text-primary">{{ selectedCourse.quality_status || 'pending' }}</p>
                                <p v-if="selectedCourse.quality_note" class="mt-1 text-sm text-secondary">{{ selectedCourse.quality_note }}</p>
                            </div>
                            <div class="grid gap-3">
                                <div v-for="coupon in selectedCourse.coupons" :key="coupon.id" class="rounded-lg border border-border bg-bg p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <p class="font-bold text-primary">{{ coupon.code }}</p>
                                        <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ coupon.redeemed_count || 0 }} genutzt</span>
                                    </div>
                                    <p class="mt-1 text-sm text-secondary">{{ coupon.discount_type === 'fixed' ? formatMoney(coupon.discount_value) : `${coupon.discount_value}%` }} Rabatt</p>
                                </div>
                                <p v-if="!selectedCourse.coupons?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch keine Gutscheine angelegt.</p>
                            </div>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createCoupon">
                            <h3 class="font-semibold text-primary">Gutschein anlegen</h3>
                            <input v-model="couponForm.code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Code, z. B. TEAM20">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <select v-model="couponForm.discount_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="percent">Prozent</option>
                                    <option value="fixed">Fixbetrag in Cent</option>
                                </select>
                                <input v-model="couponForm.discount_value" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Wert">
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="couponForm.max_redemptions" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Max. Nutzungen">
                                <input v-model="couponForm.expires_at" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            </div>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                <input v-model="couponForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                Aktiv
                            </label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Gutschein speichern</button>
                        </form>
                    </div>
                </article>

                <article v-show="activePanel === 'quiz'" class="surface-card p-5">
                    <h2 class="text-lg font-semibold text-primary">Quiz und Wissenschecks</h2>
                    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-3">
                            <div v-for="quiz in selectedCourse.quizzes" :key="quiz.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-primary">{{ quiz.title }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ quiz.description || 'Kein Beschreibungstext' }}</p>
                                    </div>
                                    <span class="rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">{{ quiz.pass_percent }}% Bestehen</span>
                                </div>
                                <p class="mt-3 text-xs text-secondary">{{ quiz.questions?.length || 0 }} Fragen</p>
                                <button type="button" class="mt-3 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="deleteQuiz(quiz)">
                                    Quiz löschen
                                </button>
                            </div>
                            <p v-if="!selectedCourse.quizzes?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch kein Quiz angelegt.</p>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createQuiz">
                            <h3 class="font-semibold text-primary">Quiz erstellen</h3>
                            <select v-model="quizForm.learning_lesson_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Allgemeines Kursquiz</option>
                                <option v-for="lesson in allLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                            </select>
                            <input v-model="quizForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Quiztitel">
                            <textarea v-model="quizForm.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                            <input v-model="quizForm.pass_percent" type="number" min="1" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestehensgrenze in %">
                            <textarea v-model="quizForm.question" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Erste Frage optional"></textarea>
                            <textarea v-model="quizForm.options_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Antwortoptionen, je Zeile eine"></textarea>
                            <textarea v-model="quizForm.correct_options_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Korrekte Antwort(en), je Zeile eine"></textarea>
                            <textarea v-model="quizForm.explanation" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Erklaerung nach Antwort"></textarea>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Quiz speichern</button>
                        </form>
                    </div>
                </article>

                <article v-show="activePanel === 'assignments'" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">Aufgaben und manuelle Bewertung</h2>
                        <p class="mt-1 text-sm text-secondary">Teilnehmer reichen Text oder Links ein, Tutoren geben Score und Feedback zurück.</p>
                    </div>
                    <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-4">
                            <div v-for="assignment in selectedCourse.assignments" :key="assignment.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-primary">{{ assignment.title }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ assignment.instructions || 'Keine Beschreibung' }}</p>
                                    </div>
                                    <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ assignment.points }} Punkte</span>
                                </div>
                                <div v-if="assignment.submissions?.length" class="mt-4 grid gap-3">
                                    <div v-for="submission in assignment.submissions" :key="submission.id" class="rounded-lg border border-border bg-card p-3">
                                        <p class="text-sm font-semibold text-primary">{{ submission.user?.name || 'Teilnehmer' }}</p>
                                        <p class="mt-1 text-sm text-secondary whitespace-pre-line">{{ submission.body }}</p>
                                        <a v-if="submission.attachment_url" :href="submission.attachment_url" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-air-blue">Anhang öffnen</a>
                                        <div class="mt-3 grid gap-2 md:grid-cols-[8rem_7rem_minmax(0,1fr)_auto]">
                                            <select v-model="gradingForms[String(submission.id)].status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                                <option value="passed">Bestanden</option>
                                                <option value="needs_revision">Revision</option>
                                                <option value="rejected">Abgelehnt</option>
                                            </select>
                                            <input v-model="gradingForms[String(submission.id)].score" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Score">
                                            <input v-model="gradingForms[String(submission.id)].feedback" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Feedback">
                                            <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="gradeSubmission(submission)">Bewerten</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!selectedCourse.assignments?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch keine Aufgaben angelegt.</p>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createAssignment">
                            <h3 class="font-semibold text-primary">Aufgabe erstellen</h3>
                            <select v-model="assignmentForm.learning_lesson_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Allgemeine Kursaufgabe</option>
                                <option v-for="lesson in allLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                            </select>
                            <input v-model="assignmentForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Aufgabentitel">
                            <textarea v-model="assignmentForm.instructions" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Aufgabenstellung"></textarea>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="assignmentForm.points" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Punkte">
                                <input v-model="assignmentForm.due_after_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Fällig nach Tagen">
                            </div>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                <input v-model="assignmentForm.is_required" type="checkbox" class="rounded border-border bg-inputBg">
                                Pflichtaufgabe
                            </label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Aufgabe speichern</button>
                        </form>
                    </div>
                </article>

                <article v-show="activePanel === 'students'" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">Teilnehmer und Betreuung</h2>
                        <p class="mt-1 text-sm text-secondary">Einschreibungen, Fortschritt, manuelle Freischaltung und CSV-Reporting.</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="grantEnrollment">
                                <input v-model="enrollmentForm.email" type="email" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="E-Mail für manuellen Zugang">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Freischalten</button>
                            </form>
                            <a :href="route('auth.learning.studio.courses.report', selectedCourse.id)" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">CSV Export</a>
                        </div>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="enrollment in selectedCourse.enrollments" :key="enrollment.id" class="grid gap-3 p-5 md:grid-cols-[minmax(0,1fr)_8rem_8rem_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-primary">{{ enrollment.user?.name || 'Teilnehmer' }}</p>
                                <p class="text-sm text-secondary">{{ enrollment.user?.email }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ enrollment.completed_lessons_count || 0 }} Lektionen erledigt -
                                    {{ enrollment.passed_quizzes_count || 0 }} Quiz bestanden -
                                    {{ enrollment.assignments_passed_count || 0 }} Aufgaben bestanden
                                </p>
                                <p v-if="enrollment.completion_requirements" class="mt-1 text-xs text-secondary">
                                    Abschluss: {{ enrollment.completion_requirements.lessons.completed }}/{{ enrollment.completion_requirements.lessons.total }} Lektionen,
                                    {{ enrollment.completion_requirements.quizzes.completed }}/{{ enrollment.completion_requirements.quizzes.total }} Quiz,
                                    {{ enrollment.completion_requirements.assignments.completed }}/{{ enrollment.completion_requirements.assignments.total }} Pflicht-Aufgaben
                                </p>
                                <p v-if="enrollment.certificate" class="mt-1 text-xs font-semibold text-success">Zertifikat {{ enrollment.certificate.code }}</p>
                            </div>
                            <p class="text-sm font-semibold text-secondary">{{ enrollment.status }}</p>
                            <p class="text-sm font-semibold text-primary">{{ enrollment.progress_percent }}%</p>
                            <button class="rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="revokeEnrollment(enrollment)">Deaktivieren</button>
                        </div>
                        <p v-if="!selectedCourse.enrollments?.length" class="p-5 text-sm text-secondary">Noch keine Teilnehmer eingeschrieben.</p>
                    </div>
                </article>

                <article v-show="activePanel === 'questions'" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">Fragen-Inbox</h2>
                        <p class="mt-1 text-sm text-secondary">Offene Fragen aus den Lektionen mit Status für Betreuung und Nacharbeit.</p>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="question in selectedCourse.questions" :key="question.id" class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_12rem]">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-bg px-2 py-1 text-xs font-semibold text-secondary">{{ question.lesson_title }}</span>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="question.status === 'resolved' ? 'bg-success/10 text-success' : question.status === 'answered' ? 'bg-air-blue/10 text-air-blue' : 'bg-warning/10 text-warning'">
                                        {{ question.status }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-primary">{{ question.user?.name || 'Teilnehmer' }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-secondary">{{ question.body }}</p>
                                <div v-if="question.replies?.length" class="mt-3 grid gap-2 border-l border-border pl-3">
                                    <div v-for="reply in question.replies" :key="reply.id" class="rounded-lg bg-bg p-3">
                                        <p class="text-xs font-semibold text-air-blue">{{ reply.user?.name || 'Tutor' }}</p>
                                        <p class="mt-1 text-sm leading-relaxed text-secondary">{{ reply.body }}</p>
                                    </div>
                                </div>
                                <form class="mt-3 grid gap-2" @submit.prevent="submitQuestionReply(question)">
                                    <textarea v-model="replyForms[String(question.id)]" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Antwort für den Teilnehmer schreiben"></textarea>
                                    <button class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary">
                                        Antwort senden
                                    </button>
                                </form>
                            </div>
                            <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                                <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="updateQuestionStatus(question, 'answered')">
                                    Beantwortet
                                </button>
                                <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateQuestionStatus(question, 'resolved')">
                                    Erledigt
                                </button>
                            </div>
                        </div>
                        <p v-if="!selectedCourse.questions?.length" class="p-5 text-sm text-secondary">Noch keine Kursfragen vorhanden.</p>
                    </div>
                </article>
            </section>

            <section v-else class="surface-card flex min-h-[28rem] items-center justify-center p-8 text-center">
                <div class="max-w-md">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                        <i class="las la-graduation-cap text-4xl text-air-blue"></i>
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-primary">Starte deine Online-Sportschule</h2>
                    <p class="mt-2 text-sm text-secondary">Lege links deinen ersten Kurs an. Danach kannst du Kapitel, Lektionen und Quiz direkt im Studio bauen.</p>
                </div>
            </section>
        </section>
    </div>
</template>

