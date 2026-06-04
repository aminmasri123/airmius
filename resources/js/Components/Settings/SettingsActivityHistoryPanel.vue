<script setup>
defineProps({
    activities: { type: Array, default: () => [] },
    activityDescription: { type: Function, required: true },
    activityLabel: { type: Function, required: true },
    activityScope: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    settingsText: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('activities.title', 'Meine Aktivitäten') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('activities.description', 'Hier erscheinen nur Aktionen, die von deinem eigenen Konto erstellt wurden.') }}
                </p>
            </div>
            <span class="text-sm font-semibold text-secondary">{{ settingsText('activities.count', '{count} Einträge', { count: activities.length }) }}</span>
        </div>

        <div v-if="activities.length" class="mt-5 divide-y divide-border rounded-lg border border-border bg-bg">
            <article
                v-for="activity in activities"
                :key="activity.id"
                class="flex gap-3 p-4"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                    <i class="las la-history text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-semibold text-primary">{{ activityLabel(activity.type) }}</p>
                        <time class="text-xs text-secondary">{{ formatDate(activity.created_at) }}</time>
                    </div>
                    <p class="mt-1 text-sm text-secondary">{{ activityScope(activity) }}</p>
                    <p v-if="activityDescription(activity)" class="mt-2 line-clamp-2 text-sm text-primary">
                        {{ activityDescription(activity) }}
                    </p>
                </div>
            </article>
        </div>

        <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
            {{ settingsText('activities.empty', 'Noch keine eigenen Aktivitäten vorhanden.') }}
        </div>
    </div>
</template>

