<script setup>
defineProps({
    privacy: { type: Object, default: () => ({}) },
})

const tone = (visible) => visible
    ? 'border-success/30 bg-success/10 text-success'
    : 'border-border bg-inputBg text-secondary'
</script>

<template>
    <section v-if="privacy?.sections?.length" class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-primary">Profil-Datenschutz</h2>
                <p class="mt-1 text-sm text-secondary">Sichtbarkeit je Sport-CV-Bereich, feldgenau für Bestwerte.</p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                {{ privacy.completion_percent || 0 }}% sichtbar
            </span>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <div
                v-for="section in privacy.sections"
                :key="section.key"
                class="rounded-lg border p-3"
                :class="tone(section.visible_to_viewer)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ section.label }}</p>
                        <p class="mt-1 text-xs opacity-80">{{ section.description }}</p>
                    </div>
                    <i :class="['las shrink-0 text-lg', section.visible_to_viewer ? 'la-eye' : 'la-lock']"></i>
                </div>
                <p class="mt-2 text-xs font-semibold uppercase opacity-80">{{ section.visibility }}</p>
            </div>
        </div>
    </section>
</template>

