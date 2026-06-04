<script setup>
defineProps({
    clubs: { type: Array, default: () => [] },
    selectedClubId: { type: [Number, String], default: null },
})

defineEmits(['update:selectedClubId'])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vereinsverwaltung</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Mitglieder & Beiträge</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                    Mitglieder pflegen, Anfragen prüfen, Beiträge abrechnen und Zahlungen abgleichen.
                </p>
            </div>

            <label v-if="clubs.length" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">Aktiver Verein</span>
                <select
                    :value="selectedClubId"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary"
                    @change="$emit('update:selectedClubId', Number($event.target.value) || $event.target.value)"
                >
                    <option v-for="club in clubs" :key="club.id" :value="club.id">
                        {{ club.name }}
                    </option>
                </select>
            </label>
        </div>
    </section>
</template>

