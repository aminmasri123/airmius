<script setup>
defineProps({
    eventCreation: { type: Object, default: () => ({}) },
    form: { type: Object, required: true },
    recurrenceOptions: { type: Array, default: () => [] },
    recurrenceSummary: { type: String, default: '' },
    toggleWeekday: { type: Function, required: true },
    weekdayOptions: { type: Array, default: () => [] },
})
</script>

<template>
    <section class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-primary">
                Details & Wiederholung
            </h3>

            <p class="mt-1 text-sm text-secondary">
                Optional: Ort, Notizen und Wiederholung hinzufügen.
            </p>
        </div>

        <div v-if="eventCreation?.allows_recurring">
            <label for="event-recurring" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.recurrence') }}
            </label>

            <select
                id="event-recurring"
                v-model="form.recurring"
                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
            >
                <option v-for="option in recurrenceOptions" :key="option.value" :value="option.value">
                    {{ $t(option.label) }}
                </option>
            </select>
        </div>

        <div v-else class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
            <p class="font-semibold text-primary">Keine Intervalle im kostenlosen Konto</p>
            <p class="mt-1">Du kannst einfache Einzel-Events erstellen. Wiederholungen sind ab einem passenden Paket verfügbar.</p>
        </div>

        <div v-if="form.recurring">
            <label for="event-recurrence-end" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.recurrence_end') }}
            </label>

            <input
                id="event-recurrence-end"
                v-model="form.recurrence_ends_at"
                class="date-input mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary focus:border-borderHover focus:ring-borderHover"
                type="date"
            />

            <div v-if="form.errors.recurrence_ends_at" class="mt-1 text-sm text-error">
                {{ form.errors.recurrence_ends_at }}
            </div>
        </div>

        <div v-if="['weekly', 'biweekly'].includes(form.recurring)">
            <div class="mb-2 text-sm font-semibold text-primary">
                {{ $t('events.fields.recurrence_days') }}
            </div>

            <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                <button
                    v-for="day in weekdayOptions"
                    :key="day.value"
                    type="button"
                    class="rounded-lg border px-2 py-3 text-xs font-semibold transition"
                    :class="form.recurrence_days.map(Number).includes(day.value)
                        ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                        : 'border-border bg-inputBg text-primary hover:bg-muted'"
                    :title="$t(day.label)"
                    @click="toggleWeekday(day.value)"
                >
                    {{ $t(day.short) }}
                </button>
            </div>

            <div v-if="form.errors.recurrence_days" class="mt-1 text-sm text-error">
                {{ form.errors.recurrence_days }}
            </div>

            <p v-if="recurrenceSummary" class="mt-2 text-xs text-secondary">
                {{ recurrenceSummary }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="event-location-name" class="block text-sm font-semibold text-primary">Ort / Treffpunkt</label>
                <input
                    id="event-location-name"
                    v-model="form.location_name"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="z. B. Waldhaus, Sporthalle, Vereinsheim"
                />
            </div>

            <div>
                <label for="event-location-street" class="block text-sm font-semibold text-primary">Straße</label>
                <input
                    id="event-location-street"
                    v-model="form.location_street"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="Straße"
                />
            </div>

            <div>
                <label for="event-location-house-number" class="block text-sm font-semibold text-primary">Nr.</label>
                <input
                    id="event-location-house-number"
                    v-model="form.location_house_number"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="10"
                />
            </div>

            <div>
                <label for="event-location-postal-code" class="block text-sm font-semibold text-primary">PLZ</label>
                <input
                    id="event-location-postal-code"
                    v-model="form.location_postal_code"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="66119"
                />
            </div>

            <div>
                <label for="event-location-city" class="block text-sm font-semibold text-primary">Stadt</label>
                <input
                    id="event-location-city"
                    v-model="form.location_city"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="Saarbrücken"
                />
            </div>

            <div>
                <label for="event-location-country" class="block text-sm font-semibold text-primary">Land</label>
                <input
                    id="event-location-country"
                    v-model="form.location_country"
                    maxlength="2"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 uppercase text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    placeholder="DE"
                />
            </div>
        </div>

        <div>
            <label for="event-max-participants" class="block text-sm font-semibold text-primary">
                Maximale Teilnehmerzahl
            </label>

            <input
                id="event-max-participants"
                v-model="form.max_participants"
                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                type="number"
                min="1"
                max="100000"
                inputmode="numeric"
                placeholder="Leer lassen = unbegrenzt"
            />

            <p class="mt-1 text-xs text-secondary">
                Nur Zusagen zählen gegen diese Grenze. Vielleicht und Absagen bleiben möglich.
            </p>

            <div v-if="form.errors.max_participants" class="mt-1 text-sm text-error">
                {{ form.errors.max_participants }}
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-border bg-inputBg p-4 transition"
            :class="form.visibility === 'private' && form.team_id ? 'cursor-pointer hover:border-borderHover' : 'opacity-60'"
        >
            <input
                v-model="form.uses_penalty_catalog"
                type="checkbox"
                class="mt-1 rounded border-border bg-card text-buttonPrimary focus:ring-buttonPrimary"
                :disabled="form.visibility !== 'private' || !form.team_id"
            />

            <span>
                <span class="block text-sm font-semibold text-primary">Mit Strafkatalog arbeiten</span>
                <span class="mt-1 block text-xs leading-relaxed text-secondary">
                    Berechtigte Teamrollen können während dieses Events Strafen an anwesende oder verspätete Spieler vergeben.
                </span>
            </span>
        </label>

        <div>
            <label for="event-notes" class="block text-sm font-semibold text-primary">
                {{ $t('events.fields.notes') }}
            </label>

            <textarea
                id="event-notes"
                v-model="form.notes"
                rows="4"
                class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                :placeholder="$t('events.placeholders.notes')"
            />
        </div>
    </section>
</template>

