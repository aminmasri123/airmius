<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    checkout: {
        type: Object,
        required: true,
    },
    bank: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <Head title="Zahlung per Überweisung" />

    <div class="mx-auto max-w-4xl space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius Abo</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Zahlung per Überweisung</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                    Bitte überweise den Betrag mit exakt diesem Verwendungszweck. Dein Abo wird freigeschaltet, sobald die Zahlung eingegangen und bestätigt wurde.
                </p>
            </div>

            <div class="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-4 p-5">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">Verwendungszweck</p>
                        <p class="mt-2 break-all text-2xl font-bold text-primary">{{ checkout.payment_reference }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs font-semibold uppercase text-secondary">Betrag</p>
                            <p class="mt-2 text-xl font-bold text-primary">{{ checkout.amount }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-4">
                            <p class="text-xs font-semibold uppercase text-secondary">Fällig bis</p>
                            <p class="mt-2 text-xl font-bold text-primary">{{ checkout.due_at || '-' }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">Bankverbindung</p>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">Kontoinhaber</dt>
                                <dd class="text-right font-semibold text-primary">{{ bank.bank_account_holder || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">Bank</dt>
                                <dd class="text-right font-semibold text-primary">{{ bank.bank_name || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">IBAN</dt>
                                <dd class="break-all text-right font-semibold text-primary">{{ bank.iban || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-secondary">BIC</dt>
                                <dd class="text-right font-semibold text-primary">{{ bank.bic || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <aside class="border-t border-border bg-bg p-5 lg:border-l lg:border-t-0">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">Buchung</p>
                        <h2 class="mt-2 text-lg font-semibold text-primary">{{ checkout.plan?.name }}</h2>
                        <p v-if="checkout.club" class="mt-1 text-sm text-secondary">{{ checkout.club.name }}</p>
                        <p class="mt-3 text-sm text-secondary">
                            Status: <span class="font-semibold text-air-blue">Warte auf Zahlungseingang</span>
                        </p>
                    </div>

                    <div class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm leading-6 text-warning">
                        Achte darauf, den Verwendungszweck nicht zu verändern. Sonst kann die Zahlung nicht automatisch oder eindeutig zugeordnet werden.
                    </div>

                    <div class="mt-5 flex flex-col gap-2">
                        <a v-if="checkout.invoice" :href="route('auth.subscription-invoices.download', checkout.invoice.id)" download class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted">
                            Rechnung herunterladen
                        </a>
                        <Link :href="route('guest.pricing')" class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted">
                            Zurück zu Preise
                        </Link>
                        <Link :href="route('auth.dashboard')" class="rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary">
                            Zum Dashboard
                        </Link>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
