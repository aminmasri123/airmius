<script setup>
defineProps({
    courseStats: { type: Array, default: () => [] },
    flashSuccess: { type: String, default: '' },
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius Sportschule</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Kurs-Studio für Trainer und Tutoren</h1>
                <p class="mt-2 max-w-3xl text-sm text-secondary">
                    Plane echte Online-Kurse mit Kapiteln, Lektionen, Aufgaben, Anhängen, Quiz, Notizen und Kurskommunikation.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div v-for="[label, value] in courseStats" :key="label" class="rounded-lg border border-border bg-bg p-3">
                    <p class="text-xs uppercase text-secondary">{{ label }}</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ value }}</p>
                </div>
            </div>
        </div>
        <div v-if="flashSuccess" class="border-t border-success/30 bg-success/10 px-5 py-3 text-sm font-semibold text-success">
            {{ flashSuccess }}
        </div>
    </section>
</template>

