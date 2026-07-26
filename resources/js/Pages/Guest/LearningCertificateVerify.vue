<script setup>
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    certificate: { type: Object, required: true },
})

const { t, locale } = useI18n()
const dateLocale = () => locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE'))

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(dateLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    : '-'
</script>

<template>
    <SeoHead :title="t('Zertifikat prüfen')" :description="t('Öffentliche Prüfung eines Airmius E-Learning Zertifikats.')" />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-4xl">
                <Link :href="route('guest.e-learning')" class="text-sm font-semibold text-air-orange">{{ t('Zurück zur Sportschule') }}</Link>

                <article class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
                    <div class="border-b border-border bg-success/10 p-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-success">{{ t('Zertifikat gültig') }}</p>
                        <h1 class="mt-2 font-heading text-3xl font-900 text-primary sm:text-5xl">{{ certificate.course_title }}</h1>
                        <p v-if="certificate.course_subtitle" class="mt-3 max-w-2xl text-sm leading-relaxed text-secondary">{{ certificate.course_subtitle }}</p>
                    </div>

                    <div class="grid gap-5 p-6 md:grid-cols-3">
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">{{ t('Teilnehmer') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ certificate.student_name }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">{{ t('Ausgestellt') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ formatDate(certificate.issued_at) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs uppercase text-secondary">{{ t('Fortschritt') }}</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ certificate.progress_percent }}%</p>
                        </div>
                    </div>

                    <div class="border-t border-border p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs uppercase text-secondary">{{ t('Zertifikat-ID') }}</p>
                                <p class="mt-1 font-mono text-sm font-bold text-primary">{{ certificate.code }}</p>
                                <p class="mt-2 text-sm text-secondary">{{ t('Kursleitung:') }} {{ certificate.tutor?.name || t('Tutor') }}</p>
                            </div>
                            <Link v-if="certificate.course_url" :href="certificate.course_url" class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary">
                                {{ t('Kurs ansehen') }}
                            </Link>
                        </div>
                    </div>
                </article>
            </section>
        </main>

        <Footer />
    </div>
</template>
