<script setup>
import Footer from '@/Components/Guest/Footer.vue'
import Nav from '@/Components/Guest/Nav.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    clubName: { type: String, default: '' },
})

const content = computed(() => ({
    confirmed: {
        icon: 'las la-check-circle',
        tone: 'text-success',
        title: 'Newsletter-Anmeldung bestätigt',
        body: `Du erhältst künftig die Newsletter von ${props.clubName || 'diesem Verein'}. In jeder Nachricht kannst du dich mit einem Klick wieder abmelden.`,
    },
    unsubscribed: {
        icon: 'las la-envelope-open',
        tone: 'text-air-blue',
        title: 'Newsletter abbestellt',
        body: `Deine Adresse wurde für Newsletter von ${props.clubName || 'diesem Verein'} gesperrt. Du erhältst keine weiteren Newsletter.`,
    },
    invalid: {
        icon: 'las la-exclamation-circle',
        tone: 'text-warning',
        title: 'Link nicht mehr gültig',
        body: 'Der Link ist abgelaufen, wurde bereits verwendet oder ist ungültig. Starte die Anmeldung bei Bedarf erneut auf dem Vereinsprofil.',
    },
})[props.status] || {})
</script>

<template>
    <SeoHead :title="$t(content.title)" :description="$t(content.body)" />
    <div class="min-h-screen bg-bg text-primary">
        <Nav />
        <main id="main-content" class="grid min-h-[80vh] place-items-center px-4 pb-16 pt-32" tabindex="-1">
            <section class="w-full max-w-xl rounded-3xl border border-border bg-card p-7 text-center shadow-xl sm:p-10" role="status">
                <i :class="[content.icon, content.tone]" class="text-6xl" aria-hidden="true"></i>
                <h1 class="mt-5 text-3xl font-black">{{ $t(content.title) }}</h1>
                <p class="mt-4 leading-7 text-secondary">{{ $t(content.body) }}</p>
                <Link :href="route('guest.vereine')" class="mt-7 inline-flex rounded-xl bg-buttonPrimary px-5 py-3 font-bold text-buttonTextPrimary">
                    {{ $t('Vereine entdecken') }}
                </Link>
            </section>
        </main>
        <Footer />
    </div>
</template>
