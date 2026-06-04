<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    replyForms: { type: Object, required: true },
})

defineEmits(['submitQuestionReply', 'updateQuestionStatus'])
</script>

<template>
    <article class="surface-card overflow-hidden">
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
                    <form class="mt-3 grid gap-2" @submit.prevent="$emit('submitQuestionReply', question)">
                        <textarea v-model="replyForms[String(question.id)]" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Antwort für den Teilnehmer schreiben"></textarea>
                        <button class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-xs font-semibold text-buttonTextPrimary">
                            Antwort senden
                        </button>
                    </form>
                </div>
                <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                    <button class="rounded-lg border border-air-blue/40 px-3 py-2 text-xs font-semibold text-air-blue" @click="$emit('updateQuestionStatus', question, 'answered')">
                        Beantwortet
                    </button>
                    <button class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success" @click="$emit('updateQuestionStatus', question, 'resolved')">
                        Erledigt
                    </button>
                </div>
            </div>
            <p v-if="!selectedCourse.questions?.length" class="p-5 text-sm text-secondary">Noch keine Kursfragen vorhanden.</p>
        </div>
    </article>
</template>

