<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    post: { type: Object, required: true },
    user: { type: Object, default: null },
    editForm: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    initials: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    visibilityLabel: { type: Function, required: true },
})

const emit = defineEmits(['delete-post', 'report-post'])
</script>

<template>
    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 gap-3">
            <Link :href="route('auth.users.show', post.user.id)" class="shrink-0">
                <img
                    v-if="post.user?.profile_photo_thumb"
                    :src="post.user.profile_photo_thumb"
                    :alt="post.user.name"
                    class="h-10 w-10 rounded-full object-cover"
                >

                <div
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary"
                >
                    {{ initials(post.user?.name) }}
                </div>
            </Link>

            <div class="min-w-0">
                <Link
                    :href="route('auth.users.show', post.user.id)"
                    class="block truncate text-sm font-semibold text-primary hover:underline"
                >
                    {{ post.user?.name }}
                </Link>

                <p class="break-words text-xs text-secondary">
                    <Link
                        v-if="post.team"
                        :href="route('auth.teams.show', post.team.id)"
                        class="hover:text-primary hover:underline"
                    >
                        {{ post.team.name }}
                    </Link>

                    <Link
                        v-else-if="post.club"
                        :href="route('auth.clubs.show', post.club.id)"
                        class="hover:text-primary hover:underline"
                    >
                        {{ post.club.name }}
                    </Link>

                    <span v-else>Public</span>

                    · {{ visibilityLabel(post.visibility) }} · {{ formatDate(post.created_at) }}
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-1">
            <button
                v-if="post.user_id !== user?.id"
                class="rounded p-2 text-secondary hover:bg-muted hover:text-primary"
                title="Beitrag melden"
                @click="emit('report-post')"
            >
                <i class="las la-flag"></i>
            </button>

            <button
                v-if="canEdit"
                class="rounded p-2 text-secondary hover:bg-muted hover:text-primary"
                @click="editForm.editing = !editForm.editing"
            >
                <i class="las la-edit"></i>
            </button>

            <button
                v-if="canDelete"
                class="rounded p-2 text-secondary hover:bg-error/10 hover:text-error"
                @click="emit('delete-post')"
            >
                <i class="las la-trash"></i>
            </button>
        </div>
    </div>
</template>

