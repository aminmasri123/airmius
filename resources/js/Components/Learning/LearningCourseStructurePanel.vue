<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    editingLesson: { type: Object, default: null },
    sectionForm: { type: Object, required: true },
    lessonForm: { type: Object, required: true },
    lessonTypes: { type: Array, default: () => [] },
    uploadState: { type: Object, required: true },
    formatMinutes: { type: Function, required: true },
})

defineEmits([
    'createSection',
    'submitLesson',
    'editLesson',
    'moveLesson',
    'deleteLesson',
    'resetLesson',
    'uploadLessonVideo',
    'uploadLessonAttachment',
])
</script>

<template>
    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_24rem]">
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
                            <button type="button" class="min-w-0 text-left" @click="$emit('editLesson', lesson)">
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
                                <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="$emit('moveLesson', section, lesson, -1)">Hoch</button>
                                <button type="button" class="rounded border border-border px-2 py-1 text-xs text-secondary" @click="$emit('moveLesson', section, lesson, 1)">Runter</button>
                                <button type="button" class="rounded border border-error/40 px-2 py-1 text-xs text-error" @click="$emit('deleteLesson', lesson)">Löschen</button>
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
                <form class="mt-4 grid gap-3" @submit.prevent="$emit('createSection')">
                    <input v-model="sectionForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kapitelname">
                    <textarea v-model="sectionForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurzbeschreibung"></textarea>
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">Kapitel erstellen</button>
                </form>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-base font-semibold text-primary">{{ editingLesson ? 'Lektion bearbeiten' : 'Lektion hinzufügen' }}</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="$emit('submitLesson')">
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
                            <input type="file" accept="video/*" class="sr-only" @change="$emit('uploadLessonVideo', $event)">
                        </label>
                        <p v-if="uploadState.key === 'lesson_video'" class="text-xs text-secondary">Video wird hochgeladen...</p>
                        <p v-if="uploadState.error && uploadState.key === 'lesson_video'" class="text-xs text-error">{{ uploadState.error }}</p>
                    </div>
                    <div class="grid gap-2">
                        <textarea v-model="lessonForm.attachments_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anhang-URLs, je Zeile eine"></textarea>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            <i class="las la-paperclip"></i>
                            Material hochladen
                            <input type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,image/*,video/*" class="sr-only" @change="$emit('uploadLessonAttachment', $event)">
                        </label>
                        <p v-if="uploadState.key === 'lesson_attachment'" class="text-xs text-secondary">Material wird hochgeladen...</p>
                        <p v-if="uploadState.error && uploadState.key === 'lesson_attachment'" class="text-xs text-error">{{ uploadState.error }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="lessonForm.duration_minutes" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Dauer in Minuten">
                        <input v-model="lessonForm.position" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Position">
                    </div>
                    <input v-model="lessonForm.unlock_after_days" type="number" min="0" max="3650" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Freischalten nach Tagen ab Einschreibung">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-secondary">
                        <input v-model="lessonForm.is_preview" type="checkbox" class="rounded border-border bg-inputBg">
                        Als Preview freigeben
                    </label>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button v-if="editingLesson" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="$emit('resetLesson')">Neu anlegen</button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="lessonForm.processing">
                            {{ editingLesson ? 'Lektion speichern' : 'Lektion erstellen' }}
                        </button>
                    </div>
                </form>
            </article>
        </aside>
    </div>
</template>


