<script setup>
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
})

const highlights = [
    {
        icon: 'las la-calendar-check',
        color: 'text-air-blue',
        title: 'guest.top.cards.planning.title',
        text: 'guest.top.cards.planning.text',
    },
    {
        icon: 'las la-comments',
        color: 'text-air-green',
        title: 'guest.top.cards.communication.title',
        text: 'guest.top.cards.communication.text',
    },
    {
        icon: 'las la-chart-line',
        color: 'text-air-orange',
        title: 'guest.top.cards.performance.title',
        text: 'guest.top.cards.performance.text',
    },
]

const articles = [
    ['Training', 'guest.top.articles.training.title', 'guest.top.articles.training.text', 'las la-running'],
    ['Verein', 'guest.top.articles.club.title', 'guest.top.articles.club.text', 'las la-warehouse'],
    ['Digital', 'guest.top.articles.digital.title', 'guest.top.articles.digital.text', 'las la-laptop-code'],
]
</script>

<template>
    <SeoHead
        title="Top Inhalte für Sport, Training und Vereinsarbeit"
        description="Entdecke Inhalte rund um Trainingsplanung, Teamkommunikation, Vereinsorganisation und digitale Sportentwicklung mit Airmius."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_.95fr]">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ $t('Top Inhalte') }}</span>
                    <h1 class="mt-3 font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ $t('guest.top.hero.title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                        {{ $t('guest.top.hero.subtitle') }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-full bg-air-blue px-6 py-3 text-center font-bold text-white transition hover:bg-blue-600"
                        >
                            {{ $t('guest.cta.start') }}
                        </Link>
                        <Link
                            :href="route('welcome')"
                            class="rounded-full border border-border px-6 py-3 text-center font-semibold text-primary transition hover:bg-muted"
                        >
                            {{ $t('guest.cta.explore') }}
                        </Link>
                    </div>
                </div>

                <div class="surface-card overflow-hidden p-5">
                    <div class="rounded-xl border border-border bg-inputBg p-4">
                        <div class="flex items-center justify-between border-b border-border pb-3">
                            <div>
                                <p class="text-xs uppercase tracking-wider text-secondary">{{ $t('guest.top.preview.label') }}</p>
                                <h2 class="mt-1 text-xl font-bold text-primary">{{ $t('guest.top.preview.title') }}</h2>
                            </div>
                            <i class="las la-fire text-4xl text-air-orange"></i>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div v-for="item in highlights" :key="item.title" class="flex gap-3 rounded-lg bg-card p-3">
                                <i :class="[item.icon, item.color, 'mt-1 text-2xl']"></i>
                                <div>
                                    <h3 class="font-semibold text-primary">{{ $t(item.title) }}</h3>
                                    <p class="text-sm text-secondary">{{ $t(item.text) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-16 max-w-7xl pb-20">
                <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-primary">{{ $t('guest.top.sections.latest') }}</h2>
                        <p class="mt-2 text-secondary">{{ $t('guest.top.sections.latest_text') }}</p>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <article v-for="[tag, title, text, icon] in articles" :key="title" class="surface-card p-5 transition hover:border-air-blue/40">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[icon, 'text-2xl text-air-blue']"></i>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-secondary">{{ tag }}</span>
                        <h3 class="mt-2 text-lg font-bold text-primary">{{ $t(title) }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                    </article>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>

