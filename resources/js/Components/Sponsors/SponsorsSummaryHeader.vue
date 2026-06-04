<script setup>
import { computed } from 'vue'

const props = defineProps({
    sponsorStats: {
        type: Object,
        required: true,
    },
})

defineEmits(['create'])

const statCards = computed(() => [
    { key: 'total', label: 'Gesamt', value: props.sponsorStats.total },
    { key: 'platform', label: 'Airmius', value: props.sponsorStats.platform },
    { key: 'outfit_subscription', label: 'Outfit-Abo', value: props.sponsorStats.outfit_subscription },
    { key: 'club', label: 'Vereine', value: props.sponsorStats.club },
])
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Sponsoring</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Sponsoren verwalten</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Lege Sponsoren für Airmius, Outfit-Abos oder einzelne Vereine an und verwalte Logos, Laufzeiten und Ansprechpartner.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary transition hover:bg-primary/90"
                @click="$emit('create')"
            >
                <i class="las la-plus text-lg"></i>
                Sponsor anlegen
            </button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in statCards"
                :key="card.key"
                class="rounded-lg border border-border bg-inputBg p-4"
            >
                <p class="text-xs font-semibold uppercase text-secondary">{{ card.label }}</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ card.value }}</p>
            </div>
        </div>
    </section>
</template>

