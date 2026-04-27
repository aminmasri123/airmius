<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

const props = defineProps({
    profileUser: Object,
    viewer: Object,
})

const follow = () => {
    router.post(route('auth.users.follow', props.profileUser.id), {}, { preserveScroll: true })
}

const unfollow = () => {
    router.delete(route('auth.users.unfollow', props.profileUser.id), { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <Head :title="profileUser.name" />

        <div class="mx-auto max-w-4xl space-y-6">
            <section class="border-b border-border pb-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <img
                            :src="profileUser.profile_photo_url"
                            :alt="profileUser.name"
                            class="size-20 rounded-full object-cover"
                        />
                        <div>
                            <h1 class="text-2xl font-semibold text-primary">{{ profileUser.name }}</h1>
                            <p v-if="profileUser.email" class="text-sm text-secondary">{{ profileUser.email }}</p>
                            <p class="mt-1 text-xs uppercase tracking-wide text-secondary">
                                {{ profileUser.profile_visibility === 'private' ? 'Privates Profil' : 'Öffentliches Profil' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link
                            v-if="viewer.is_self"
                            :href="route('profile.show')"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover"
                        >
                            Profil bearbeiten
                        </Link>
                        <button
                            v-else-if="viewer.can_follow && !viewer.is_following"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="follow"
                        >
                            Folgen
                        </button>
                        <button
                            v-else-if="viewer.can_follow"
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover"
                            @click="unfollow"
                        >
                            Entfolgen
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="viewer.can_view_private_profile" class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.followers_count }}</div>
                    <div class="text-sm text-secondary">Follower</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.following_count }}</div>
                    <div class="text-sm text-secondary">Folgt</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.roles?.length || 0 }}</div>
                    <div class="text-sm text-secondary">Rollen sichtbar</div>
                </div>
            </section>

            <section class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Profil</h2>
                <p v-if="viewer.can_view_private_profile && profileUser.bio" class="mt-3 whitespace-pre-line text-sm text-primary">
                    {{ profileUser.bio }}
                </p>
                <p v-else-if="viewer.can_view_private_profile" class="mt-3 text-sm text-secondary">
                    Dieses Profil hat noch keine Bio.
                </p>
                <p v-else class="mt-3 text-sm text-secondary">
                    Dieses Profil ist privat.
                </p>
            </section>

            <section v-if="viewer.can_manage_roles" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Rollen & Berechtigungen</h2>
                <div class="mt-3 space-y-3 text-sm">
                    <div>
                        <div class="font-medium text-primary">Rollen</div>
                        <div class="mt-1 text-secondary">{{ profileUser.roles.length ? profileUser.roles.join(', ') : 'Keine Rollen' }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-primary">Berechtigungen</div>
                        <div class="mt-1 max-h-32 overflow-auto text-secondary">
                            {{ profileUser.permissions.length ? profileUser.permissions.join(', ') : 'Keine Berechtigungen' }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
