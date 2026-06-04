<script setup>
defineProps({
    event: { type: Object, required: true },
    formatDateTime: { type: Function, required: true },
})
</script>

<template>
    <div v-if="event.status === 'cancelled'" class="rounded-lg border border-error/40 bg-error/10 p-4 text-error lg:col-span-2">
        <p class="font-semibold">Dieses Event wurde abgesagt.</p>
        <p v-if="event.cancellation_reason" class="mt-1 text-sm">{{ event.cancellation_reason }}</p>
        <p v-if="event.cancelled_by" class="mt-1 text-xs text-secondary">
            Abgesagt von {{ event.cancelled_by.name }}{{ event.cancelled_at ? ` am ${formatDateTime(event.cancelled_at)}` : '' }}
        </p>
    </div>
</template>
