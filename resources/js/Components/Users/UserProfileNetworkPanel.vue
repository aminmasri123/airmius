<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    profileUser: { type: Object, required: true },
    initials: { type: Function, required: true },
})
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Teams</h2>
            <div class="mt-4 space-y-2">
                <Link v-for="team in profileUser.teams" :key="team.id" :href="route('auth.teams.show', team.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">{{ initials(team.name) }}</div>
                    <span class="min-w-0 truncate text-sm font-semibold text-primary">{{ team.name }}</span>
                </Link>
                <p v-if="!profileUser.teams.length" class="text-sm text-secondary">Keine Teams sichtbar.</p>
            </div>
        </section>

        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Vereine</h2>
            <div class="mt-4 space-y-2">
                <Link v-for="club in profileUser.clubs" :key="club.id" :href="route('auth.clubs.show', club.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-inputBg text-sm font-semibold text-primary">{{ initials(club.name) }}</div>
                    <span class="min-w-0 truncate text-sm font-semibold text-primary">{{ club.name }}</span>
                </Link>
                <p v-if="!profileUser.clubs.length" class="text-sm text-secondary">Keine Vereine sichtbar.</p>
            </div>
        </section>
    </div>
</template>
