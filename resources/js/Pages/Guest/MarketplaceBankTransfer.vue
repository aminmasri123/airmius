<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'

defineProps({
    order: { type: Object, required: true },
    bank: { type: Object, required: true },
})
</script>

<template>
    <Head title="Ueberweisung" />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="true" :canRegister="true" />
        <Subnav />

        <main class="px-4 pb-24 pt-36 md:pb-12 md:pt-44">
            <div class="mx-auto max-w-3xl space-y-6">
                <section class="surface-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Ueberweisung</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ order.title }}</h1>
                    <p class="mt-2 text-sm text-secondary">Bitte ueberweise den Betrag mit exakt diesem Verwendungszweck.</p>
                </section>

                <section class="surface-card p-6">
                    <dl class="grid gap-4 text-sm">
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">Betrag</dt>
                            <dd class="font-semibold text-primary">{{ order.amount }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">Verwendungszweck</dt>
                            <dd class="font-semibold text-primary">{{ order.payment_reference }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">Faellig bis</dt>
                            <dd class="font-semibold text-primary">{{ order.due_at }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">Kontoinhaber</dt>
                            <dd class="font-semibold text-primary">{{ bank.bank_account_holder }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">Bank</dt>
                            <dd class="font-semibold text-primary">{{ bank.bank_name || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border pb-3">
                            <dt class="text-secondary">IBAN</dt>
                            <dd class="font-semibold text-primary">{{ bank.iban }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-secondary">BIC</dt>
                            <dd class="font-semibold text-primary">{{ bank.bic || '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <Link :href="route('guest.marketplace')" class="inline-flex rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Zurueck zum Marketplace
                </Link>
            </div>
        </main>

        <Footer />
    </div>
</template>
