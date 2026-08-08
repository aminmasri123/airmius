<script setup>
defineProps({
    exerciseLibrary: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
    imageLabel: {
        type: String,
        default: 'Bild',
    },
    sport: {
        type: Object,
        default: () => ({ metrics: [] }),
    },
    sportLabel: {
        type: Function,
        default: (value) => value,
    },
    sports: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        default: 'Speichern',
    },
})

const emit = defineEmits(['apply-template', 'set-image', 'submit'])
</script>

<template>
    <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="emit('submit')">
        <div class="md:col-span-2 rounded-2xl border border-border bg-inputBg/40 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Übungsbibliothek</p>
            <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="template in exerciseLibrary"
                    :key="template.title"
                    type="button"
                    class="shrink-0 rounded-xl border border-border px-3 py-2 text-left text-xs text-primary hover:bg-muted"
                    @click="emit('apply-template', template)"
                >
                    <span class="block font-semibold">{{ template.title }}</span>
                    <span class="text-secondary">{{ sportLabel(template.sport_type) }} · {{ template.focus }}</span>
                </button>
            </div>
        </div>
        <label class="block text-sm font-semibold text-primary">Sportart
            <select v-model="form.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option v-for="sportOption in sports.filter((item) => item.key !== 'all')" :key="sportOption.key" :value="sportOption.key">{{ sportOption.label }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">Titel
            <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
        </label>
        <label v-for="metric in sport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
            {{ metric }}
            <input v-model="form.metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Termin
            <input v-model="form.scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Woche
            <input v-model="form.week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Dauer
            <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Distanz km
            <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Kalorien
            <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Belastung
            <select v-model="form.load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="low">Locker</option>
                <option value="medium">Mittel</option>
                <option value="high">Hoch</option>
                <option value="test">Test</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">Fokus
            <input v-model="form.focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Beschreibung') }}
            <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary md:col-span-2">Todo-Liste
            <textarea v-model="form.todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Eine Aufgabe pro Zeile" />
        </label>
        <label class="block text-sm font-semibold text-primary">Video-Link
            <input v-model="form.video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ imageLabel }}
            <input type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-image', $event)" />
        </label>
        <button type="submit" class="md:col-span-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            {{ submitLabel }}
        </button>
    </form>
</template>

