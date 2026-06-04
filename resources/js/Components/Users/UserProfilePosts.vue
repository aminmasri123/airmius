<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    posts: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
})
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-primary">Aktuelle Beiträge</h2>
                <p class="mt-1 text-sm text-secondary">Die letzten sichtbaren Aktivitäten dieses Profils.</p>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            <article v-for="post in posts" :key="post.id" class="rounded-xl border border-border bg-bg p-4">
                <div class="flex flex-wrap items-center gap-2 text-xs text-secondary">
                    <Link v-if="post.team" :href="route('auth.teams.show', post.team.id)" class="font-semibold text-primary hover:underline">{{ post.team.name }}</Link>
                    <Link v-else-if="post.club" :href="route('auth.clubs.show', post.club.id)" class="font-semibold text-primary hover:underline">{{ post.club.name }}</Link>
                    <span v-else class="font-semibold text-primary">Public</span>
                    <span>- {{ formatDate(post.created_at) }}</span>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                <div class="mt-3 flex gap-4 text-xs text-secondary">
                    <span>{{ post.likes_count }} Likes</span>
                    <span>{{ post.comments_count }} Kommentare</span>
                </div>
            </article>
            <p v-if="!posts.length" class="rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                Keine sichtbaren Beiträge vorhanden.
            </p>
        </div>
    </section>
</template>




