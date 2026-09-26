<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { majorToCents } from '@/utils/currency'
import { confirmDialog } from '@/services/dialogService'
import learningContentLocalization from '@/i18n/learningContentLocalization.json'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const localizationCopy = computed(() => learningContentLocalization[locale.value] || learningContentLocalization.de)
const lx = (key, values = {}) => Object.entries(values).reduce(
    (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
    localizationCopy.value[key] || learningContentLocalization.de[key] || key,
)
const learningStudioTranslationAliases = {
    learning_studio_ui: 'guest.welcome.benefits.cards.athletes.learning_studio_ui',
    learning_studio_form: 'guest.welcome.benefits.cards.athletes.learning_studio_form',
}
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    if (translated !== key) return translated

    const [scope, ...segments] = key.split('.')
    const alias = learningStudioTranslationAliases[scope]
    if (!alias) return fallback

    const aliasedKey = `${alias}.${segments.join('.')}`
    const aliasedTranslation = t(aliasedKey, values)
    return aliasedTranslation === aliasedKey ? fallback : aliasedTranslation
}

const props = defineProps({
    courses: { type: Array, default: () => [] },
    selectedCourse: { type: Object, default: null },
    supportedLocales: { type: Array, default: () => ['de', 'en', 'fr', 'ar'] },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const activePanel = ref('structure')
const editingLesson = ref(null)
const replyForms = ref({})
const uploadState = ref({ key: '', error: '' })
const newCoursePanel = ref(null)
const newCourseTitleInput = ref(null)
const languageName = (value) => lx(value)
const courseVariantLocales = (course) => new Set(
    (course?.translations || course?.translation_variants || []).map((variant) => variant.locale || variant.language),
)

const courseCategories = computed(() => [
    ['training', tx('learning_studio.categories.training', 'Training')],
    ['nutrition', tx('learning_studio.categories.nutrition', 'Ernährung')],
    ['mindset', tx('learning_studio.categories.mindset', 'Mindset')],
    ['tactics', tx('learning_studio.categories.tactics', 'Taktik')],
    ['rehab', tx('learning_studio.categories.rehab', 'Reha & Prävention')],
    ['coaching', tx('learning_studio.categories.coaching', 'Coaching')],
    ['club_management', tx('learning_studio.categories.club_management', 'Vereinsführung')],
])

const levels = computed(() => [
    ['beginner', tx('learning_studio.levels.beginner', 'Einsteiger')],
    ['intermediate', tx('learning_studio.levels.intermediate', 'Fortgeschritten')],
    ['advanced', tx('learning_studio.levels.advanced', 'Ambitioniert')],
    ['pro', tx('learning_studio.levels.pro', 'Profi')],
])

const offerTypes = computed(() => [
    ['course', tx('learning_studio.offer_types.course', 'Kurs')],
    ['block', tx('learning_studio.offer_types.block', 'Block')],
    ['single_session', tx('learning_studio.offer_types.single_session', 'Einzeltermin')],
    ['multi_pass', tx('learning_studio.offer_types.multi_pass', 'Mehrfachkarte')],
    ['camp', tx('learning_studio.offer_types.camp', 'Feriencamp')],
    ['training_camp', tx('learning_studio.offer_types.training_camp', 'Trainingslager')],
])
const offerTypeLabel = (value) => offerTypes.value.find(([key]) => key === value)?.[1] || value || tx('learning_studio.offer_types.course', 'Kurs')

const lessonTypes = computed(() => [
    ['lesson', tx('learning_studio.lesson_types.lesson', 'Lektion')],
    ['video', tx('learning_studio.lesson_types.video', 'Video')],
    ['exercise', tx('learning_studio.lesson_types.exercise', 'Übung')],
    ['assignment', tx('learning_studio.lesson_types.assignment', 'Aufgabe')],
    ['live_session', tx('learning_studio.lesson_types.live_session', 'Live-Session')],
])

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency,
}).format(Number(cents || 0) / 100)

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0)
    if (value < 60) return `${value} ${tx('learning_studio.units.minutes', 'Min.')}`
    return `${Math.floor(value / 60)} ${tx('learning_studio.units.hours', 'Std.')} ${value % 60} ${tx('learning_studio.units.minutes', 'Min.')}`
}

const datetimeLocalValue = (value) => value ? String(value).slice(0, 16) : ''

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
            error: error?.response?.data?.message || tx('learning_studio.upload_failed', 'Upload fehlgeschlagen.'),
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
    draft: tx('learning_studio_form.draft', 'Entwurf'),
    review: tx('learning_studio_form.review', 'Zur Prüfung'),
    published: tx('learning_studio_form.published', 'Veröffentlicht'),
    archived: tx('learning_studio_form.archived', 'Archiviert'),
}[status] || status)

const qualityStatusLabel = (status) => ({
    pending: tx('learning_studio_ui.quality_pending', 'Ausstehend'),
    approved: tx('learning_studio_ui.quality_approved', 'Freigegeben'),
    rejected: tx('learning_studio_ui.quality_rejected', 'Überarbeitung nötig'),
}[status] || status)

const enrollmentStatusLabel = (status) => ({
    active: tx('learning_studio_ui.enrollment_active', 'Aktiv'),
    completed: tx('learning_studio_ui.enrollment_completed', 'Abgeschlossen'),
    revoked: tx('learning_studio_ui.enrollment_revoked', 'Deaktiviert'),
}[status] || status)

const questionStatusLabel = (status) => ({
    open: tx('learning_studio_ui.question_open', 'Offen'),
    answered: tx('learning_studio_ui.answered', 'Beantwortet'),
    resolved: tx('learning_studio_ui.resolved', 'Erledigt'),
}[status] || status)

const newCourseForm = useForm({
    title: '',
    subtitle: '',
    description: '',
    category: 'training',
    offer_type: 'course',
    sport_type: '',
    level: 'beginner',
    language: 'de',
    cover_image: '',
    status: 'draft',
    is_public: false,
    is_free: true,
    price: '',
    capacity: '',
    registration_deadline_at: '',
    starts_at: '',
    ends_at: '',
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
    translation_of_id: '',
})

const courseForm = useForm({
    title: '',
    subtitle: '',
    description: '',
    category: 'training',
    offer_type: 'course',
    sport_type: '',
    level: 'beginner',
    language: 'de',
    cover_image: '',
    status: 'draft',
    is_public: false,
    is_free: true,
    price: '',
    capacity: '',
    registration_deadline_at: '',
    starts_at: '',
    ends_at: '',
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
        offer_type: props.selectedCourse.offer_type || 'course',
        sport_type: props.selectedCourse.sport_type || '',
        level: props.selectedCourse.level || 'beginner',
        language: props.selectedCourse.language || 'de',
        cover_image: props.selectedCourse.cover_image || '',
        status: props.selectedCourse.status || 'draft',
        is_public: Boolean(props.selectedCourse.is_public),
        is_free: Boolean(props.selectedCourse.is_free),
        price: props.selectedCourse.price_cents ? String(Number(props.selectedCourse.price_cents) / 100).replace('.', ',') : '',
        capacity: props.selectedCourse.capacity || '',
        registration_deadline_at: datetimeLocalValue(props.selectedCourse.registration_deadline_at),
        starts_at: datetimeLocalValue(props.selectedCourse.starts_at),
        ends_at: datetimeLocalValue(props.selectedCourse.ends_at),
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
    offer_type: form.offer_type,
    sport_type: form.sport_type,
    level: form.level,
    language: form.language,
    cover_image: form.cover_image,
    status: form.status,
    is_public: form.is_public,
    is_free: form.is_free,
    price_cents: form.is_free ? 0 : majorToCents(form.price),
    capacity: form.capacity || null,
    registration_deadline_at: form.registration_deadline_at || null,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
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
    translation_of_id: form.translation_of_id || null,
})

const filterByLanguage = (language) => {
    router.get(route('auth.learning.studio.index'), {
        ...(language ? { language } : {}),
        ...(props.selectedCourse ? { course: props.selectedCourse.id } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['courses', 'selectedCourse', 'filters'],
    })
}

const startTranslation = (course, targetLocale) => {
    newCourseForm.reset()
    Object.assign(newCourseForm, {
        title: '',
        subtitle: '',
        description: '',
        category: course.category || 'training',
        offer_type: course.offer_type || 'course',
        sport_type: course.sport_type || '',
        level: course.level || 'beginner',
        language: targetLocale,
        cover_image: course.cover_image || '',
        status: 'draft',
        is_public: false,
        is_free: Boolean(course.is_free),
        price: course.price_cents ? String(Number(course.price_cents) / 100).replace('.', ',') : '',
        capacity: course.capacity || '',
        registration_deadline_at: datetimeLocalValue(course.registration_deadline_at),
        starts_at: datetimeLocalValue(course.starts_at),
        ends_at: datetimeLocalValue(course.ends_at),
        learning_goals_text: '',
        requirements_text: '',
        target_groups_text: '',
        sales_points_text: '',
        faq_items_text: '',
        guarantee_text: '',
        certificate_logo_url: course.certificate_logo_url || '',
        certificate_signature_name: course.certificate_signature_name || '',
        certificate_footer_text: '',
        tags_text: '',
        translation_of_id: course.id,
    })
    newCoursePanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    window.requestAnimationFrame(() => newCourseTitleInput.value?.focus())
}

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
    <Head :title="tx('learning_studio.page_title', 'Airmius Sportschule')" />

    <div class="space-y-6">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('learning_studio.eyebrow', 'Airmius Sportschule') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ tx('learning_studio.title', 'Kurs-Studio für Trainer und Tutoren') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        {{ tx('learning_studio.intro', 'Plane echte Online-Kurse mit Kapiteln, Lektionen, Aufgaben, Anhängen, Quiz, Notizen und Kurskommunikation.') }}
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
                <article ref="newCoursePanel" class="surface-card p-4">
                    <h2 class="text-base font-semibold text-primary">{{ tx('learning_studio.new_course', 'Neuen Kurs planen') }}</h2>
                    <form class="mt-4 grid gap-3" @submit.prevent="createCourse">
                        <p v-if="newCourseForm.translation_of_id" class="rounded-lg border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs leading-relaxed text-air-blue">
                            {{ lx('translation_source', { title: courses.find((course) => course.id === newCourseForm.translation_of_id)?.title || selectedCourse?.title || '' }) }}
                        </p>
                        <input ref="newCourseTitleInput" v-model="newCourseForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.course_title', 'Kurstitel')">
                        <p v-if="newCourseForm.errors.title" class="text-sm text-error">{{ newCourseForm.errors.title }}</p>
                        <input v-model="newCourseForm.subtitle" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.course_subtitle', 'Kurzversprechen')">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <select v-model="newCourseForm.category" :aria-label="tx('learning_studio_form.category', 'Kategorie')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in courseCategories" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="newCourseForm.offer_type" :aria-label="tx('learning_studio_form.offer_type', 'Angebotsart')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in offerTypes" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="newCourseForm.level" :aria-label="tx('learning_studio_form.level', 'Niveau')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in levels" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="newCourseForm.language" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="lx('content_language')">
                                <option v-for="supportedLocale in supportedLocales" :key="supportedLocale" :value="supportedLocale">{{ languageName(supportedLocale) }}</option>
                            </select>
                        </div>
                        <p v-if="newCourseForm.errors.language" class="text-sm text-error">{{ newCourseForm.errors.language }}</p>
                        <p v-if="newCourseForm.translation_of_id" class="text-xs leading-relaxed text-secondary">{{ lx('translation_hint') }}</p>
                        <input v-model="newCourseForm.sport_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.sport', 'Sportart, z. B. Fußball')">
                        <textarea v-model="newCourseForm.description" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.course_description', 'Worum geht es in diesem Kurs?')"></textarea>
                        <textarea v-model="newCourseForm.learning_goals_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.learning_goals', 'Lernziele, je Zeile eins')"></textarea>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="newCourseForm.processing">
                            {{ tx('learning_studio.create_course', 'Kurs anlegen') }}
                        </button>
                    </form>
                </article>

                <article class="surface-card overflow-hidden">
                    <div class="grid gap-3 border-b border-border p-4">
                        <h2 class="text-base font-semibold text-primary">{{ tx('learning_studio.my_courses', 'Meine Kurse') }}</h2>
                        <select :value="filters.language || ''" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="lx('content_language')" @change="filterByLanguage($event.target.value)">
                            <option value="">{{ lx('all_languages') }}</option>
                            <option v-for="supportedLocale in supportedLocales" :key="supportedLocale" :value="supportedLocale">{{ languageName(supportedLocale) }}</option>
                        </select>
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
                                    <p class="mt-1 text-xs text-secondary">{{ statusLabel(course.status) }} - {{ offerTypeLabel(course.offer_type) }} - {{ course.lessons_count }} Lektionen</p>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="rounded-full bg-bg px-2 py-1 text-xs font-semibold uppercase text-secondary">{{ course.language }}</span>
                                    <span class="rounded-full bg-bg px-2 py-1 text-xs font-semibold text-secondary">{{ course.level }}</span>
                                </div>
                            </div>
                        </Link>
                        <p v-if="!courses.length" class="p-4 text-sm text-secondary">{{ tx('learning_studio_ui.no_courses', 'Noch kein Kurs angelegt.') }}</p>
                    </div>
                </article>
            </aside>

            <section v-if="selectedCourse" class="space-y-4">
                <article class="surface-card overflow-hidden">
                    <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('learning_studio_ui.current_course', 'Aktueller Kurs') }}</p>
                            <h2 class="mt-1 break-words text-2xl font-bold text-primary">{{ selectedCourse.title }}</h2>
                            <p class="mt-2 text-sm text-secondary">{{ selectedCourse.subtitle || selectedCourse.description || tx('learning_studio_ui.course_fallback', 'Beschreibe den Kurs, damit Sportler sofort wissen, was sie lernen.') }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold uppercase text-secondary">{{ selectedCourse.language }}</span>
                                <button
                                    v-for="targetLocale in supportedLocales.filter((item) => !courseVariantLocales(selectedCourse).has(item))"
                                    :key="targetLocale"
                                    type="button"
                                    class="rounded-full border border-air-blue/40 px-3 py-1 text-xs font-semibold text-air-blue hover:bg-air-blue/10"
                                    @click="startTranslation(selectedCourse, targetLocale)"
                                >
                                    {{ lx('create_translation') }}: {{ languageName(targetLocale) }}
                                </button>
                            </div>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.status', 'Status') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ statusLabel(selectedCourse.status) }}</p>
                            <p class="mt-2 text-xs text-secondary">{{ offerTypeLabel(selectedCourse.offer_type) }} - {{ selectedCourse.is_free ? tx('learning_studio_ui.free', 'Kostenlos') : formatMoney(selectedCourse.price_cents, selectedCourse.currency) }} - {{ selectedCourse.capacity ? `${selectedCourse.capacity} Plätze` : tx('learning_studio_form.capacity_open', 'Kapazität offen') }}</p>
                            <p v-if="selectedCourse.registration_deadline_at" class="mt-1 text-xs text-secondary">{{ tx('learning_studio_form.registration_deadline', 'Anmeldeschluss') }}: {{ datetimeLocalValue(selectedCourse.registration_deadline_at).replace('T', ' ') }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ formatMinutes(selectedCourse.estimated_minutes) }}</p>
                            <a v-if="selectedCourse.preview_url" :href="selectedCourse.preview_url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted">
                                {{ tx('learning_studio_ui.view_as_student', 'Als Teilnehmer ansehen') }}
                            </a>
                        </div>
                    </div>
                    <div class="grid gap-3 border-t border-border p-5 md:grid-cols-4 xl:grid-cols-10">
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.average', 'Durchschnitt') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_progress || 0 }}%</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.completions', 'Abschlüsse') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.completed_enrollments || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.open_questions', 'Offene Fragen') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.open_questions || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.rating', 'Bewertung') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.average_rating || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.sales', 'Verkäufe') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.net_revenue', 'Umsatz netto') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(selectedCourse.analytics?.net_revenue_cents || 0, selectedCourse.currency) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.pending_payments', 'Offene Zahlungen') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.pending_sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.refunds', 'Refund/Storno') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.cancelled_sales_count || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.security_24h', 'Security 24h') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.security_events_24h || 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs uppercase text-secondary">{{ tx('learning_studio_ui.video_block_24h', 'Video-Block 24h') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ selectedCourse.analytics?.blocked_video_attempts_24h || 0 }}</p>
                        </div>
                    </div>
                    <div class="border-t border-border p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-secondary">{{ tx('learning_studio_ui.publish_check', 'Publish-Check') }}</p>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ tx('learning_studio_ui.checklist_progress', '{done} of {total} points complete ({score}%)', { done: selectedCourse.publish_checklist?.done_count || 0, total: selectedCourse.publish_checklist?.total_count || 0, score: selectedCourse.publish_checklist?.score ?? formatPercent(selectedCourse.publish_checklist?.done_count, selectedCourse.publish_checklist?.total_count) }) }}
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="selectedCourse.publish_checklist?.ready ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                {{ selectedCourse.publish_checklist?.ready ? tx('learning_studio_ui.ready', 'Bereit') : tx('learning_studio_ui.still_open', 'Noch offen') }}
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
                                <p class="text-xs font-semibold uppercase text-secondary">{{ tx('learning_studio_ui.monitoring', 'Monitoring') }}</p>
                                <span v-if="selectedCourse.analytics?.critical_security_events_24h" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">
                                    {{ selectedCourse.analytics.critical_security_events_24h }} {{ tx('learning_studio_ui.critical', 'kritisch') }}
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
                    <div class="flex gap-2 overflow-x-auto border-t border-border p-2" role="tablist" :aria-label="tx('learning_studio_ui.workspace_sections', 'Kursbereiche')">
                        <button
                            v-for="panel in [
                                ['structure', tx('learning_studio_ui.panel_structure', 'Struktur'), 'las la-list'],
                                ['details', tx('learning_studio_ui.panel_details', 'Kursdaten'), 'las la-sliders-h'],
                                ['sales', tx('learning_studio_ui.panel_sales', 'Landingpage'), 'las la-bullhorn'],
                                ['quiz', tx('learning_studio_ui.panel_quiz', 'Quiz'), 'las la-question-circle'],
                                ['assignments', tx('learning_studio_ui.panel_assignments', 'Aufgaben'), 'las la-clipboard-check'],
                                ['students', tx('learning_studio_ui.panel_students', 'Teilnehmer'), 'las la-users'],
                                ['questions', tx('learning_studio_ui.panel_questions', 'Fragen'), 'las la-comments'],
                            ]"
                            :key="panel[0]"
                            :id="`learning-studio-tab-${panel[0]}`"
                            type="button"
                            role="tab"
                            :aria-selected="activePanel === panel[0]"
                            :aria-controls="`learning-studio-panel-${panel[0]}`"
                            class="flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                            :class="activePanel === panel[0] ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                            @click="activePanel = panel[0]"
                        >
                            <i :class="panel[2]"></i>
                            <span>{{ panel[1] }}</span>
                        </button>
                    </div>
                </article>

                <div id="learning-studio-panel-structure" v-show="activePanel === 'structure'" role="tabpanel" aria-labelledby="learning-studio-tab-structure" class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <article class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.structure_title', 'Kursstruktur') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ tx('learning_studio_ui.structure_hint', 'Kapitel, Themen und Lektionen in didaktischer Reihenfolge.') }}</p>
                        </div>
                        <div class="divide-y divide-border">
                            <div v-for="section in selectedCourse.sections" :key="section.id" class="p-5">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('learning_studio_ui.chapter', 'Kapitel') }} {{ section.position }}</p>
                                        <h3 class="mt-1 text-lg font-semibold text-primary">{{ section.title }}</h3>
                                        <p v-if="section.description" class="mt-1 text-sm text-secondary">{{ section.description }}</p>
                                    </div>
                                    <span class="rounded-full bg-bg px-3 py-1 text-xs font-semibold text-secondary">{{ section.lessons?.length || 0 }} {{ tx('learning_studio_ui.lessons', 'Lektionen') }}</span>
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
                                                <span v-if="lesson.is_preview" class="rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">{{ tx('learning_studio_ui.preview', 'Preview') }}</span>
                                                <span v-if="lesson.unlock_after_days" class="rounded-full bg-warning/10 px-2 py-0.5 text-xs font-semibold text-warning">{{ tx('learning_studio_ui.unlock_day', 'Tag') }} {{ lesson.unlock_after_days }}</span>
                                            </div>
                                            <p class="mt-2 font-semibold text-primary">{{ lesson.title }}</p>
                                            <p v-if="lesson.summary" class="mt-1 line-clamp-2 text-sm text-secondary">{{ lesson.summary }}</p>
                                        </button>
                                        <p class="text-sm font-semibold text-secondary">{{ formatMinutes(lesson.duration_minutes) }}</p>
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="moveLesson(section, lesson, -1)">{{ tx('learning_studio_ui.move_up', 'Hoch') }}</button>
                                            <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="moveLesson(section, lesson, 1)">{{ tx('learning_studio_ui.move_down', 'Runter') }}</button>
                                            <button type="button" class="rounded border border-error/40 px-2 py-1 text-xs text-error" @click="deleteLesson(lesson)">{{ tx('learning_studio_ui.delete', 'Löschen') }}</button>
                                        </div>
                                    </div>
                                    <p v-if="!section.lessons?.length" class="rounded-lg border border-dashed border-border bg-bg p-4 text-sm text-secondary">{{ tx('learning_studio_ui.no_lessons', 'Noch keine Lektionen in diesem Kapitel.') }}</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <aside class="space-y-4">
                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">{{ tx('learning_studio_ui.add_chapter', 'Kapitel hinzufügen') }}</h2>
                            <form class="mt-4 grid gap-3" @submit.prevent="createSection">
                                <input v-model="sectionForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.chapter_name', 'Kapitelname')">
                                <textarea v-model="sectionForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.short_description', 'Kurzbeschreibung')"></textarea>
                                <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">{{ tx('learning_studio_ui.create_chapter', 'Kapitel erstellen') }}</button>
                            </form>
                        </article>

                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">{{ editingLesson ? tx('learning_studio_form.edit_lesson', 'Lektion bearbeiten') : tx('learning_studio_form.add_lesson', 'Lektion hinzufügen') }}</h2>
                            <form class="mt-4 grid gap-3" @submit.prevent="submitLesson">
                                <select v-model="lessonForm.learning_course_section_id" :aria-label="tx('learning_studio_form.section', 'Kapitel')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option v-for="section in selectedCourse.sections" :key="section.id" :value="section.id">{{ section.title }}</option>
                                </select>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input v-model="lessonForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.lesson_title', 'Lektionstitel')">
                                    <select v-model="lessonForm.type" :aria-label="tx('learning_studio_form.lesson_type', 'Lektionstyp')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                        <option v-for="[value, label] in lessonTypes" :key="value" :value="value">{{ label }}</option>
                                    </select>
                                </div>
                                <textarea v-model="lessonForm.summary" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.lesson_summary', 'Was passiert in dieser Lektion?')"></textarea>
                                <textarea v-model="lessonForm.content" rows="7" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.lesson_content', 'Skript, Aufgaben, Hinweise, Coaching-Text')"></textarea>
                                <div class="grid gap-2">
                                    <input v-model="lessonForm.video_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.video_url', 'Video-URL optional')">
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        <i class="las la-video"></i>
                                        {{ tx('learning_studio_form.upload_video', 'Video hochladen') }}
                                        <input type="file" accept="video/*" class="sr-only" @change="uploadLessonVideo">
                                    </label>
                                    <p v-if="uploadState.key === 'lesson_video'" class="text-xs text-secondary">{{ tx('learning_studio_form.video_uploading', 'Video wird hochgeladen...') }}</p>
                                    <p v-if="uploadState.error && uploadState.key === 'lesson_video'" class="text-xs text-error">{{ uploadState.error }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <textarea v-model="lessonForm.attachments_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.attachment_urls', 'Anhang-URLs, je Zeile eine')"></textarea>
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                        <i class="las la-paperclip"></i>
                                        {{ tx('learning_studio_form.upload_material', 'Material hochladen') }}
                                        <input type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,image/*,video/*" class="sr-only" @change="uploadLessonAttachment">
                                    </label>
                                    <p v-if="uploadState.key === 'lesson_attachment'" class="text-xs text-secondary">{{ tx('learning_studio_form.material_uploading', 'Material wird hochgeladen...') }}</p>
                                    <p v-if="uploadState.error && uploadState.key === 'lesson_attachment'" class="text-xs text-error">{{ uploadState.error }}</p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input v-model="lessonForm.duration_minutes" type="number" min="0" :aria-label="tx('learning_studio_form.duration', 'Dauer in Minuten')" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.duration', 'Dauer in Minuten')">
                                    <input v-model="lessonForm.position" type="number" min="1" :aria-label="tx('learning_studio_form.position', 'Position')" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.position', 'Position')">
                                </div>
                                <input v-model="lessonForm.unlock_after_days" type="number" min="0" max="3650" :aria-label="tx('learning_studio_form.unlock_after', 'Freischalten nach Tagen ab Einschreibung')" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.unlock_after', 'Freischalten nach Tagen ab Einschreibung')">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                                        <input v-model="lessonForm.is_preview" type="checkbox" class="rounded border-border bg-inputBg">
                                        {{ tx('learning_studio_form.preview_access', 'Als Preview freigeben') }}
                                    </label>
                                </div>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                    <button v-if="editingLesson" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="resetLessonForm">{{ tx('learning_studio_form.new_lesson', 'Neu anlegen') }}</button>
                                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="lessonForm.processing">
                                        {{ editingLesson ? tx('learning_studio_form.save_lesson', 'Lektion speichern') : tx('learning_studio_form.create_lesson', 'Lektion erstellen') }}
                                    </button>
                                </div>
                            </form>
                        </article>
                    </aside>
                </div>

                <article id="learning-studio-panel-details" v-show="activePanel === 'details'" role="tabpanel" aria-labelledby="learning-studio-tab-details" class="surface-card p-5">
                    <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_form.course_data_title', 'Kursdaten und Veröffentlichung') }}</h2>
                    <form class="mt-5 grid gap-4" @submit.prevent="updateCourse">
                        <div class="grid gap-3 lg:grid-cols-2">
                            <input v-model="courseForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.course_title', 'Kurstitel')">
                            <input v-model="courseForm.subtitle" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.course_subtitle', 'Kurzversprechen')">
                            <select v-model="courseForm.category" :aria-label="tx('learning_studio_form.category', 'Kategorie')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in courseCategories" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="courseForm.offer_type" :aria-label="tx('learning_studio_form.offer_type', 'Angebotsart')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in offerTypes" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <input v-model="courseForm.sport_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.sport', 'Sportart')">
                            <select v-model="courseForm.level" :aria-label="tx('learning_studio_form.level', 'Niveau')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="[value, label] in levels" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <select v-model="courseForm.language" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="lx('content_language')">
                                <option v-for="supportedLocale in supportedLocales" :key="supportedLocale" :value="supportedLocale">{{ languageName(supportedLocale) }}</option>
                            </select>
                            <p v-if="courseForm.errors.language" class="text-sm text-error">{{ courseForm.errors.language }}</p>
                        </div>
                        <div class="grid gap-2">
                            <input v-model="courseForm.cover_image" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.cover_url', 'Cover-Bild URL')">
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                <i class="las la-image"></i>
                                {{ tx('learning_studio_form.upload_cover', 'Cover hochladen') }}
                                <input type="file" accept="image/*" class="sr-only" @change="uploadCourseCover">
                            </label>
                            <p v-if="uploadState.key === 'cover'" class="text-xs text-secondary">{{ tx('learning_studio_form.cover_uploading', 'Cover wird hochgeladen...') }}</p>
                            <p v-if="uploadState.error && uploadState.key === 'cover'" class="text-xs text-error">{{ uploadState.error }}</p>
                        </div>
                        <textarea v-model="courseForm.description" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.description', 'Beschreibung')"></textarea>
                        <div class="grid gap-3 lg:grid-cols-4">
                            <input v-model="courseForm.capacity" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.capacity', 'Kapazität')">
                            <input v-model="courseForm.registration_deadline_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_form.registration_deadline', 'Anmeldeschluss')">
                            <input v-model="courseForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_form.starts_at', 'Start')">
                            <input v-model="courseForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_form.ends_at', 'Ende')">
                        </div>
                        <div class="grid gap-3 lg:grid-cols-3">
                            <textarea v-model="courseForm.learning_goals_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lernziele, je Zeile eins"></textarea>
                            <textarea v-model="courseForm.requirements_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.requirements', 'Voraussetzungen, je Zeile eine')"></textarea>
                            <textarea v-model="courseForm.target_groups_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.target_groups', 'Zielgruppen, je Zeile eine')"></textarea>
                        </div>
                        <div class="grid gap-3 lg:grid-cols-3">
                            <textarea v-model="courseForm.sales_points_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.sales_points', 'Verkaufsargumente, je Zeile eins')"></textarea>
                            <textarea v-model="courseForm.faq_items_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.faq', 'FAQ: Frage | Antwort')"></textarea>
                            <textarea v-model="courseForm.guarantee_text" rows="4" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.guarantee', 'Garantie / Betreuung / Rückfragen')"></textarea>
                        </div>
                        <div class="grid gap-3 lg:grid-cols-3">
                            <input v-model="courseForm.certificate_logo_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.certificate_logo', 'Zertifikat Logo URL')">
                            <input v-model="courseForm.certificate_signature_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.certificate_signature', 'Signatur auf Zertifikat')">
                            <input v-model="courseForm.certificate_footer_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.certificate_footer', 'Zertifikat Fußzeile')">
                        </div>
                        <input v-model="courseForm.tags_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_form.tags', 'Tags durch Komma trennen')">
                        <div class="grid gap-3 lg:grid-cols-4">
                            <select v-model="courseForm.status" :aria-label="tx('learning_studio_form.status', 'Status')" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="draft">{{ tx('learning_studio_form.draft', 'Entwurf') }}</option>
                                <option value="review">{{ tx('learning_studio_form.review', 'Zur Prüfung') }}</option>
                                <option value="published">{{ tx('learning_studio_form.published', 'Veröffentlicht') }}</option>
                                <option value="archived">{{ tx('learning_studio_form.archived', 'Archiviert') }}</option>
                            </select>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                                <input v-model="courseForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">
                                {{ tx('learning_studio_form.public', 'Öffentlich sichtbar') }}
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                                <input v-model="courseForm.is_free" type="checkbox" class="rounded border-border bg-inputBg">
                                {{ tx('learning_studio_ui.free', 'Kostenlos') }}
                            </label>
                            <input v-if="!courseForm.is_free" v-model="courseForm.price" inputmode="decimal" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.price_euro', 'Preis in Euro')">
                        </div>
                        <button class="justify-self-start rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="courseForm.processing">
                            {{ tx('learning_studio_form.save_course', 'Kursdaten speichern') }}
                        </button>
                    </form>
                </article>

                <article id="learning-studio-panel-sales" v-show="activePanel === 'sales'" role="tabpanel" aria-labelledby="learning-studio-tab-sales" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.sales_heading', 'Landingpage, Gutscheine und Review') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('learning_studio_ui.sales_intro', 'Alles, was Besucher vor dem Kauf brauchen: Nutzen, FAQ, Rabatte und Qualitätsstatus.') }}</p>
                    </div>
                    <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-4">
                            <div class="rounded-lg border border-border bg-bg p-4">
                                <p class="text-xs font-semibold uppercase text-secondary">{{ tx('learning_studio_ui.quality_review', 'Qualitätsreview') }}</p>
                                <p class="mt-2 text-lg font-bold text-primary">{{ qualityStatusLabel(selectedCourse.quality_status || 'pending') }}</p>
                                <p v-if="selectedCourse.quality_note" class="mt-1 text-sm text-secondary">{{ selectedCourse.quality_note }}</p>
                            </div>
                            <div class="grid gap-3">
                                <div v-for="coupon in selectedCourse.coupons" :key="coupon.id" class="rounded-lg border border-border bg-bg p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <p class="font-bold text-primary">{{ coupon.code }}</p>
                                        <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ coupon.redeemed_count || 0 }} {{ tx('learning_studio_ui.used', 'genutzt') }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-secondary">{{ coupon.discount_type === 'fixed' ? formatMoney(coupon.discount_value) : `${coupon.discount_value}%` }} {{ tx('learning_studio_ui.discount', 'Rabatt') }}</p>
                                </div>
                                <p v-if="!selectedCourse.coupons?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">{{ tx('learning_studio_ui.no_coupons', 'Noch keine Gutscheine angelegt.') }}</p>
                            </div>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createCoupon">
                            <h3 class="font-semibold text-primary">{{ tx('learning_studio_ui.create_coupon', 'Gutschein anlegen') }}</h3>
                            <input v-model="couponForm.code" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.coupon_code', 'Code, z. B. TEAM20')" :aria-label="tx('learning_studio_ui.coupon_code', 'Code, z. B. TEAM20')">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <select v-model="couponForm.discount_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_ui.discount_type', 'Rabattart')">
                                    <option value="percent">{{ tx('learning_studio_ui.percent', 'Prozent') }}</option>
                                    <option value="fixed">{{ tx('learning_studio_ui.fixed_amount_cents', 'Fixbetrag in Cent') }}</option>
                                </select>
                                <input v-model="couponForm.discount_value" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.value', 'Wert')" :aria-label="tx('learning_studio_ui.value', 'Wert')">
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="couponForm.max_redemptions" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.max_redemptions', 'Max. Nutzungen')" :aria-label="tx('learning_studio_ui.max_redemptions', 'Max. Nutzungen')">
                                <input v-model="couponForm.expires_at" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_ui.expires_at', 'Gültig bis')">
                            </div>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                <input v-model="couponForm.is_active" type="checkbox" class="rounded border-border bg-inputBg" :aria-label="tx('learning_studio_ui.active', 'Aktiv')">
                                {{ tx('learning_studio_ui.active', 'Aktiv') }}
                            </label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ tx('learning_studio_ui.save_coupon', 'Gutschein speichern') }}</button>
                        </form>
                    </div>
                </article>

                <article id="learning-studio-panel-quiz" v-show="activePanel === 'quiz'" role="tabpanel" aria-labelledby="learning-studio-tab-quiz" class="surface-card p-5">
                    <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.quiz_heading', 'Quiz und Wissenschecks') }}</h2>
                    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-3">
                            <div v-for="quiz in selectedCourse.quizzes" :key="quiz.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-primary">{{ quiz.title }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ quiz.description || tx('learning_studio_ui.no_description_text', 'Kein Beschreibungstext') }}</p>
                                    </div>
                                    <span class="rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">{{ quiz.pass_percent }}% {{ tx('learning_studio_ui.passing', 'Bestehen') }}</span>
                                </div>
                                <p class="mt-3 text-xs text-secondary">{{ quiz.questions?.length || 0 }} {{ tx('learning_studio_ui.questions', 'Fragen') }}</p>
                                <button type="button" class="mt-3 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="deleteQuiz(quiz)">
                                    {{ tx('learning_studio_ui.delete_quiz', 'Quiz löschen') }}
                                </button>
                            </div>
                            <p v-if="!selectedCourse.quizzes?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">{{ tx('learning_studio_ui.no_quizzes', 'Noch kein Quiz angelegt.') }}</p>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createQuiz">
                            <h3 class="font-semibold text-primary">{{ tx('learning_studio_ui.create_quiz', 'Quiz erstellen') }}</h3>
                            <select v-model="quizForm.learning_lesson_id" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_ui.lesson_scope', 'Lektion zuordnen')">
                                <option value="">{{ tx('learning_studio_ui.general_course_quiz', 'Allgemeines Kursquiz') }}</option>
                                <option v-for="lesson in allLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                            </select>
                            <input v-model="quizForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.quiz_title', 'Quiztitel')" :aria-label="tx('learning_studio_ui.quiz_title', 'Quiztitel')">
                            <textarea v-model="quizForm.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.description', 'Beschreibung')" :aria-label="tx('learning_studio_ui.description', 'Beschreibung')"></textarea>
                            <input v-model="quizForm.pass_percent" type="number" min="1" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.pass_percent', 'Bestehensgrenze in %')" :aria-label="tx('learning_studio_ui.pass_percent', 'Bestehensgrenze in %')">
                            <textarea v-model="quizForm.question" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.first_question_optional', 'Erste Frage optional')" :aria-label="tx('learning_studio_ui.first_question_optional', 'Erste Frage optional')"></textarea>
                            <textarea v-model="quizForm.options_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.answer_options', 'Antwortoptionen, je Zeile eine')" :aria-label="tx('learning_studio_ui.answer_options', 'Antwortoptionen, je Zeile eine')"></textarea>
                            <textarea v-model="quizForm.correct_options_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.correct_answers', 'Korrekte Antwort(en), je Zeile eine')" :aria-label="tx('learning_studio_ui.correct_answers', 'Korrekte Antwort(en), je Zeile eine')"></textarea>
                            <textarea v-model="quizForm.explanation" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.answer_explanation', 'Erklärung nach Antwort')" :aria-label="tx('learning_studio_ui.answer_explanation', 'Erklärung nach Antwort')"></textarea>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ tx('learning_studio_ui.save_quiz', 'Quiz speichern') }}</button>
                        </form>
                    </div>
                </article>

                <article id="learning-studio-panel-assignments" v-show="activePanel === 'assignments'" role="tabpanel" aria-labelledby="learning-studio-tab-assignments" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.assignment_heading', 'Aufgaben und manuelle Bewertung') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('learning_studio_ui.assignment_intro', 'Teilnehmer reichen Text oder Links ein, Tutoren geben Score und Feedback zurück.') }}</p>
                    </div>
                    <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="grid gap-4">
                            <div v-for="assignment in selectedCourse.assignments" :key="assignment.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-primary">{{ assignment.title }}</p>
                                        <p class="mt-1 text-sm text-secondary">{{ assignment.instructions || tx('learning_studio_ui.no_description', 'Keine Beschreibung') }}</p>
                                    </div>
                                    <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ assignment.points }} {{ tx('learning_studio_ui.points', 'Punkte') }}</span>
                                </div>
                                <div v-if="assignment.submissions?.length" class="mt-4 grid gap-3">
                                    <div v-for="submission in assignment.submissions" :key="submission.id" class="rounded-lg border border-border bg-card p-3">
                                        <p class="text-sm font-semibold text-primary">{{ submission.user?.name || tx('learning_studio_ui.participant', 'Teilnehmer') }}</p>
                                        <p class="mt-1 text-sm text-secondary whitespace-pre-line">{{ submission.body }}</p>
                                        <a v-if="submission.attachment_url" :href="submission.attachment_url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-air-blue">{{ tx('learning_studio_ui.open_attachment', 'Anhang öffnen') }}</a>
                                        <div class="mt-3 grid gap-2 md:grid-cols-[8rem_7rem_minmax(0,1fr)_auto]">
                                            <select v-model="gradingForms[String(submission.id)].status" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_ui.status', 'Status')">
                                                <option value="passed">{{ tx('learning_studio_ui.passed', 'Bestanden') }}</option>
                                                <option value="needs_revision">{{ tx('learning_studio_ui.needs_revision', 'Revision') }}</option>
                                                <option value="rejected">{{ tx('learning_studio_ui.rejected', 'Abgelehnt') }}</option>
                                            </select>
                                            <input v-model="gradingForms[String(submission.id)].score" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.score', 'Score')" :aria-label="tx('learning_studio_ui.score', 'Score')">
                                            <input v-model="gradingForms[String(submission.id)].feedback" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.feedback', 'Feedback')" :aria-label="tx('learning_studio_ui.feedback', 'Feedback')">
                                            <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="gradeSubmission(submission)">{{ tx('learning_studio_ui.grade', 'Bewerten') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!selectedCourse.assignments?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">{{ tx('learning_studio_ui.no_assignments', 'Noch keine Aufgaben angelegt.') }}</p>
                        </div>
                        <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="createAssignment">
                            <h3 class="font-semibold text-primary">{{ tx('learning_studio_ui.create_assignment', 'Aufgabe erstellen') }}</h3>
                            <select v-model="assignmentForm.learning_lesson_id" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="tx('learning_studio_ui.lesson_scope', 'Lektion zuordnen')">
                                <option value="">{{ tx('learning_studio_ui.general_course_assignment', 'Allgemeine Kursaufgabe') }}</option>
                                <option v-for="lesson in allLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                            </select>
                            <input v-model="assignmentForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.assignment_title', 'Aufgabentitel')" :aria-label="tx('learning_studio_ui.assignment_title', 'Aufgabentitel')">
                            <textarea v-model="assignmentForm.instructions" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.instructions', 'Aufgabenstellung')" :aria-label="tx('learning_studio_ui.instructions', 'Aufgabenstellung')"></textarea>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="assignmentForm.points" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.points', 'Punkte')" :aria-label="tx('learning_studio_ui.points', 'Punkte')">
                                <input v-model="assignmentForm.due_after_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.due_after_days', 'Fällig nach Tagen')" :aria-label="tx('learning_studio_ui.due_after_days', 'Fällig nach Tagen')">
                            </div>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                                <input v-model="assignmentForm.is_required" type="checkbox" class="rounded border-border bg-inputBg" :aria-label="tx('learning_studio_ui.required_assignment', 'Pflichtaufgabe')">
                                {{ tx('learning_studio_ui.required_assignment', 'Pflichtaufgabe') }}
                            </label>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ tx('learning_studio_ui.save_assignment', 'Aufgabe speichern') }}</button>
                        </form>
                    </div>
                </article>

                <article id="learning-studio-panel-students" v-show="activePanel === 'students'" role="tabpanel" aria-labelledby="learning-studio-tab-students" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.students_heading', 'Teilnehmer und Betreuung') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('learning_studio_ui.students_intro', 'Einschreibungen, Fortschritt, manuelle Freischaltung und CSV-Reporting.') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="grantEnrollment">
                                <input v-model="enrollmentForm.email" type="email" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.enrollment_email', 'E-Mail für manuellen Zugang')" :aria-label="tx('learning_studio_ui.enrollment_email', 'E-Mail für manuellen Zugang')">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ tx('learning_studio_ui.grant_access', 'Freischalten') }}</button>
                            </form>
                            <a :href="route('auth.learning.studio.courses.report', selectedCourse.id)" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">{{ tx('learning_studio_ui.csv_export', 'CSV Export') }}</a>
                        </div>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="enrollment in selectedCourse.enrollments" :key="enrollment.id" class="grid gap-3 p-5 md:grid-cols-[minmax(0,1fr)_8rem_8rem_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-primary">{{ enrollment.user?.name || tx('learning_studio_ui.participant', 'Teilnehmer') }}</p>
                                <p class="text-sm text-secondary">{{ enrollment.user?.email }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ enrollment.completed_lessons_count || 0 }} {{ tx('learning_studio_ui.lessons_completed', 'Lektionen erledigt') }} -
                                    {{ enrollment.passed_quizzes_count || 0 }} {{ tx('learning_studio_ui.quizzes_passed', 'Quiz bestanden') }} -
                                    {{ enrollment.assignments_passed_count || 0 }} {{ tx('learning_studio_ui.assignments_passed', 'Aufgaben bestanden') }}
                                </p>
                                <p v-if="enrollment.completion_requirements" class="mt-1 text-xs text-secondary">
                                    {{ tx('learning_studio_ui.completion', 'Abschluss') }}: {{ enrollment.completion_requirements.lessons.completed }}/{{ enrollment.completion_requirements.lessons.total }} {{ tx('learning_studio_ui.lessons', 'Lektionen') }},
                                    {{ enrollment.completion_requirements.quizzes.completed }}/{{ enrollment.completion_requirements.quizzes.total }} {{ tx('learning_studio_ui.quiz', 'Quiz') }},
                                    {{ enrollment.completion_requirements.assignments.completed }}/{{ enrollment.completion_requirements.assignments.total }} {{ tx('learning_studio_ui.required_assignments', 'Pflicht-Aufgaben') }}
                                </p>
                                <p v-if="enrollment.certificate" class="mt-1 text-xs font-semibold text-success">{{ tx('learning_studio_ui.certificate', 'Zertifikat') }} {{ enrollment.certificate.code }}</p>
                            </div>
                            <p class="text-sm font-semibold text-secondary">{{ enrollmentStatusLabel(enrollment.status) }}</p>
                            <p class="text-sm font-semibold text-primary">{{ enrollment.progress_percent }}%</p>
                            <button class="rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="revokeEnrollment(enrollment)">{{ tx('learning_studio_ui.deactivate', 'Deaktivieren') }}</button>
                        </div>
                        <p v-if="!selectedCourse.enrollments?.length" class="p-5 text-sm text-secondary">{{ tx('learning_studio_ui.no_enrollments', 'Noch keine Teilnehmer eingeschrieben.') }}</p>
                    </div>
                </article>

                <article id="learning-studio-panel-questions" v-show="activePanel === 'questions'" role="tabpanel" aria-labelledby="learning-studio-tab-questions" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('learning_studio_ui.questions_heading', 'Fragen-Inbox') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tx('learning_studio_ui.questions_intro', 'Offene Fragen aus den Lektionen mit Status für Betreuung und Nacharbeit.') }}</p>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="question in selectedCourse.questions" :key="question.id" class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_12rem]">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-bg px-2 py-1 text-xs font-semibold text-secondary">{{ question.lesson_title }}</span>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="question.status === 'resolved' ? 'bg-success/10 text-success' : question.status === 'answered' ? 'bg-air-blue/10 text-air-blue' : 'bg-warning/10 text-warning'">
                                        {{ questionStatusLabel(question.status) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-primary">{{ question.user?.name || tx('learning_studio_ui.participant', 'Teilnehmer') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-secondary">{{ question.body }}</p>
                                <div v-if="question.replies?.length" class="mt-3 grid gap-2 border-l border-border pl-3">
                                    <div v-for="reply in question.replies" :key="reply.id" class="rounded-lg bg-bg p-3">
                                        <p class="text-xs font-semibold text-air-blue">{{ reply.user?.name || tx('learning_studio_ui.tutor', 'Tutor') }}</p>
                                        <p class="mt-1 text-sm leading-relaxed text-secondary">{{ reply.body }}</p>
                                    </div>
                                </div>
                                <form class="mt-3 grid gap-2" @submit.prevent="submitQuestionReply(question)">
                                    <textarea v-model="replyForms[String(question.id)]" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="tx('learning_studio_ui.reply_placeholder', 'Antwort für den Teilnehmer schreiben')" :aria-label="tx('learning_studio_ui.reply_placeholder', 'Antwort für den Teilnehmer schreiben')"></textarea>
                                    <button class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary">
                                        {{ tx('learning_studio_ui.send_reply', 'Antwort senden') }}
                                    </button>
                                </form>
                            </div>
                            <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                                <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="updateQuestionStatus(question, 'answered')">
                                    {{ tx('learning_studio_ui.answered', 'Beantwortet') }}
                                </button>
                                <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="updateQuestionStatus(question, 'resolved')">
                                    {{ tx('learning_studio_ui.resolved', 'Erledigt') }}
                                </button>
                            </div>
                        </div>
                        <p v-if="!selectedCourse.questions?.length" class="p-5 text-sm text-secondary">{{ tx('learning_studio_ui.no_questions', 'Noch keine Kursfragen vorhanden.') }}</p>
                    </div>
                </article>
            </section>

            <section v-else class="surface-card flex min-h-[28rem] items-center justify-center p-8 text-center">
                <div class="max-w-md">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-bg">
                        <i class="las la-graduation-cap text-4xl text-air-blue"></i>
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-primary">{{ tx('learning_studio_ui.start_title', 'Starte deine Online-Sportschule') }}</h2>
                    <p class="mt-2 text-sm text-secondary">{{ tx('learning_studio_ui.start_intro', 'Lege links deinen ersten Kurs an. Danach kannst du Kapitel, Lektionen und Quiz direkt im Studio bauen.') }}</p>
                </div>
            </section>
        </section>
    </div>
</template>
