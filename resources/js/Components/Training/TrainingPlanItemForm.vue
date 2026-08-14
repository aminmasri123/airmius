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
    trainingSessionBlocks: {
        type: Array,
        default: () => [],
    },
    trainingGoals: {
        type: Array,
        default: () => [],
    },
    equipmentPresets: {
        type: Array,
        default: () => [],
    },
    sportRoutes: {
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
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('Übungsbibliothek') }}</p>
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
        <label class="block text-sm font-semibold text-primary">{{ $t('Sportart') }}
            <select v-model="form.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option v-for="sportOption in sports.filter((item) => item.key !== 'all')" :key="sportOption.key" :value="sportOption.key">{{ sportOption.label }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Titel') }}
            <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
        </label>
        <div class="md:col-span-2 rounded-2xl border border-border bg-inputBg/40 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('Trainingsstruktur') }}</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <label class="block text-sm font-semibold text-primary">{{ $t('Abschnitt') }}
                    <select v-model="form.session_block" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                        <option v-for="block in trainingSessionBlocks" :key="block.key" :value="block.key">{{ block.label }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ $t('Trainingsziel') }}
                    <select v-model="form.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                        <option v-for="goal in trainingGoals" :key="goal.key" :value="goal.label">{{ goal.label }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ $t('Niveau') }}
                    <select v-model="form.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                        <option value="beginner">{{ $t('Einsteiger') }}</option>
                        <option value="intermediate">{{ $t('Fortgeschritten') }}</option>
                        <option value="advanced">{{ $t('Advanced') }}</option>
                        <option value="elite">{{ $t('Leistung') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ $t('Equipment') }}
                    <input v-model="form.equipment" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Ball, Hütchen, Matte oder kein Equipment" />
                </label>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="equipment in equipmentPresets"
                    :key="equipment"
                    type="button"
                    class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold text-secondary hover:bg-muted"
                    @click="form.equipment = equipment"
                >
                    {{ equipment }}
                </button>
            </div>
        </div>
        <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('training_workspace.route_link.label') }}
            <select v-model="form.sport_route_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="">{{ $t('training_workspace.route_link.none') }}</option>
                <option v-for="sportRoute in sportRoutes" :key="sportRoute.id" :value="sportRoute.id">
                    {{ sportRoute.title }} · {{ ((Number(sportRoute.distance_meters || 0) / 1000).toFixed(1)) }} km
                </option>
            </select>
            <span class="mt-1 block text-xs font-normal text-secondary">{{ $t('training_workspace.route_link.plan_hint') }}</span>
        </label>
        <label v-for="metric in sport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
            {{ metric }}
            <input v-model="form.metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Termin') }}
            <input v-model="form.scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Woche') }}
            <input v-model="form.week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Dauer') }}
            <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Distanz km') }}
            <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Kalorien') }}
            <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Belastung') }}
            <select v-model="form.load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="low">{{ $t('training_workspace.load.low') }}</option>
                <option value="medium">{{ $t('training_workspace.load.medium') }}</option>
                <option value="high">{{ $t('training_workspace.load.high') }}</option>
                <option value="test">{{ $t('training_workspace.load.test') }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Fokus') }}
            <input v-model="form.focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Beschreibung') }}
            <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Todo-Liste') }}
            <textarea v-model="form.todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="$t('Eine Aufgabe pro Zeile')" />
        </label>
        <label class="block text-sm font-semibold text-primary">{{ $t('Video-Link') }}
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
