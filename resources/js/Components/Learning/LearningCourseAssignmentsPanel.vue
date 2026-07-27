<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    assignmentForm: { type: Object, required: true },
    gradingForms: { type: Object, required: true },
    allLessons: { type: Array, default: () => [] },
})

defineEmits(['createAssignment', 'gradeSubmission'])
</script>

<template>
    <article class="surface-card overflow-hidden">
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
                            <p class="mt-1 whitespace-pre-line text-sm text-secondary">{{ submission.body }}</p>
                            <a v-if="submission.attachment_url" :href="submission.attachment_url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-air-blue">Anhang öffnen</a>
                            <div v-if="gradingForms[String(submission.id)]" class="mt-3 grid gap-2 md:grid-cols-[8rem_7rem_minmax(0,1fr)_auto]">
                                <select v-model="gradingForms[String(submission.id)].status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                    <option value="passed">Bestanden</option>
                                    <option value="needs_revision">Revision</option>
                                    <option value="rejected">Abgelehnt</option>
                                </select>
                                <input v-model="gradingForms[String(submission.id)].score" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Score">
                                <input v-model="gradingForms[String(submission.id)].feedback" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Feedback">
                                <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="$emit('gradeSubmission', submission)">Bewerten</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="!selectedCourse.assignments?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch keine Aufgaben angelegt.</p>
            </div>
            <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="$emit('createAssignment')">
                <h3 class="font-semibold text-primary">Aufgabe erstellen</h3>
                <select v-model="assignmentForm.learning_lesson_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="">Allgemeine Kursaufgabe</option>
                    <option v-for="lesson in allLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                </select>
                <input v-model="assignmentForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Aufgabentitel">
                <textarea v-model="assignmentForm.instructions" rows="5" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Aufgabenstellung"></textarea>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="assignmentForm.points" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Punkte">
                    <input v-model="assignmentForm.due_after_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Faellig nach Tagen">
                </div>
                <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                    <input v-model="assignmentForm.is_required" type="checkbox" class="rounded border-border bg-inputBg">
                    Pflichtaufgabe
                </label>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Aufgabe speichern</button>
            </form>
        </div>
    </article>
</template>
