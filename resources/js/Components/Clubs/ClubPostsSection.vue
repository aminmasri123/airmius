<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    clubProfile: { type: Object, required: true },
    formatDate: { type: Function, required: true },
    posts: { type: Array, default: () => [] },
})
</script>

<template>
    <section class="space-y-4">
        <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
            <div class="flex items-center gap-3">
                <img :src="post.user.profile_photo_url" :alt="post.user.name" width="40" height="40" loading="lazy" decoding="async" class="h-10 w-10 rounded-full object-cover">
                <div>
                    <Link :href="route('auth.users.show', post.user.id)" class="text-sm font-semibold text-primary hover:underline">
                        {{ post.user.name }}
                    </Link>
                    <p class="text-xs text-secondary">{{ post.team?.name || clubProfile.name }} · {{ formatDate(post.created_at) }}</p>
                </div>
            </div>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
            <div class="mt-3 flex gap-4 text-xs text-secondary">
                <span>{{ post.likes_count }} Likes</span>
                <span>{{ post.comments_count }} Kommentare</span>
            </div>
        </article>
        <div v-if="!posts.length" class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
            Noch keine sichtbaren Beiträge.
        </div>
    </section>
</template>

