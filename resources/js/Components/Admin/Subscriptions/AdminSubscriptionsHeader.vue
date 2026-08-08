<script setup>
defineProps({
    flashMessage: { type: String, default: '' },
})
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Business Model</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">{{ $t('Abo-Verwaltung') }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-secondary">
                    Preise, Zielgruppen, Limits und Vereinszuordnungen zentral verwalten.
                </p>
            </div>

            <a
                :href="route('guest.pricing')"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
            >
                <i class="las la-external-link-alt"></i>
                Preisseite ansehen
            </a>
        </div>

        <div v-if="flashMessage" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ flashMessage }}
        </div>
    </section>
</template>
