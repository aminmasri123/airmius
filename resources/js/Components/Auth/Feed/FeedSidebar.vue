<script setup>
import { Link } from '@inertiajs/vue3'
import AdSlot from '@/Components/Ads/AdSlot.vue'

defineProps({
    clubs: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
})
</script>

<template>
    <aside class="min-w-0 hidden space-y-4 md:block xl:sticky xl:top-24 xl:self-start">
        <AdSlot placement="feed" variant="card" :fallback="false" />

        <div class="surface-card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                {{ $t('Deine Vereine') }}
            </h2>

            <div class="mt-4 space-y-2">
                <div v-if="clubs && clubs.length > 0" class="space-y-2">
                    <Link
                        v-for="club in clubs"
                        :key="club.id"
                        :href="route('auth.clubs.show', club.id)"
                        class="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-inputBg p-3"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(club.name) }}
                        </div>

                        <span class="min-w-0 truncate text-sm font-medium text-primary">
                            {{ club.name }}
                        </span>
                    </Link>
                </div>

                <div v-else class="text-sm text-secondary">
                    {{ $t('teams.empty') }}
                </div>
            </div>
        </div>
    </aside>
</template>
