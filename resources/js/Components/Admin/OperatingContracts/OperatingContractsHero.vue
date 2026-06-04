<script setup>
defineProps({
    summary: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    openCreateModal: { type: Function, required: true },
})
</script>

<template>
    <section class="overflow-hidden rounded-2xl border-l-4 border-l-air-blue border-border bg-card">
        <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-air-blue">Interne Kontrolle</p>
                <h1 class="mt-2 text-3xl font-black text-primary">Betriebskosten & Verträge</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    WLAN, Handy, Leasing, Hosting, Software und Dienstleister mit Kosten, Fristen und nächsten Zahlungen im Blick.
                </p>
            </div>
            <button
                v-if="canManage"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                @click="openCreateModal"
            >
                <i class="las la-plus-circle text-lg"></i>
                Vertrag anlegen
            </button>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Aktive Verträge</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.active_count || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fixkosten / Monat</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.monthly_total || '0,00 EUR' }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fixkosten / Jahr</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ summary.yearly_total || '0,00 EUR' }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-card p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-secondary">Fristen & Zahlungen</p>
            <p class="mt-3 text-3xl font-black text-primary">{{ (summary.due_soon_count || 0) + (summary.notice_soon_count || 0) }}</p>
            <p class="mt-1 text-xs text-secondary">{{ summary.due_soon_count || 0 }} Zahlungen, {{ summary.notice_soon_count || 0 }} Fristen</p>
        </div>
    </section>
</template>




