<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    clubProfile: { type: Object, required: true },
    clubRoleLabel: { type: Function, required: true },
    clubRoles: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    memberRoles: { type: Function, required: true },
    viewer: { type: Object, required: true },
})

defineEmits(['toggle-member-role', 'update-member-role'])
</script>

<template>
    <aside class="space-y-4">
        <section class="rounded-lg border border-border bg-card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ $t('Teams') }}</h2>
            <div class="mt-4 space-y-2">
                <Link
                    v-for="team in clubProfile.teams"
                    :key="team.id"
                    :href="route('auth.teams.show', team.id)"
                    class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg"
                >
                    <div class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                        {{ initials(team.name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-primary">{{ team.name }}</p>
                        <p class="text-xs text-secondary">{{ team.users_count }} {{ $t('Mitglieder') }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ $t('Admins') }}</h2>
            <div class="mt-4 space-y-2">
                <Link
                    v-for="admin in clubProfile.admins"
                    :key="admin.id"
                    :href="route('auth.users.show', admin.id)"
                    class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg"
                >
                    <img v-if="admin.profile_photo_thumb" :src="admin.profile_photo_thumb" :alt="admin.name" width="32" height="32" loading="lazy" decoding="async" class="h-8 w-8 rounded-full object-cover" />
                    <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                        {{ initials(admin?.name) }}
                    </div>
                    <span class="min-w-0 truncate text-sm font-medium text-primary">{{ admin.name }}</span>
                </Link>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ $t('Mitglieder') }}</h2>
            <div class="mt-4 space-y-2">
                <div
                    v-for="member in clubProfile.members"
                    :key="member.id"
                    class="flex flex-col gap-3 rounded-lg border border-transparent p-3 hover:border-border hover:bg-inputBg"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <Link :href="route('auth.users.show', member.id)" class="flex min-w-0 items-center gap-3 hover:underline">
                            <img v-if="member.profile_photo_thumb" :src="member.profile_photo_thumb" :alt="member.name" width="32" height="32" loading="lazy" decoding="async" class="h-8 w-8 rounded-full object-cover" />
                            <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                {{ initials(member?.name) }}
                            </div>
                            <span class="truncate text-sm font-medium text-primary">{{ member.name }}</span>
                        </Link>
                        <p v-if="!viewer.can_manage" class="ml-auto shrink-0 text-right text-xs text-secondary">
                            {{ memberRoles(member).map(clubRoleLabel).join(', ') }}
                        </p>
                    </div>

                    <div v-if="viewer.can_manage" class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="role in clubRoles"
                            :key="role"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                            :class="memberRoles(member).includes(role)
                                ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary'"
                            :aria-pressed="memberRoles(member).includes(role)"
                            @click="$emit('toggle-member-role', member, role)"
                        >
                            {{ clubRoleLabel(role) }}
                        </button>

                        <button type="button" class="ml-auto rounded bg-buttonPrimary px-3 py-1.5 text-xs font-semibold text-buttonTextPrimary" @click="$emit('update-member-role', member)">
                            {{ $t('Speichern') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</template>
