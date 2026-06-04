<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    quizForm: { type: Object, required: true },
    allLessons: { type: Array, default: () => [] },
})

defineEmits(['createQuiz', 'deleteQuiz'])
</script>

<template>
    <article class="surface-card p-5">
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
                    <button type="button" class="mt-3 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="$emit('deleteQuiz', quiz)">
                        Quiz löschen
                    </button>
                </div>
                <p v-if="!selectedCourse.quizzes?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch kein Quiz angelegt.</p>
            </div>
            <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="$emit('createQuiz')">
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
                <textarea v-model="quizForm.explanation" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Erklärung nach Antwort"></textarea>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Quiz speichern</button>
            </form>
        </div>
    </article>
</template>

