<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    clubs: { type: Array, default: () => [] },
    eventTypes: { type: Array, default: () => [] },
    filteredTeams: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    freeEventLimitMessage: { type: String, default: '' },
    typeLabels: { type: Object, default: () => ({}) },
    visibilities: { type: Array, default: () => [] },
    visibilityLabels: { type: Object, default: () => ({}) },
})
</script>

<template>
    <section class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-primary">
                Basisdaten
            </h3>

            <p class="mt-1 text-sm text-secondary">
                Was für ein Event möchtest du erstellen?
            </p>
        </div>

        <div v-if="form.errors.authorization" class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold">
                    {{ form.errors.authorization }}
                </p>
                <Link :href="route('guest.pricing')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-center text-sm font-semibold text-buttonTextPrimary">
                    Upgrade ansehen
                </Link>
            </div>
        </div>

        <div v-else-if="freeEventLimitMessage" class="rounded-lg border border-air-blue/40 bg-air-blue/10 p-3 text-sm text-air-blue">
            <p class="font-semibold">{{ freeEventLimitMessage }}</p>
        </div>

        <div>
            <label for="event-title" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.title') }}
            </label>

            <input
                id="event-title"
                v-model="form.title"
                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                :placeholder="$t('events.placeholders.title')"
                required
            />

            <div v-if="form.errors.title" class="mt-1 text-sm text-error">
                {{ form.errors.title }}
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label for="event-type" class="block text-sm font-semibold text-primary">
                    {{ $t('events.fields.type') }}
                </label>

                <select
                    id="event-type"
                    v-model="form.type"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                >
                    <option v-for="type in eventTypes" :key="type" :value="type">
                        {{ $t(typeLabels[type] || type) }}
                    </option>
                </select>
            </div>

            <div>
                <label for="event-visibility" class="block text-sm font-semibold text-primary">
                    {{ $t('events.fields.visibility') }}
                </label>

                <select
                    id="event-visibility"
                    v-model="form.visibility"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                >
                    <option v-for="visibility in visibilities" :key="visibility" :value="visibility">
                        {{ $t(visibilityLabels[visibility] || visibility) }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="form.visibility !== 'public'" class="grid grid-cols-1 gap-3">
            <div v-if="form.visibility === 'organization'">
                <label for="event-club" class="block text-sm font-semibold text-primary">
                    {{ $t('events.fields.club') }}
                </label>

                <select
                    id="event-club"
                    v-model="form.club_id"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                >
                    <option value="">
                        {{ $t('events.none.club') }}
                    </option>

                    <option v-for="club in clubs" :key="club.id" :value="club.id">
                        {{ club.name }}
                    </option>
                </select>
            </div>

            <div v-if="form.visibility === 'private'">
                <label for="event-team" class="block text-sm font-semibold text-primary">
                    {{ $t('events.fields.team') }}
                </label>

                <select
                    id="event-team"
                    v-model="form.team_id"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                >
                    <option value="">
                        {{ $t('events.none.team') }}
                    </option>

                    <option v-for="team in filteredTeams" :key="team.id" :value="team.id">
                        {{ team.name }}
                    </option>
                </select>

                <p v-if="form.visibility === 'private'" class="mt-1 text-xs text-secondary">
                    {{ $t('events.private_requires_team') }}
                </p>

                <div v-if="form.errors.club_id" class="mt-1 text-sm text-error">
                    {{ form.errors.club_id }}
                </div>

                <div v-if="form.errors.team_id" class="mt-1 text-sm text-error">
                    {{ form.errors.team_id }}
                </div>
            </div>
        </div>
    </section>
</template>

