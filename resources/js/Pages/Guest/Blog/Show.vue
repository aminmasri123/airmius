<script setup>
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    post: Object,
})
</script>

<template>
    <SeoHead
        :title="post.meta_title || post.title"
        :description="post.meta_description || post.excerpt || 'Artikel aus dem Airmius Blog zu Sport, Training, Vereinen und digitaler Organisation.'"
        :image="post.cover_image || '/img/logo/Logo-Airmius-Quervormat.png'"
        type="article"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <article class="mx-auto max-w-4xl">
                <Link :href="route('guest.blog.index')" class="text-sm font-semibold text-air-blue hover:underline">
                    Zurück zum Blog
                </Link>

                <div class="mt-6 flex flex-wrap items-center gap-2 text-sm text-secondary">
                    <span v-if="post.category" class="rounded-full border border-border px-3 py-1">{{ post.category }}</span>
                    <span>{{ post.author?.name }}</span>
                </div>

                <h1 class="mt-4 font-heading text-4xl font-900 leading-tight sm:text-5xl">{{ post.title }}</h1>
                <p v-if="post.excerpt" class="mt-5 text-lg leading-relaxed text-secondary">{{ post.excerpt }}</p>

                <div v-if="post.cover_image" class="mt-8 overflow-hidden rounded-xl border border-border">
                    <img :src="post.cover_image" :alt="post.title" class="w-full object-cover" />
                </div>

                <div class="blog-content prose prose-invert mt-10 max-w-none text-primary" v-html="post.content"></div>
            </article>
        </main>

        <Footer />
    </div>
</template>

<style scoped>
.blog-content :deep(h2) {
    margin: 2rem 0 0.85rem;
    font-size: clamp(1.8rem, 3vw, 2.35rem);
    font-weight: 900;
    line-height: 1.15;
}

.blog-content :deep(h3) {
    margin: 1.6rem 0 0.7rem;
    font-size: clamp(1.35rem, 2.2vw, 1.7rem);
    font-weight: 800;
}

.blog-content :deep(h4) {
    margin: 1.25rem 0 0.55rem;
    font-size: 1.15rem;
    font-weight: 800;
}

.blog-content :deep(p) {
    margin: 1rem 0;
    line-height: 1.85;
}

.blog-content :deep(ul),
.blog-content :deep(ol) {
    margin: 1rem 0;
    padding-left: 1.45rem;
}

.blog-content :deep(li) {
    margin: 0.35rem 0;
}

.blog-content :deep(blockquote) {
    margin: 1.5rem 0;
    border-left: 4px solid var(--accent);
    padding: 0.4rem 0 0.4rem 1.1rem;
    color: var(--secondary);
}

.blog-content :deep(pre) {
    overflow-x: auto;
    border-radius: 0.75rem;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--inputBg) 86%, var(--bg));
    padding: 1rem;
}

.blog-content :deep(a) {
    color: var(--accent);
    text-decoration: underline;
}

.blog-content :deep(.blog-lead) {
    color: var(--secondary);
    font-size: clamp(1.12rem, 2vw, 1.3rem);
    font-weight: 600;
    line-height: 1.8;
}

.blog-content :deep(.blog-callout) {
    margin: 1.5rem 0;
    border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border));
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--accent) 12%, var(--card));
    padding: 1.1rem;
}

.blog-content :deep(.blog-callout strong) {
    display: block;
    margin-bottom: 0.35rem;
    color: var(--accent);
}

.blog-content :deep(.blog-image) {
    margin: 2rem 0;
}

.blog-content :deep(.blog-image img) {
    display: block;
    width: 100%;
    border-radius: 1rem;
    object-fit: cover;
}

.blog-content :deep(.blog-image figcaption) {
    margin-top: 0.65rem;
    color: var(--secondary);
    font-size: 0.9rem;
    text-align: center;
}

.blog-content :deep(.blog-text-primary) {
    color: var(--primary);
}

.blog-content :deep(.blog-text-secondary) {
    color: var(--secondary);
}

.blog-content :deep(.blog-text-accent) {
    color: var(--accent);
}

.blog-content :deep(.blog-text-success) {
    color: var(--success);
}

.blog-content :deep(.blog-text-warning) {
    color: var(--accent-3);
}

.blog-content :deep(.blog-text-danger) {
    color: var(--error);
}

.blog-content :deep(.blog-mark) {
    border-radius: 0.25rem;
    background: color-mix(in srgb, var(--accent-3) 22%, transparent);
    color: var(--primary);
    padding: 0.05rem 0.25rem;
}
</style>
