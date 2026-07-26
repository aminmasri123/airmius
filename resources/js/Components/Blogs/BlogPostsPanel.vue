<script setup>
import { Link } from '@inertiajs/vue3'

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

defineProps({
    posts: { type: Object, required: true },
    can: { type: Object, required: true },
    statusClasses: { type: Object, required: true },
    stripHtml: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    edit: { type: Function, required: true },
    destroyPost: { type: Function, required: true },
})
</script>

<template>
    <section class="space-y-4">
        <article
            v-for="post in posts.data"
            :key="post.id"
            class="surface-card overflow-hidden transition hover:border-air-blue/50"
        >
            <div class="grid gap-4 p-4 md:grid-cols-[190px_1fr]">
                <div class="flex h-40 items-center justify-center overflow-hidden rounded-lg bg-inputBg">
                    <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                    <i v-else class="las la-newspaper text-5xl text-secondary"></i>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="[statusClasses[post.status], 'rounded-full px-3 py-1 text-xs font-semibold']">
                            {{ post.status }}
                        </span>
                        <Link
                            v-if="post.category && can.manageCategories"
                            :href="route('blog-categories.index')"
                            class="rounded-full border border-border px-3 py-1 text-xs text-secondary hover:border-air-blue hover:text-air-blue"
                        >
                            {{ post.category }}
                        </Link>
                        <span v-else-if="post.category" class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                            {{ post.category }}
                        </span>
                        <span class="text-xs text-secondary">
                            {{ post.author?.name || 'Unbekannt' }}
                        </span>
                        <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                            SEO {{ post.seo_score || 0 }}%
                        </span>
                        <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                            {{ post.revisions_count || 0 }} Revisionen
                        </span>
                    </div>

                    <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                    <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-secondary">
                        {{ post.excerpt || stripHtml(post.content) }}
                    </p>
                    <p v-if="post.latest_revision" class="mt-2 text-xs text-secondary">
                        Letzte Sicherung: {{ formatDateTime(post.latest_revision.created_at) }} mit {{ post.latest_revision.seo_score }}% SEO
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="can.update"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="edit(post)"
                        >
                            Bearbeiten
                        </button>
                        <Link
                            v-if="post.status === 'published'"
                            :href="route('guest.blog.show', post.slug)"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        >
                            Anzeigen
                        </Link>
                        <Link
                            v-if="can.update"
                            :href="route('blogs.preview', post.id)"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        >
                            Vorschau
                        </Link>
                        <button
                            v-if="can.delete"
                            class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white"
                            @click="destroyPost(post)"
                        >
                            Löschen
                        </button>
                    </div>
                </div>
            </div>
        </article>

        <div v-if="!posts.data.length" class="surface-card p-8 text-center text-secondary">
            Noch keine Blogbeiträge vorhanden.
        </div>

        <div v-if="posts.links?.length > 3" class="flex flex-wrap gap-2">
            <Link
                v-for="link in posts.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-lg border border-border px-3 py-2 text-sm"
                :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted'"
            >
                {{ paginationLabel(link.label) }}
            </Link>
        </div>
    </section>
</template>
