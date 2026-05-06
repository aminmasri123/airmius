<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    status: { type: Number, default: 403 },
    title: { type: String, default: 'Upgrade oder Berechtigung erforderlich' },
    message: {
        type: String,
        default: 'Diese Funktion ist in deinem aktuellen Paket oder mit deiner aktuellen Rolle nicht freigeschaltet.',
    },
    upgradeUrl: { type: String, default: '/preise' },
})
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-[70vh] items-center justify-center px-4 py-10">
        <section class="w-full max-w-2xl rounded-2xl border border-border bg-card p-8 text-center shadow-xl">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-warning/15 text-warning">
                <i class="las la-lock text-4xl"></i>
            </div>

            <p class="mt-6 text-sm font-semibold uppercase tracking-wide text-warning">
                {{ status }}
            </p>

            <h1 class="mt-2 text-2xl font-bold text-primary">
                {{ title }}
            </h1>

            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-secondary">
                {{ message }}
            </p>

            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <Link :href="upgradeUrl" class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                    Upgrade ansehen
                </Link>

                <Link :href="route('auth.dashboard')" class="rounded-lg border border-border px-5 py-3 text-sm font-semibold text-primary hover:border-borderHover">
                    Zurueck zum Dashboard
                </Link>
            </div>
        </section>
    </div>
</template>
