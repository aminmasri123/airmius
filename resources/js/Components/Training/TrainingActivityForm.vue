<script setup>
defineProps({
    form: { type: Object, required: true },
    setActivityImageElement: { type: Function, required: true },
    sports: { type: Array, default: () => [] },
})

const emit = defineEmits(['set-image', 'submit'])
</script>

<template>
    <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="emit('submit')">
        <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Name') }}
            <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
        </label>
        <label class="block text-sm font-semibold text-primary">Sportart
            <select v-model="form.activity_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option v-for="sport in sports.filter((item) => item.key !== 'all')" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">Datum und Zeit
            <input v-model="form.started_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
        </label>
        <label class="block text-sm font-semibold text-primary">Dauer in Minuten
            <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Distanz in km
            <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Kalorien
            <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
        </label>
        <label class="block text-sm font-semibold text-primary">Bild
            <input :ref="setActivityImageElement" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-image', $event)" />
        </label>
        <button type="submit" class="md:col-span-2 rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            Einheit speichern
        </button>
    </form>
</template>
