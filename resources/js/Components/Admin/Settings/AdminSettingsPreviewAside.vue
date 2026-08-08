<script setup>
defineProps({
    billingBrandBadge: { type: String, default: 'AI' },
    form: { type: Object, required: true },
})
</script>

<template>
    <aside class="border-t border-border bg-bg p-5 lg:border-l lg:border-t-0">
        <div class="rounded-lg border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vorschau</p>
            <div class="mt-4 rounded-lg border border-border bg-bg p-5">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                    <i class="las la-tools text-2xl"></i>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-primary">
                    {{ form.maintenance_title }}
                </h3>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    {{ form.maintenance_message }}
                </p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full w-2/3 rounded-full bg-buttonPrimary"></div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
            Benutzer mit der Berechtigung <code v-text="'system.manage'" translate="no" class="font-semibold text-primary"></code>
            können Airmius weiterhin normal verwenden.
        </div>

        <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
            <p class="font-semibold text-primary">Überweisungsdaten</p>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between gap-3">
                    <dt>{{ $t('Kontoinhaber') }}</dt>
                    <dd class="text-right text-primary">{{ form.billing_bank_account_holder || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>{{ $t('IBAN') }}</dt>
                    <dd class="text-right text-primary">{{ form.billing_iban || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>Zahlungsziel</dt>
                    <dd class="text-right text-primary">{{ form.billing_payment_terms_days }} Tage</dd>
                </div>
            </dl>
        </div>

        <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">PDF-Branding Vorschau</p>
            <div class="mt-3 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-buttonPrimary/10 text-sm font-bold text-buttonTextPrimary">
                    {{ billingBrandBadge }}
                </div>
                <div>
                    <p class="text-sm font-semibold text-primary">{{ form.billing_brand_name || 'Airmius' }}</p>
                    <p class="text-xs text-secondary">{{ form.billing_company_name || 'Airmius' }}</p>
                    <p class="text-xs text-secondary">{{ form.billing_company_country || 'Deutschland' }}</p>
                </div>
            </div>
        </div>
    </aside>
</template>
