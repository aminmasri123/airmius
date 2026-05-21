<script setup>
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    posts: Object,
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ category: '', search: '' }),
    },
    activeCategory: {
        type: Object,
        default: null,
    },
    seo: {
        type: Object,
        default: () => ({
            title: 'Airmius Blog',
            description: 'Praxiswissen, Updates und Ideen für digitale Sportorganisation, Vereine, Trainer, Teams und Sportler.',
            canonical: null,
            noindex: false,
        }),
    },
})

const selectedCategory = ref(props.filters?.category || '')
const search = ref(props.filters?.search || '')

const breadcrumbSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
        {
            '@type': 'ListItem',
            position: 1,
            name: 'Startseite',
            item: route('welcome'),
        },
        {
            '@type': 'ListItem',
            position: 2,
            name: 'Blog',
            item: route('guest.blog.index'),
        },
        ...(props.activeCategory ? [{
            '@type': 'ListItem',
            position: 3,
            name: props.activeCategory.name,
            item: route('guest.blog.category', props.activeCategory.slug),
        }] : []),
    ],
}))

const stripHtml = (value = '') => String(value)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const teaserText = (post) => stripHtml(post.excerpt || post.content || '')

const formatDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value))
}

const applyFilters = () => {
    const target = selectedCategory.value
        ? route('guest.blog.category', selectedCategory.value)
        : route('guest.blog.index')

    router.get(target, {
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <SeoHead
        :title="seo.title"
        :description="seo.description"
        :canonical="seo.canonical"
        :noindex="seo.noindex"
        :schema="breadcrumbSchema"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-7xl">
                <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-secondary" aria-label="Breadcrumb">
                    <Link :href="route('welcome')" class="hover:text-primary">Startseite</Link>
                    <span>/</span>
                    <Link :href="route('guest.blog.index')" class="hover:text-primary">Blog</Link>
                    <template v-if="activeCategory">
                        <span>/</span>
                        <span class="text-primary">{{ activeCategory.name }}</span>
                    </template>
                </nav>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Airmius Blog</p>
                <h1 class="mt-3 max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    {{ activeCategory ? activeCategory.name : 'Ideen, Updates und Praxiswissen für moderne Sportorganisation.' }}
                </h1>
                <p v-if="activeCategory?.description" class="mt-4 max-w-3xl text-base leading-relaxed text-secondary">
                    {{ activeCategory.description }}
                </p>
            </section>

            <section class="mx-auto mt-10 max-w-7xl">
                <div class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-[1fr_220px_auto]">
                    <input
                        v-model="search"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Blog durchsuchen..."
                        @keydown.enter.prevent="applyFilters"
                    />
                    <select v-model="selectedCategory" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="applyFilters">
                        <option value="">Alle Kategorien</option>
                        <option v-for="category in categories" :key="category.id" :value="category.slug">
                            {{ category.name }} ({{ category.posts_count || 0 }})
                        </option>
                    </select>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="applyFilters">
                        Filtern
                    </button>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        :href="route('guest.blog.index')"
                        class="rounded-full border px-3 py-1.5 text-sm font-semibold"
                        :class="!selectedCategory ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                    >
                        Alle
                    </Link>
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="route('guest.blog.category', category.slug)"
                        class="rounded-full border px-3 py-1.5 text-sm font-semibold"
                        :class="selectedCategory === category.slug ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                    >
                        {{ category.name }}
                    </Link>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="post in posts.data" :key="post.id" class="surface-card overflow-hidden">
                    <div class="flex h-48 items-center justify-center bg-inputBg">
                        <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" class="h-full w-full object-cover" />
                        <i v-else class="las la-newspaper text-6xl text-secondary"></i>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-secondary">
                            <span v-if="post.category" class="rounded-full border border-border px-2 py-1">{{ post.category }}</span>
                            <span>{{ post.author?.name }}</span>
                            <span v-if="post.published_at">{{ formatDate(post.published_at) }}</span>
                            <span>{{ post.reading_time_minutes || 1 }} Min. Lesezeit</span>
                        </div>
                        <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-secondary">{{ teaserText(post) }}</p>
                        <Link :href="route('guest.blog.show', post.slug)" class="mt-5 inline-flex font-semibold text-air-blue hover:underline">
                            Lesen
                        </Link>
                    </div>
                </article>
            </section>

            <section v-if="!posts.data.length" class="mx-auto mt-12 max-w-7xl rounded-lg border border-border bg-card p-8 text-center text-secondary">
                Keine passenden Blogbeitraege gefunden.
            </section>

            <nav v-if="posts.links?.length > 3" class="mx-auto mt-10 flex max-w-7xl flex-wrap gap-2">
                <Link
                    v-for="link in posts.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted'"
                    v-html="link.label"
                />
            </nav>
        </main>

        <Footer />
    </div>
</template>
