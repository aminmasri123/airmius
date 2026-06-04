<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    enrollments: { type: Array, default: () => [] },
})

const statusLabel = (status) => ({
    active: 'Aktiv',
    cancelled: 'Entzogen',
    refunded: 'Erstattet',
}[status] || status)

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    : '-'
</script>

<template>
    <Head title="Meine Kurse" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius Sportschule</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Meine Kurse</h1>
            <p class="mt-2 text-sm text-secondary">Alle freigeschalteten Kurse, Fortschritte und Zertifikate an einem Ort.</p>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article v-for="enrollment in enrollments" :key="enrollment.id" class="surface-card overflow-hidden">
                <div class="grid gap-4 p-5 md:grid-cols-[8rem_minmax(0,1fr)]">
                    <div class="flex aspect-video items-center justify-center overflow-hidden rounded-lg bg-inputBg md:aspect-square">
                        <img v-if="enrollment.course?.cover_image" :src="enrollment.course.cover_image" :alt="enrollment.course.title" class="h-full w-full object-cover">
                        <i v-else class="las la-graduation-cap text-5xl text-air-blue"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold text-secondary">{{ statusLabel(enrollment.status) }}</span>
                            <span v-if="enrollment.completed_at" class="rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">Abgeschlossen</span>
                        </div>
                        <h2 class="mt-3 break-words text-xl font-bold text-primary">{{ enrollment.course?.title }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ enrollment.course?.subtitle || enrollment.course?.description }}</p>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                <span>Fortschritt</span>
                                <span>{{ enrollment.progress_percent || 0 }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                <div class="h-full rounded-full bg-air-blue" :style="{ width: `${enrollment.progress_percent || 0}%` }"></div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-secondary">Start: {{ formatDate(enrollment.started_at) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-border p-4">
                    <Link v-if="enrollment.status === 'active'" :href="enrollment.course.show_url" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        Weiterlernen
                    </Link>
                    <a v-if="enrollment.certificate?.download_url" :href="enrollment.certificate.download_url" class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success">
                        Zertifikat PDF
                    </a>
                    <a v-if="enrollment.certificate?.verify_url" :href="enrollment.certificate.verify_url" class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success">
                        Öffentlich prüfen
                    </a>
                </div>
            </article>
        </section>

        <section v-if="!enrollments.length" class="surface-card p-8 text-center">
            <i class="las la-book-open text-5xl text-air-blue"></i>
            <h2 class="mt-4 text-xl font-bold text-primary">Noch keine Kurse</h2>
            <p class="mt-2 text-sm text-secondary">Sobald du dich einschreibst oder einen Kurs kaufst, erscheint er hier.</p>
            <Link :href="route('guest.e-learning')" class="mt-5 inline-flex rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                Kurse entdecken
            </Link>
        </section>
    </div>
</template>

