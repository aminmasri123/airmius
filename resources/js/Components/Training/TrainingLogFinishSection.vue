<script setup>
defineProps({
    form: { type: Object, required: true },
})

defineEmits(['previous'])
</script>

<template>
    <section class="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-4 sm:p-5 2xl:col-start-1 2xl:row-start-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Schritt 3</p>
            <h2 class="mt-1 text-xl font-semibold text-primary">Training abschließen</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Diese Werte sind bewusst am Ende. Du musst nur eintragen, was du wirklich weißt.
            </p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-semibold text-primary">Intensität
                <select v-model="form.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="">Keine Angabe</option>
                    <option value="locker">Leicht / locker</option>
                    <option value="mittel">Mittel</option>
                    <option value="hart">Hart / intensiv</option>
                    <option value="recovery">Regeneration</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Verbrannte Kalorien
                <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Optional" />
            </label>
            <div class="rounded-xl border border-border bg-inputBg/40 p-3 md:col-span-2">
                <p class="text-sm font-semibold text-primary">Körpergefühl optional</p>
                <p class="mt-1 text-xs leading-5 text-secondary">
                    Nur ausfüllen, wenn du dein Befinden dokumentieren willst. 1 bedeutet niedrig, 10 bedeutet hoch.
                </p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block text-sm font-semibold text-primary">Anstrengung 1-10
                        <input v-model="form.wellness.rpe" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Energie 1-10
                        <input v-model="form.wellness.energy" type="number" min="1" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Schmerzen 0-10
                        <input v-model="form.wellness.pain" type="number" min="0" max="10" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">Schlaf in Stunden
                        <input v-model="form.wellness.sleep_hours" type="number" min="0" max="24" step="0.5" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
                    </label>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap justify-between gap-2">
            <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted" @click="$emit('previous')">
                Zurück zur Doku
            </button>
            <button type="submit" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                Training speichern
            </button>
        </div>
    </section>
</template>


