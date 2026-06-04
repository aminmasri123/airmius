<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    event: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
    typeLabels: { type: Object, required: true },
    visibilityLabels: { type: Object, required: true },
    eventStatusLabels: { type: Object, required: true },
})

defineEmits(['edit', 'cancel', 'delete'])
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <Link :href="route('auth.events.index')" class="text-sm font-semibold text-air-blue hover:underline">
                Zurück zu Events
            </Link>
            <h1 class="mt-2 text-3xl font-bold text-primary">{{ event.title }}</h1>
            <p class="mt-2">
                <span
                    class="rounded-full px-2 py-1 text-xs font-semibold"
                    :class="event.status === 'cancelled' ? 'bg-error/10 text-error' : 'bg-success/10 text-success'"
                >
                    {{ eventStatusLabels[event.status || 'scheduled'] || event.status }}
                </span>
            </p>
            <p class="mt-2 text-sm text-secondary">
                {{ typeLabels[event.type] || event.type }} - {{ visibilityLabels[event.visibility] || event.visibility }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                v-if="event.conversation_id || event.team_id"
                :href="route('auth.events.chat', event.id)"
                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
            >
                <i class="las la-comments mr-1"></i>
                Teamchat
            </Link>
            <button
                v-if="can.update"
                type="button"
                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                @click="$emit('edit')"
            >
                <i class="las la-edit mr-1"></i>
                Event bearbeiten
            </button>
            <button
                v-if="can.cancel && event.status !== 'cancelled'"
                type="button"
                class="rounded-lg border border-warning/40 px-4 py-2 text-sm font-semibold text-warning hover:bg-warning/10"
                @click="$emit('cancel')"
            >
                <i class="las la-calendar-times mr-1"></i>
                Event absagen
            </button>
            <button
                v-if="can.delete"
                type="button"
                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                @click="$emit('delete')"
            >
                <i class="las la-trash mr-1"></i>
                Löschen
            </button>
        </div>
    </div>
</template>

