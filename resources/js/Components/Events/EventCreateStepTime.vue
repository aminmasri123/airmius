<script setup>
defineProps({
    form: { type: Object, required: true },
})
</script>

<template>
    <section class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-primary">
                Zeit
            </h3>

            <p class="mt-1 text-sm text-secondary">
                Wann findet das Event statt?
            </p>
        </div>

        <div>
            <label for="event-start" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.start') }}
            </label>

            <input
                id="event-start"
                v-model="form.start_time"
                class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                type="datetime-local"
                required
            />

            <p class="mt-1 text-xs text-secondary">
                Zeitzone: {{ form.event_timezone }}
            </p>

            <div v-if="form.errors.start_time" class="mt-1 text-sm text-error">
                {{ form.errors.start_time }}
            </div>
        </div>

        <div>
            <label for="event-end" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.end') }}
            </label>

            <input
                id="event-end"
                v-model="form.end_time"
                class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                type="datetime-local"
            />

            <div v-if="form.errors.end_time" class="mt-1 text-sm text-error">
                {{ form.errors.end_time }}
            </div>
        </div>

        <div>
            <label for="event-reminder" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.reminder') }}
            </label>

            <input
                id="event-reminder"
                v-model="form.reminder_at"
                class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                type="datetime-local"
            />

            <div v-if="form.errors.reminder_at" class="mt-1 text-sm text-error">
                {{ form.errors.reminder_at }}
            </div>
        </div>
    </section>
</template>
