<script setup>
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    posts: Object,
})

const stripHtml = (value = '') => String(value)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const teaserText = (post) => stripHtml(post.excerpt || post.content || '')
</script>

<template>
    <SeoHead
        title="Airmius Blog"
        description="Praxiswissen, Updates und Ideen für digitale Sportorganisation, Vereine, Trainer, Teams und Sportler."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-7xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Airmius Blog</p>
                <h1 class="mt-3 max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    Ideen, Updates und Praxiswissen für moderne Sportorganisation.
                </h1>
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
                        </div>
                        <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-secondary">{{ teaserText(post) }}</p>
                        <Link :href="route('guest.blog.show', post.slug)" class="mt-5 inline-flex font-semibold text-air-blue hover:underline">
                            Lesen
                        </Link>
                    </div>
                </article>
            </section>
        </main>

        <Footer />
    </div>
</template>
