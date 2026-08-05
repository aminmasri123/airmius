<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
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
    action: {
        type: Object,
        default: null,
    },
})

const { t } = useI18n()
const tx = (value, params = {}) => t(value, params)
const page = usePage()
const currentUser = computed(() => page.props.auth?.user || null)
const actionHref = computed(() => currentUser.value && props.action?.authenticated_href
    ? props.action.authenticated_href
    : props.action?.href)
const actionLabel = computed(() => currentUser.value && props.action?.authenticated_label
    ? props.action.authenticated_label
    : props.action?.label)

const legalLinks = [
    { label: 'Impressum', route: 'legal.imprint' },
    { label: 'Datenschutz', route: 'policy.show' },
    { label: 'Konto löschen', route: 'legal.account-deletion' },
    { label: 'Daten löschen', route: 'legal.data-erasure' },
    { label: 'AGB', route: 'terms.show' },
    { label: 'Community', route: 'legal.community' },
    { label: 'Jugendschutz', route: 'legal.minors' },
    { label: 'Cookies', route: 'legal.cookies' },
    { label: 'Widerruf', route: 'legal.withdrawal' },
    { label: 'Kontakt & Melden', route: 'legal.reporting' },
]
</script>

<template>
    <SeoHead
        :title="`${tx(title)} | Airmius`"
        :description="`${tx(title)} von Airmius: ${tx('rechtliche Informationen, Datenschutz, Nutzungsbedingungen und Hinweise für Nutzer, Vereine und Erziehungsberechtigte.')}`"
    />

    <main class="min-h-screen bg-bg text-primary">
        <header class="border-b border-border bg-card/70">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link :href="route('welcome')" class="flex items-center">
                    <ApplicationLogo class="h-10 w-auto max-w-[11rem]" />
                </Link>

                <div v-if="currentUser" class="flex items-center gap-2">
                    <Link :href="route('profile.show')" class="btn">{{ tx('Mein Konto') }}</Link>
                    <Link :href="route('auth.dashboard')" class="btn-primary">{{ tx('Dashboard') }}</Link>
                </div>
                <div v-else class="flex items-center gap-2">
                    <Link :href="route('login')" class="btn">{{ tx('Anmelden') }}</Link>
                    <Link :href="route('register')" class="btn-primary">{{ tx('Registrieren') }}</Link>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="h-fit rounded-lg border border-border bg-card p-3">
                <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('Rechtliches') }}</p>
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
                    <Link v-if="action" :href="actionHref" class="btn-primary mt-4">
                        {{ actionLabel }}
                    </Link>
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
