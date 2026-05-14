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
})

const page = usePage()
const firstLesson = computed(() => props.course.sections?.flatMap((section) => section.lessons || [])?.[0] || null)
const selectedLesson = ref(firstLesson.value)
const isAuthed = computed(() => Boolean(page.props.auth?.user))
const canUseLearningRoom = computed(() => isAuthed.value && (props.enrollment || page.props.auth?.user?.id === props.course.tutor?.id))

watch(firstLesson, (lesson) => {
    if (!selectedLesson.value && lesson) selectedLesson.value = lesson
})

const noteForm = useForm({ body: '' })
const commentForm = useForm({ body: '' })

const formatMinutes = (minutes) => {
    const value = Number(minutes || 0)
    if (value < 60) return `${value} Min.`
    return `${Math.floor(value / 60)} Std. ${value % 60} Min.`
}

const enroll = () => {
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
</script>

<template>
    <SeoHead :title="course.title" :description="course.subtitle || course.description || 'Airmius Sportschule Kurs ansehen.'" />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div>
                    <Link :href="route('guest.e-learning')" class="text-sm font-semibold text-air-orange">Zurueck zur Sportschule</Link>
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
                        <article class="surface-card p-5">
                            <h2 class="text-lg font-bold text-primary">Fuer wen ist der Kurs?</h2>
                            <ul class="mt-4 space-y-3">
                                <li v-for="group in course.target_groups" :key="group" class="flex gap-2 text-sm text-secondary">
                                    <i class="las la-user-check mt-0.5 text-air-orange"></i>
                                    <span>{{ group }}</span>
                                </li>
                                <li v-if="!course.target_groups?.length" class="text-sm text-secondary">Geeignet fuer Sportler, Trainer und Teams mit Interesse am Thema.</li>
                            </ul>
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
                                        <span v-if="lesson.is_preview || canUseLearningRoom" class="rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">Oeffnen</span>
                                        <i v-else class="las la-lock text-secondary"></i>
                                    </button>
                                </div>
                            </div>
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
                            class="mt-5 w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary"
                            @click="enroll"
                        >
                            {{ enrollment ? 'Zum Lernraum' : 'Kostenlos einschreiben' }}
                        </button>
                        <p class="mt-3 text-xs text-secondary">Nach der Einschreibung kannst du Notizen schreiben und Fragen im Kurs posten.</p>
                    </article>

                    <article v-if="selectedLesson" class="surface-card overflow-hidden">
                        <div class="border-b border-border p-5">
                            <p class="text-xs font-semibold uppercase text-secondary">Lernraum</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">{{ selectedLesson.title }}</h2>
                        </div>
                        <div class="p-5">
                            <p class="text-sm leading-relaxed text-secondary">{{ selectedLesson.summary || 'Waehle eine Lektion, um Inhalte, Notizen und Fragen zu bearbeiten.' }}</p>
                            <div v-if="canUseLearningRoom" class="mt-5 grid gap-4">
                                <div v-if="selectedLesson.content" class="rounded-lg border border-border bg-bg p-4 text-sm leading-relaxed text-primary whitespace-pre-line">{{ selectedLesson.content }}</div>
                                <a v-if="selectedLesson.video_url" :href="selectedLesson.video_url" target="_blank" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Video oeffnen</a>
                                <form class="grid gap-2" @submit.prevent="submitNote">
                                    <textarea v-model="noteForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Private Notiz zu dieser Lektion"></textarea>
                                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Notiz speichern</button>
                                </form>
                                <form class="grid gap-2" @submit.prevent="submitComment">
                                    <textarea v-model="commentForm.body" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Frage an den Tutor oder Kurschat"></textarea>
                                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Frage senden</button>
                                </form>
                            </div>
                            <div v-else class="mt-5 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                                Schreibe dich ein, um Inhalte, Notizen und Kursfragen zu nutzen.
                            </div>
                        </div>
                    </article>
                </aside>
            </section>
        </main>

        <Footer />
    </div>
</template>
