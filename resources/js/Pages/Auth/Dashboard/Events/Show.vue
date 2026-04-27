<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    event: Object,
    participantStatuses: Array,
    currentParticipantStatus: String,
})

const commentForm = useForm({ content: '' })

const setStatus = (status) => {
    router.post(route('auth.events.join', props.event.id), { status }, { preserveScroll: true })
}

const submitComment = () => {
    commentForm.post(route('auth.events.comments.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    })
}
</script>

<template>
    <Head :title="event.title" />

    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-primary">{{ event.title }}</h1>
                    <p class="mt-1 text-sm text-secondary">{{ event.type }} · {{ event.visibility }}</p>
                    <p class="mt-3 text-sm text-primary">{{ new Date(event.start_time).toLocaleString() }}</p>
                    <p v-if="event.location" class="text-sm text-secondary">{{ event.location }}</p>
                </div>
                <Link
                    v-if="event.conversation_id"
                    :href="route('auth.events.chat', event.id)"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary"
                >
                    Event chat
                </Link>
            </div>
            <p v-if="event.notes" class="mt-4 whitespace-pre-line text-sm text-primary">{{ event.notes }}</p>
            <div class="mt-4 grid gap-2 text-sm text-secondary sm:grid-cols-3">
                <div>Recurring: {{ event.recurring || 'none' }}</div>
                <div>Reminder: {{ event.reminder_at ? new Date(event.reminder_at).toLocaleString() : 'none' }}</div>
                <div>Until: {{ event.recurrence_ends_at ? new Date(event.recurrence_ends_at).toLocaleDateString() : 'none' }}</div>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-5">
            <h2 class="font-semibold text-primary">Participation</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="status in participantStatuses"
                    :key="status"
                    class="rounded-lg border px-4 py-2 text-sm"
                    :class="currentParticipantStatus === status ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-primary'"
                    @click="setStatus(status)"
                >
                    {{ status }}
                </button>
            </div>
            <div class="mt-4 space-y-1 text-sm text-secondary">
                <div v-for="participant in event.participants" :key="participant.id">
                    {{ participant.name }}: {{ participant.pivot.status }}
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-5">
            <h2 class="font-semibold text-primary">Discussion</h2>
            <form class="mt-3 flex gap-2" @submit.prevent="submitComment">
                <input v-model="commentForm.content" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-primary" placeholder="Comment" required />
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" :disabled="commentForm.processing">Send</button>
            </form>
            <div class="mt-4 divide-y divide-border">
                <div v-for="comment in event.comments" :key="comment.id" class="py-3">
                    <div class="text-sm font-medium text-primary">{{ comment.user.name }}</div>
                    <p class="mt-1 text-sm text-secondary">{{ comment.content }}</p>
                </div>
                <div v-if="!event.comments.length" class="py-4 text-sm text-secondary">No comments yet.</div>
            </div>
        </section>
    </div>
</template>
