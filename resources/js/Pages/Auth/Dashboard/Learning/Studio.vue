<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { majorToCents } from '@/utils/currency'

defineOptions({ layout: AppLayout })

const props = defineProps({
    courses: { type: Array, default: () => [] },
    selectedCourse: { type: Object, default: null },
})

const page = usePage()
const activePanel = ref('structure')
const editingLesson = ref(null)

const courseCategories = [
    ['training', 'Training'],
    ['nutrition', 'Ernaehrung'],
    ['mindset', 'Mindset'],
    ['tactics', 'Taktik'],
    ['rehab', 'Reha & Praevention'],
    ['coaching', 'Coaching'],
    ['club_management', 'Vereinsfuehrung'],
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
    ['exercise', 'Uebung'],
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

const statusLabel = (status) => ({
    draft: 'Entwurf',
    review: 'Pruefung',
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
    is_preview: false,
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
        tags_text: props.selectedCourse.tags_text || '',
    })
    courseForm.reset()
}

watch(() => props.selectedCourse?.id, () => {
    fillCourseForm()
    editingLesson.value = null
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
        is_preview: false,
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
        is_preview: Boolean(lesson.is_preview),
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

const createQuiz = () => {
    if (!props.selectedCourse) return
    quizForm.post(route('auth.learning.studio.quizzes.store', props.selectedCourse.id), {
        preserveScroll: true,
        onSuccess: () => quizForm.reset('title', 'description', 'question', 'options_text', 'correct_options_text', 'explanation'),
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
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kurs-Studio fuer Trainer und Tutoren</h1>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        Plane echte Online-Kurse mit Kapiteln, Lektionen, Aufgaben, Anhaengen, Quiz, Notizen und Kurskommunikation.
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
                        <input v-model="newCourseForm.sport_type" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Sportart, z. B. Fussball">
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
                        </div>
                    </div>
                    <div class="flex gap-2 overflow-x-auto border-t border-border p-2">
                        <button
                            v-for="panel in [
                                ['structure', 'Struktur', 'las la-list'],
                                ['details', 'Kursdaten', 'las la-sliders-h'],
                                ['quiz', 'Quiz', 'las la-question-circle'],
                                ['students', 'Teilnehmer', 'las la-users'],
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
                                    <button
                                        v-for="lesson in section.lessons"
                                        :key="lesson.id"
                                        type="button"
                                        class="grid gap-3 rounded-lg border border-border bg-bg p-3 text-left transition hover:border-air-blue md:grid-cols-[minmax(0,1fr)_8rem_auto] md:items-center"
                                        @click="editLesson(lesson)"
                                    >
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-card px-2 py-0.5 text-xs font-semibold text-secondary">{{ lesson.type }}</span>
                                                <span v-if="lesson.is_preview" class="rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">Preview</span>
                                            </div>
                                            <p class="mt-2 font-semibold text-primary">{{ lesson.title }}</p>
                                            <p v-if="lesson.summary" class="mt-1 line-clamp-2 text-sm text-secondary">{{ lesson.summary }}</p>
                                        </div>
                                        <p class="text-sm font-semibold text-secondary">{{ formatMinutes(lesson.duration_minutes) }}</p>
                                        <i class="las la-pen text-xl text-air-blue"></i>
                                    </button>
                                    <p v-if="!section.lessons?.length" class="rounded-lg border border-dashed border-border bg-bg p-4 text-sm text-secondary">Noch keine Lektionen in diesem Kapitel.</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <aside class="space-y-4">
                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">Kapitel hinzufuegen</h2>
                            <form class="mt-4 grid gap-3" @submit.prevent="createSection">
                                <input v-model="sectionForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kapitelname">
                                <textarea v-model="sectionForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurzbeschreibung"></textarea>
                                <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Kapitel erstellen</button>
                            </form>
                        </article>

                        <article class="surface-card p-5">
                            <h2 class="text-base font-semibold text-primary">{{ editingLesson ? 'Lektion bearbeiten' : 'Lektion hinzufuegen' }}</h2>
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
                                <input v-model="lessonForm.video_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Video-URL optional">
                                <textarea v-model="lessonForm.attachments_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anhang-URLs, je Zeile eine"></textarea>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input v-model="lessonForm.duration_minutes" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Dauer in Minuten">
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
                    <h2 class="text-lg font-semibold text-primary">Kursdaten und Veroeffentlichung</h2>
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
                        <input v-model="courseForm.cover_image" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Cover-Bild URL">
                        <textarea v-model="courseForm.description" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                        <div class="grid gap-3 lg:grid-cols-3">
                            <textarea v-model="courseForm.learning_goals_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lernziele, je Zeile eins"></textarea>
                            <textarea v-model="courseForm.requirements_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Voraussetzungen, je Zeile eine"></textarea>
                            <textarea v-model="courseForm.target_groups_text" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Zielgruppen, je Zeile eine"></textarea>
                        </div>
                        <input v-model="courseForm.tags_text" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tags durch Komma trennen">
                        <div class="grid gap-3 lg:grid-cols-4">
                            <select v-model="courseForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="draft">Entwurf</option>
                                <option value="review">Zur Pruefung</option>
                                <option value="published">Veroeffentlicht</option>
                                <option value="archived">Archiviert</option>
                            </select>
                            <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                                <input v-model="courseForm.is_public" type="checkbox" class="rounded border-border bg-inputBg">
                                Oeffentlich sichtbar
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

                <article v-show="activePanel === 'students'" class="surface-card overflow-hidden">
                    <div class="border-b border-border p-5">
                        <h2 class="text-lg font-semibold text-primary">Teilnehmer und Betreuung</h2>
                        <p class="mt-1 text-sm text-secondary">Hier landen eingeschriebene Sportler, spaeter mit Fortschritt, Chat und Feedbackverlauf.</p>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="enrollment in selectedCourse.enrollments" :key="enrollment.id" class="grid gap-3 p-5 md:grid-cols-[minmax(0,1fr)_8rem_8rem] md:items-center">
                            <div>
                                <p class="font-semibold text-primary">{{ enrollment.user?.name || 'Teilnehmer' }}</p>
                                <p class="text-sm text-secondary">{{ enrollment.user?.email }}</p>
                            </div>
                            <p class="text-sm font-semibold text-secondary">{{ enrollment.status }}</p>
                            <p class="text-sm font-semibold text-primary">{{ enrollment.progress_percent }}%</p>
                        </div>
                        <p v-if="!selectedCourse.enrollments?.length" class="p-5 text-sm text-secondary">Noch keine Teilnehmer eingeschrieben.</p>
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
