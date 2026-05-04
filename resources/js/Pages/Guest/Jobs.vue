<script setup>
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    jobs: { type: Array, default: () => [] },
})

const values = [
    ['las la-heart', 'guest.jobs.values.sport.title', 'guest.jobs.values.sport.text'],
    ['las la-rocket', 'guest.jobs.values.ownership.title', 'guest.jobs.values.ownership.text'],
    ['las la-users', 'guest.jobs.values.team.title', 'guest.jobs.values.team.text'],
]
</script>

<template>
    <SeoHead
        title="Jobs im Sport"
        description="Finde Jobs, Ehrenamt und Vereinsrollen im Sport. Airmius verbindet Vereine, Organisationen und Menschen, die den Sport gestalten wollen."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wider text-air-green">{{ $t('Jobs') }}</span>
                <h1 class="mx-auto mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    {{ $t('guest.jobs.hero.title') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                    {{ $t('guest.jobs.hero.subtitle') }}
                </p>
            </section>

            <section class="mx-auto mt-14 grid max-w-6xl gap-5 lg:grid-cols-3">
                <div v-for="[icon, title, text] in values" :key="title" class="surface-card p-5 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-inputBg">
                        <i :class="[icon, 'text-2xl text-air-green']"></i>
                    </div>
                    <h2 class="font-bold text-primary">{{ $t(title) }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-secondary">{{ $t(text) }}</p>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-6xl pb-20">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">{{ $t('guest.jobs.open_roles') }}</h2>
                    <p class="mt-2 text-secondary">{{ $t('guest.jobs.open_roles_text') }}</p>
                </div>

                <div class="space-y-3">
                    <article v-for="job in jobs" :key="job.id" class="surface-card flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="job.type === 'volunteer' ? 'bg-air-green/15 text-air-green' : 'bg-air-blue/15 text-air-blue'">
                                    {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                                </span>
                                <span class="text-xs text-secondary">{{ job.club?.name }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-primary">{{ job.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }} · {{ job.employment_type || 'Flexibel' }}
                            </p>
                            <p class="mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-secondary">{{ job.description }}</p>
                        </div>
                        <a
                            :href="job.application_url || (job.contact_email ? `mailto:${job.contact_email}` : '#')"
                            class="rounded-full border border-border px-4 py-2 text-center text-sm font-semibold text-primary transition hover:bg-muted"
                        >
                            {{ $t('guest.jobs.apply') }}
                        </a>
                    </article>

                    <div v-if="!jobs.length" class="surface-card p-8 text-center text-sm text-secondary">
                        Aktuell sind noch keine offenen Stellen veröffentlicht.
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
