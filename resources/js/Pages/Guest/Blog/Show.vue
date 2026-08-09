<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    post: Object,
    isPreview: {
        type: Boolean,
        default: false,
    },
    relatedPosts: {
        type: Array,
        default: () => [],
    },
})

const { t, locale } = useI18n()
const tx = (value, params = {}) => t(value, params)

const formatDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value))
}

const articleSchema = computed(() => {
    const breadcrumbs = [
        {
            '@type': 'ListItem',
            position: 1,
            name: tx('Startseite'),
            item: route('welcome'),
        },
        {
            '@type': 'ListItem',
            position: 2,
            name: tx('Blog'),
            item: route('guest.blog.index'),
        },
    ]

    if (props.post.blog_category?.slug || props.post.category) {
        breadcrumbs.push({
            '@type': 'ListItem',
            position: 3,
            name: props.post.blog_category?.name || props.post.category,
            item: props.post.blog_category?.slug ? route('guest.blog.category', props.post.blog_category.slug) : route('guest.blog.index'),
        })
    }

    breadcrumbs.push({
        '@type': 'ListItem',
        position: breadcrumbs.length + 1,
        name: props.post.title,
        item: route('guest.blog.show', props.post.slug),
    })

    return [
        {
            '@context': 'https://schema.org',
            '@type': 'BlogPosting',
            headline: props.post.meta_title || props.post.title,
            description: props.post.meta_description || props.post.excerpt || '',
            image: props.post.cover_image ? [props.post.cover_image] : undefined,
            datePublished: props.post.published_at || undefined,
            dateModified: props.post.updated_at || props.post.published_at || undefined,
            author: props.post.author?.name ? {
                '@type': 'Person',
                name: props.post.author.name,
            } : undefined,
            publisher: {
                '@type': 'Organization',
                name: 'Airmius',
            },
        },
        {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: breadcrumbs,
        },
    ]
})

const categoryHref = computed(() => props.post.blog_category?.slug
    ? route('guest.blog.category', props.post.blog_category.slug)
    : route('guest.blog.index'))
</script>

<template>
    <SeoHead
        :title="isPreview ? `[${tx('Vorschau')}] ${post.meta_title || post.title}` : (post.meta_title || post.title)"
        :description="post.meta_description || post.excerpt || tx('Artikel aus dem Airmius Blog zu Sport, Training, Vereinen und digitaler Organisation.')"
        :image="post.cover_image || '/img/logo/Airmius-Logo-Light.png'"
        type="article"
        :schema="articleSchema"
        :noindex="isPreview"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1">
            <div v-if="isPreview" class="mx-auto mb-6 max-w-4xl rounded-lg border border-air-orange/40 bg-air-orange/10 px-4 py-3 text-sm font-semibold text-air-orange">
                {{ tx('Vorschau: Dieser Beitrag ist nicht öffentlich indexierbar.') }}
            </div>

            <article class="mx-auto max-w-4xl">
                <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-secondary" :aria-label="tx('Breadcrumb')">
                    <Link :href="route('welcome')" class="hover:text-primary">{{ tx('Startseite') }}</Link>
                    <span>/</span>
                    <Link :href="route('guest.blog.index')" class="hover:text-primary">{{ tx('Blog') }}</Link>
                    <template v-if="post.blog_category?.name || post.category">
                        <span>/</span>
                        <Link :href="categoryHref" class="hover:text-primary">{{ post.blog_category?.name || post.category }}</Link>
                    </template>
                    <span>/</span>
                    <span class="text-primary">{{ post.title }}</span>
                </nav>

                <div class="mt-6 flex flex-wrap items-center gap-2 text-sm text-secondary">
                    <span v-if="post.category" class="rounded-full border border-border px-3 py-1">{{ post.category }}</span>
                    <span>{{ post.author?.name }}</span>
                    <span v-if="post.published_at">{{ formatDate(post.published_at) }}</span>
                    <span>{{ post.reading_time_minutes || 1 }} {{ tx('Min. Lesezeit') }}</span>
                </div>

                <h1 class="mt-4 font-heading text-4xl font-900 leading-tight sm:text-5xl">{{ post.title }}</h1>
                <p v-if="post.excerpt" class="mt-5 text-lg leading-relaxed text-secondary">{{ post.excerpt }}</p>

                <div v-if="post.cover_image" class="mt-8 overflow-hidden rounded-xl border border-border">
                    <img :src="post.cover_image" :alt="post.title" loading="eager" decoding="async" fetchpriority="high" class="w-full object-cover" />
                </div>

                <div class="blog-content prose prose-invert mt-10 max-w-none text-primary" v-html="post.content"></div>
            </article>

            <section v-if="relatedPosts.length" class="mx-auto mt-16 max-w-4xl border-t border-border pt-8">
                <h2 class="font-heading text-2xl font-900 text-primary">{{ tx('Mehr aus dieser Kategorie') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <article v-for="related in relatedPosts" :key="related.id" class="rounded-lg border border-border bg-card p-4">
                        <p class="text-xs text-secondary">{{ related.blog_category?.name || related.category }}</p>
                        <h3 class="mt-2 text-base font-bold text-primary">{{ related.title }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-secondary">{{ related.excerpt }}</p>
                        <Link :href="route('guest.blog.show', related.slug)" class="mt-4 inline-flex text-sm font-semibold text-air-blue hover:underline">
                            {{ tx('Lesen') }}
                        </Link>
                    </article>
                </div>
            </section>
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
