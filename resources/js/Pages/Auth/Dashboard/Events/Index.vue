<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    events: Array,
    clubs: Array,
    teams: Array,
    eventTypes: Array,
    visibilities: Array,
})

const form = useForm({
    club_id: props.clubs?.[0]?.id || '',
    team_id: '',
    title: '',
    type: 'training',
    visibility: 'private',
    start_time: '',
    end_time: '',
    location: '',
    notes: '',
    recurring: '',
    recurrence_ends_at: '',
    reminder_at: '',
})

const submit = () => {
    form.post(route('auth.events.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'end_time', 'location', 'notes', 'recurring', 'recurrence_ends_at', 'reminder_at'),
    })
}
</script>

<template>
    <Head title="Events" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Events</h1>
            <p class="mt-1 text-sm text-secondary">Training, matches, internal meetings, and public events.</p>
        </div>

        <form class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-3" @submit.prevent="submit">
            <input v-model="form.title" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Title" required />
            <select v-model="form.type" class="rounded-lg border-border bg-inputBg text-primary">
                <option v-for="type in eventTypes" :key="type" :value="type">{{ type }}</option>
            </select>
            <select v-model="form.visibility" class="rounded-lg border-border bg-inputBg text-primary">
                <option v-for="visibility in visibilities" :key="visibility" :value="visibility">{{ visibility }}</option>
            </select>
            <select v-model="form.club_id" class="rounded-lg border-border bg-inputBg text-primary">
                <option value="">No organization</option>
                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
            </select>
            <select v-model="form.team_id" class="rounded-lg border-border bg-inputBg text-primary">
                <option value="">No team</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
            <input v-model="form.location" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Location" />
            <input v-model="form.start_time" class="rounded-lg border-border bg-inputBg text-primary" type="datetime-local" required />
            <input v-model="form.end_time" class="rounded-lg border-border bg-inputBg text-primary" type="datetime-local" />
            <input v-model="form.reminder_at" class="rounded-lg border-border bg-inputBg text-primary" type="datetime-local" />
            <input v-model="form.recurring" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Recurring: weekly, monthly..." />
            <input v-model="form.recurrence_ends_at" class="rounded-lg border-border bg-inputBg text-primary" type="datetime-local" />
            <textarea v-model="form.notes" class="rounded-lg border-border bg-inputBg text-primary md:col-span-2" placeholder="Notes" />
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" :disabled="form.processing">
                Create event
            </button>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="event in events"
                :key="event.id"
                :href="route('auth.events.show', event.id)"
                class="rounded-lg border border-border bg-card p-4 transition hover:border-borderHover"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-primary">{{ event.title }}</h2>
                        <p class="text-sm text-secondary">{{ event.type }} · {{ event.visibility }}</p>
                    </div>
                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs text-secondary">{{ event.participants_count }} RSVP</span>
                </div>
                <p class="mt-3 text-sm text-secondary">{{ new Date(event.start_time).toLocaleString() }}</p>
                <p class="mt-1 text-sm text-secondary">{{ event.team?.name || event.club?.name || 'Public' }}</p>
                <p v-if="event.recurring" class="mt-2 text-xs text-secondary">Recurring: {{ event.recurring }}</p>
            </Link>
        </div>
    </div>
</template>
