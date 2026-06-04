<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    openCreateModal: { type: Function, required: true },
})
</script>

<template>
    <section class="overflow-hidden rounded-2xl border-l-4 border-l-air-blue border-border bg-card">
        <div class="grid gap-6 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">Finanzen</p>
                <h1 class="mt-2 text-3xl font-black text-primary">Rechnungszentrale</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Erstelle und prüfe Rechnungen für Konto-Abos, Outfit-Abos, Marketplace-Käufe, Kurse,
                    ADS, Sponsoring und Werbeagentur-Leistungen wie Website, Logo oder Branding.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    @click="openCreateModal"
                >
                    <i class="las la-plus-circle text-lg"></i>
                    Rechnung erstellen
                </button>
                <Link :href="route('payments.index')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-3 text-sm font-bold text-primary hover:bg-muted">
                    <i class="las la-credit-card text-lg"></i>
                    Zahlungen
                </Link>
            </div>
        </div>
    </section>
</template>

