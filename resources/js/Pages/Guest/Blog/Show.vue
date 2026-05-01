<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Footer from '@/Components/Guest/Footer.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    post: Object,
})
</script>

<template>
    <Head :title="post.meta_title || post.title">
        <meta v-if="post.meta_description || post.excerpt" name="description" :content="post.meta_description || post.excerpt" />
    </Head>

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" />

        <main class="px-4 pt-28">
            <article class="mx-auto max-w-4xl">
                <Link :href="route('guest.blog.index')" class="text-sm font-semibold text-air-blue hover:underline">
                    Zurueck zum Blog
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

                <div class="prose prose-invert mt-10 max-w-none whitespace-pre-line text-primary">
                    {{ post.content }}
                </div>
            </article>
        </main>

        <Footer />
    </div>
</template>
