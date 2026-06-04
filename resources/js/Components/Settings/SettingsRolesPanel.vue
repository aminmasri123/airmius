<script setup>
defineProps({
    roleDescription: { type: Function, required: true },
    roleName: { type: Function, required: true },
    settingsText: { type: Function, required: true },
    userRoles: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="surface-card p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('roles.title', 'Meine Rollen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('roles.description', 'Hier siehst du, welche Plattform-Rollen deinem Konto aktuell zugeordnet sind.') }}
                </p>
            </div>
            <span class="text-sm font-semibold text-secondary">{{ settingsText('roles.count', '{count} Rollen', { count: userRoles.length }) }}</span>
        </div>

        <div v-if="userRoles.length" class="mt-5 grid gap-3 md:grid-cols-2">
            <article
                v-for="role in userRoles"
                :key="role.id"
                class="rounded-lg border border-border bg-bg p-4"
            >
                <div>
                    <div class="min-w-0">
                        <p class="break-words font-semibold text-primary">{{ roleName(role) }}</p>
                        <p class="mt-1 text-sm text-secondary">{{ roleDescription(role) }}</p>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
            {{ settingsText('roles.empty', 'Deinem Konto ist noch keine Rolle zugewiesen.') }}
        </div>
    </div>
</template>
