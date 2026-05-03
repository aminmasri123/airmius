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

const courses = [
    ['las la-calendar-alt', 'guest.learning.courses.organization.title', 'guest.learning.courses.organization.text'],
    ['las la-comments', 'guest.learning.courses.communication.title', 'guest.learning.courses.communication.text'],
    ['las la-chart-pie', 'guest.learning.courses.analysis.title', 'guest.learning.courses.analysis.text'],
    ['las la-shield-alt', 'guest.learning.courses.privacy.title', 'guest.learning.courses.privacy.text'],
]

const steps = [
    'guest.learning.steps.learn',
    'guest.learning.steps.apply',
    'guest.learning.steps.improve',
]
</script>

<template>
    <SeoHead
        title="E-Learning für Sportorganisation"
        description="Lerne moderne Sportorganisation mit Airmius: Kommunikation, Trainingsplanung, Datenschutz und digitale Vereinsprozesse einfach erklaert."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[.95fr_1.05fr]">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-wider text-air-orange">{{ $t('E-Learning') }}</span>
                    <h1 class="mt-3 font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ $t('guest.learning.hero.title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                        {{ $t('guest.learning.hero.subtitle') }}
                    </p>
                    <div class="mt-8">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex rounded-full bg-air-orange px-6 py-3 font-bold text-white transition hover:bg-orange-600"
                        >
                            {{ $t('guest.learning.start') }}
                        </Link>
                    </div>
                </div>

                <div class="surface-card p-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="(step, index) in steps" :key="step" class="rounded-lg border border-border bg-inputBg p-4">
                            <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-full bg-air-orange text-sm font-bold text-white">
                                {{ index + 1 }}
                            </div>
                            <p class="text-sm font-semibold text-primary">{{ $t(step) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-16 max-w-7xl pb-20">
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-primary">{{ $t('guest.learning.library') }}</h2>
                    <p class="mt-2 text-secondary">{{ $t('guest.learning.library_text') }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <article v-for="[icon, title, text] in courses" :key="title" class="surface-card p-5">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[icon, 'text-2xl text-air-orange']"></i>
                        </div>
                        <h3 class="font-bold text-primary">{{ $t(title) }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                    </article>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
