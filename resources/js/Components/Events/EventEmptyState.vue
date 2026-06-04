<script setup>
defineProps({
    eventItems: { type: Array, default: () => [] },
    calendarEventItems: { type: Array, default: () => [] },
    resetFilters: { type: Function, required: true },
    openCreateModal: { type: Function, required: true },
})
</script>

<template>
    <div v-if="!eventItems.length && !calendarEventItems.length" class="rounded-lg border border-dashed border-border bg-card p-10 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg text-secondary">
            <i class="las la-calendar-times text-2xl"></i>
        </div>
        <h2 class="mt-4 text-lg font-semibold text-primary">Keine Events gefunden</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-secondary">
            Es gibt aktuell keine passenden Events. Passe die Filter an oder erstelle ein neues Event.
        </p>
        <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="resetFilters">
                Filter zurücksetzen
            </button>
            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="openCreateModal">
                Event erstellen
            </button>
        </div>
    </div>
</template>

