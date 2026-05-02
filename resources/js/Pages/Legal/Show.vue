<script setup>
import { Head, Link } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Footer from '@/Components/Guest/Footer.vue'

defineProps({
    title: {
        type: String,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    note: {
        type: String,
        default: null,
    },
})

const legalLinks = [
    { label: 'Impressum', route: 'legal.imprint' },
    { label: 'Datenschutz', route: 'policy.show' },
    { label: 'AGB', route: 'terms.show' },
    { label: 'Community', route: 'legal.community' },
    { label: 'Jugendschutz', route: 'legal.minors' },
    { label: 'Cookies', route: 'legal.cookies' },
    { label: 'Widerruf', route: 'legal.withdrawal' },
    { label: 'Kontakt & Melden', route: 'legal.reporting' },
]
</script>

<template>
    <Head :title="title" />

    <main class="min-h-screen bg-bg text-primary">
        <header class="border-b border-border bg-card/70">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link :href="route('welcome')" class="flex items-center gap-3">
                    <ApplicationLogo class="h-12 w-12" />
                    <span class="font-semibold tracking-wide">AIRMIUS</span>
                </Link>

                <div class="flex items-center gap-2">
                    <Link :href="route('login')" class="btn">Anmelden</Link>
                    <Link :href="route('register')" class="btn-primary">Registrieren</Link>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="h-fit rounded-lg border border-border bg-card p-3">
                <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-secondary">Rechtliches</p>
                <nav class="space-y-1">
                    <Link
                        v-for="item in legalLinks"
                        :key="item.route"
                        :href="route(item.route)"
                        class="block rounded-lg px-3 py-2 text-sm text-secondary hover:bg-muted hover:text-primary"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <article class="rounded-lg border border-border bg-card">
                <div class="border-b border-border p-5 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Airmius</p>
                    <h1 class="mt-2 text-3xl font-semibold text-primary sm:text-4xl">{{ title }}</h1>
                    <p v-if="note" class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                        {{ note }}
                    </p>
                </div>

                <div class="space-y-8 p-5 sm:p-8">
                    <section v-for="section in sections" :key="section.title">
                        <h2 class="text-xl font-semibold text-primary">{{ section.title }}</h2>
                        <div class="mt-3 space-y-2 text-sm leading-7 text-secondary">
                            <p v-for="line in section.body" :key="line">{{ line }}</p>
                        </div>
                    </section>
                </div>
            </article>
        </section>

        <Footer />
    </main>
</template>
