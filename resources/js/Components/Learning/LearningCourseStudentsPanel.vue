<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    enrollmentForm: { type: Object, required: true },
})

defineEmits(['grantEnrollment', 'revokeEnrollment'])
</script>

<template>
    <article class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Teilnehmer und Betreuung</h2>
            <p class="mt-1 text-sm text-secondary">Einschreibungen, Fortschritt, manuelle Freischaltung und CSV-Reporting.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="$emit('grantEnrollment')">
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
                <button class="rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="$emit('revokeEnrollment', enrollment)">Deaktivieren</button>
            </div>
            <p v-if="!selectedCourse.enrollments?.length" class="p-5 text-sm text-secondary">Noch keine Teilnehmer eingeschrieben.</p>
        </div>
    </article>
</template>

