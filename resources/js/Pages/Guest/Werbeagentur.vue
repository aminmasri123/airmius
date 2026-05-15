<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
})

const services = [
    ['las la-laptop-code', 'guest.agency.services.website.title', 'guest.agency.services.website.text'],
    ['las la-palette', 'guest.agency.services.brand.title', 'guest.agency.services.brand.text'],
    ['las la-search', 'guest.agency.services.seo.title', 'guest.agency.services.seo.text'],
    ['las la-bullhorn', 'guest.agency.services.campaigns.title', 'guest.agency.services.campaigns.text'],
    ['las la-camera', 'guest.agency.services.content.title', 'guest.agency.services.content.text'],
    ['las la-shield-alt', 'guest.agency.services.legal.title', 'guest.agency.services.legal.text'],
]

const packages = [
    {
        name: 'guest.agency.packages.start.name',
        price: 'guest.agency.packages.start.price',
        text: 'guest.agency.packages.start.text',
        items: [
            'guest.agency.packages.start.items.onepage',
            'guest.agency.packages.start.items.cta',
            'guest.agency.packages.start.items.seo',
            'guest.agency.packages.start.items.responsive',
        ],
    },
    {
        name: 'guest.agency.packages.plus.name',
        price: 'guest.agency.packages.plus.price',
        text: 'guest.agency.packages.plus.text',
        items: [
            'guest.agency.packages.plus.items.pages',
            'guest.agency.packages.plus.items.teams',
            'guest.agency.packages.plus.items.sponsors',
            'guest.agency.packages.plus.items.news',
        ],
        featured: true,
    },
    {
        name: 'guest.agency.packages.campaign.name',
        price: 'guest.agency.packages.campaign.price',
        text: 'guest.agency.packages.campaign.text',
        items: [
            'guest.agency.packages.campaign.items.landingpage',
            'guest.agency.packages.campaign.items.form',
            'guest.agency.packages.campaign.items.copy',
            'guest.agency.packages.campaign.items.reporting',
        ],
    },
]

const steps = [
    'guest.agency.steps.items.goal',
    'guest.agency.steps.items.scope',
    'guest.agency.steps.items.offer',
    'guest.agency.steps.items.start',
]

const requestSent = ref(false)
const requestModalOpen = ref(false)
const requestForm = useForm({
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    club_name: '',
    domain: '',
    goals: '',
    notes: '',
})

const submitRequest = () => {
    requestForm.post(route('guest.werbeagentur.request'), {
        preserveScroll: true,
        onSuccess: () => {
            requestSent.value = true
            requestForm.reset()
        },
    })
}

const openRequestModal = () => {
    requestSent.value = false
    requestModalOpen.value = true
}

const closeRequestModal = () => {
    requestModalOpen.value = false
}
</script>

<template>
    <SeoHead
        :title="$t('guest.agency.meta.title')"
        :description="$t('guest.agency.meta.description')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pb-20 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ $t('guest.agency.eyebrow') }}</p>
                    <h1 class="mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ $t('guest.agency.hero.title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                        {{ $t('guest.agency.hero.subtitle') }}
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            @click="openRequestModal"
                            class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        >
                            {{ $t('guest.agency.cta.start') }}
                        </button>
                        <Link
                            :href="route('guest.pricing')"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted"
                        >
                            {{ $t('guest.agency.cta.prices') }}
                        </Link>
                    </div>
                    <p class="mt-3 text-xs text-secondary">
                        {{ $t('guest.agency.notice') }}
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <div class="border-b border-border bg-bg p-4">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-400"></span>
                            <span class="h-3 w-3 rounded-full bg-yellow-400"></span>
                            <span class="h-3 w-3 rounded-full bg-air-green"></span>
                            <span class="ml-3 text-xs text-secondary">vereinswebsite.airmius.de</span>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="rounded-lg bg-inputBg p-5">
                            <p class="text-xs font-semibold uppercase text-air-green">{{ $t('guest.agency.preview.label') }}</p>
                            <h2 class="mt-2 text-2xl font-bold">{{ $t('guest.agency.preview.title') }}</h2>
                            <p class="mt-3 text-sm leading-relaxed text-secondary">
                                {{ $t('guest.agency.preview.text') }}
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">{{ $t('guest.agency.preview.requests') }}</p>
                                    <p class="mt-1 text-xl font-bold text-primary">+34</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">{{ $t('Teams') }}</p>
                                    <p class="mt-1 text-xl font-bold text-primary">12</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">{{ $t('Sponsors') }}</p>
                                    <p class="mt-1 text-xl font-bold text-primary">8</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">{{ $t('guest.agency.services.title') }}</h2>
                    <p class="mt-2 max-w-2xl text-secondary">
                        {{ $t('guest.agency.services.subtitle') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="[icon, title, text] in services" :key="title" class="surface-card p-5">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[icon, 'text-2xl text-air-blue']"></i>
                        </div>
                        <h3 class="font-bold text-primary">{{ $t(title) }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl">
                <div class="grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="pack in packages"
                        :key="pack.name"
                        class="surface-card flex flex-col p-5"
                        :class="pack.featured ? 'border-air-blue/60 shadow-lg shadow-air-blue/10' : ''"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-xl font-bold text-primary">{{ $t(pack.name) }}</h3>
                            <span v-if="pack.featured" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ $t('guest.agency.packages.popular') }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-900 text-primary">{{ $t(pack.price) }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ $t(pack.text) }}</p>
                        <ul class="mt-5 flex-1 space-y-2 text-sm text-secondary">
                            <li v-for="item in pack.items" :key="item" class="flex gap-2">
                                <i class="las la-check mt-0.5 text-air-green"></i>
                                <span>{{ $t(item) }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl rounded-lg border border-border bg-card p-6">
                <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-green">{{ $t('guest.agency.steps.eyebrow') }}</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">{{ $t('guest.agency.steps.title') }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            {{ $t('guest.agency.steps.subtitle') }}
                        </p>
                    </div>
                    <ol class="grid gap-3 sm:grid-cols-2">
                        <li v-for="(step, index) in steps" :key="step" class="rounded-lg bg-bg p-4">
                            <span class="text-xs font-bold text-air-blue">{{ $t('guest.agency.steps.step', { number: index + 1 }) }}</span>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ $t(step) }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <div v-if="requestModalOpen" class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-black/70 px-4 py-8" @click.self="closeRequestModal">
                <form class="relative mx-auto grid w-full max-w-4xl gap-8 rounded-xl border border-border bg-card p-6 pr-14 shadow-2xl lg:grid-cols-[0.85fr_1.15fr]" @submit.prevent="submitRequest">
                    <button type="button" class="absolute right-4 top-4 rounded-lg p-2 text-secondary hover:bg-muted" aria-label="Anfrage schliessen" @click="closeRequestModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Website-Anfrage</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">Anfrage direkt an Airmius senden</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            Gäste und eingeloggte Vereinsnutzer können hier ohne Umweg über das Dashboard eine Anfrage für Website, Sponsorenbereich oder Kampagnen stellen.
                        </p>
                        <p v-if="requestSent" class="mt-4 rounded-lg border border-air-green/40 bg-air-green/10 px-4 py-3 text-sm font-semibold text-air-green">
                            Danke, deine Anfrage wurde gesendet. Airmius meldet sich bei dir.
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Name</span>
                                <input v-model="requestForm.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Dein Name">
                                <span v-if="requestForm.errors.guest_name" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_name }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">E-Mail</span>
                                <input v-model="requestForm.guest_email" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="name@verein.de">
                                <span v-if="requestForm.errors.guest_email" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_email }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Verein</span>
                                <input v-model="requestForm.club_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Vereinsname">
                                <span v-if="requestForm.errors.club_name" class="mt-1 block text-xs text-error">{{ requestForm.errors.club_name }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Telefon optional</span>
                                <input v-model="requestForm.guest_phone" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="+49 ...">
                                <span v-if="requestForm.errors.guest_phone" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_phone }}</span>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Domain oder Wunschadresse optional</span>
                            <input v-model="requestForm.domain" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z. B. meinverein.de">
                            <span v-if="requestForm.errors.domain" class="mt-1 block text-xs text-error">{{ requestForm.errors.domain }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Was braucht der Verein?</span>
                            <textarea v-model="requestForm.goals" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Website, Sponsorenbereich, Mitglieder gewinnen, Kampagne, Inhalte ..."></textarea>
                            <span v-if="requestForm.errors.goals" class="mt-1 block text-xs text-error">{{ requestForm.errors.goals }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Notizen optional</span>
                            <textarea v-model="requestForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Zeitplan, Budget, bestehende Website oder weitere Hinweise"></textarea>
                            <span v-if="requestForm.errors.notes" class="mt-1 block text-xs text-error">{{ requestForm.errors.notes }}</span>
                        </label>

                        <button class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-60" :disabled="requestForm.processing">
                            Anfrage senden
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <Footer />
    </div>
</template>
