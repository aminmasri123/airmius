<script setup>
defineProps({
    athleteOptions: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    selectedItem: { type: Object, default: null },
})

const emit = defineEmits(['submit'])
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="emit('submit')">
        <p class="text-sm text-secondary">
            Markiere <span class="font-semibold text-primary">{{ selectedItem?.title }}</span> als nicht gemacht und dokumentiere kurz warum.
        </p>
        <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
            <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">Grund
            <select v-model="form.reason" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="krank">Krank</option>
                <option value="verletzt">Verletzt</option>
                <option value="keine_zeit">Keine Zeit</option>
                <option value="verschoben">Verschoben</option>
                <option value="bewusst_ausgelassen">Bewusst ausgelassen</option>
                <option value="anderes">Anderes</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">Notiz
            <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Optional: kurze Einordnung für dich oder den Trainer" />
        </label>
        <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            Ausfall speichern
        </button>
    </form>
</template>

