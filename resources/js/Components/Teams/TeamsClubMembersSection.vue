<script setup>
defineProps({
    club: { type: Object, required: true },
    clubRoles: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    clubRoleLabel: { type: Function, required: true },
    clubRoleList: { type: Function, required: true },
    updateClubMemberRole: { type: Function, required: true },
})
</script>

<template>
    <div class="rounded-xl border border-border bg-bg p-4">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-primary">
                    Vereinsmitglieder
                </h2>

                <p class="text-xs text-secondary">
                    Owner, Admins und Manager steuern die Rollen im Verein.
                </p>
            </div>

            <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                {{ club.users?.length || 0 }} Mitglieder
            </span>
        </div>

        <div class="grid gap-2 md:grid-cols-2">
            <div
                v-for="member in club.users"
                :key="member.id"
                class="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
            >
                <img
                    v-if="member.profile_photo_thumb"
                    :src="member.profile_photo_thumb"
                    :alt="member.name"
                    class="h-9 w-9 shrink-0 rounded-full object-cover"
                >

                <div
                    v-else
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-primary"
                >
                    {{ initials(member.name) }}
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-primary">
                        {{ member.name }}
                    </p>

                    <p class="truncate text-xs text-secondary">
                        {{ member.email }}
                    </p>
                </div>

                <select
                    v-if="club.can_manage"
                    v-model="member.pivot.role"
                    class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary"
                    @change="updateClubMemberRole(club, member)"
                >
                    <option
                        v-for="role in clubRoles"
                        :key="role"
                        :value="role"
                    >
                        {{ clubRoleLabel(role) }}
                    </option>
                </select>

                <span
                    v-else
                    class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                >
                    {{ clubRoleList(member).map(clubRoleLabel).join(', ') }}
                </span>
            </div>
        </div>
    </div>
</template>
