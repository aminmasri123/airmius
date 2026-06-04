<script setup>
defineProps({
    queue: {
        type: Object,
        default: () => ({}),
    },
    recentFailedJobs: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <div class="surface-card p-5">
        <h2 class="text-lg font-semibold text-primary">Queue & Fehlerjobs</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Offene Jobs</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ queue.pending_jobs || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs font-semibold uppercase text-secondary">Fehlerjobs</p>
                <p class="mt-2 text-2xl font-bold text-rose-300">{{ queue.failed_jobs || 0 }}</p>
            </div>
        </div>
        <div class="mt-4 space-y-3">
            <div v-if="!recentFailedJobs.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                Keine aktuellen Fehlerjobs gefunden.
            </div>
            <div v-for="job in recentFailedJobs" :key="job.id" class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-4">
                <div class="flex items-center justify-between gap-3 text-xs text-secondary">
                    <span>Job #{{ job.id }} · {{ job.queue }}</span>
                    <span>{{ job.failed_at }}</span>
                </div>
                <p class="mt-2 text-sm text-rose-200">{{ job.error }}</p>
            </div>
        </div>
    </div>
</template>

