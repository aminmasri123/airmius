<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    courses: { type: Array, default: () => [] },
    selectedCourse: { type: Object, default: null },
    newCourseForm: { type: Object, required: true },
    courseCategories: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    statusLabel: { type: Function, required: true },
})

defineEmits(['create'])
</script>

<template>
    <aside class="space-y-4">
        <article class="surface-card p-4">
            <h2 class="text-base font-semibold text-primary">Neuen Kurs planen</h2>
            <form class="mt-4 grid gap-3" @submit.prevent="$emit('create')">
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
</template>

