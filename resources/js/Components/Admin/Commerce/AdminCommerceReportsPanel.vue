<script setup>
defineProps({
    sellerReports: { type: Array, default: () => [] },
    auditLogs: { type: Array, default: () => [] },
})
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-2">
        <article class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">VerKäufer</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Bestand und Angebote</h2>
            </div>
            <div class="divide-y divide-border">
                <div v-for="report in sellerReports" :key="report.product_id" class="flex items-center justify-between gap-3 p-4">
                    <div>
                        <p class="font-semibold text-primary">{{ report.title }}</p>
                        <p class="text-xs text-secondary">{{ report.seller || 'Airmius' }} - {{ report.status }}</p>
                    </div>
                    <span :class="['rounded-full px-2 py-1 text-xs font-semibold', report.low_stock ? 'bg-warning/10 text-warning' : 'bg-muted text-secondary']">
                        Bestand {{ report.manages_stock ? report.stock_quantity : 'frei' }}
                    </span>
                    <div v-if="report.inventories?.length" class="mt-2 flex flex-wrap gap-1">
                        <span v-for="inventory in report.inventories" :key="`${report.product_id}-${inventory.country_code}`" class="rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary">
                            {{ inventory.country_code }} {{ inventory.available_quantity }}
                        </span>
                    </div>
                </div>
                <p v-if="!sellerReports.length" class="p-5 text-sm text-secondary">Noch keine VerKäuferdaten.</p>
            </div>
        </article>

        <article class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Audit</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Commerce-Audit-Log</h2>
            </div>
            <div class="divide-y divide-border">
                <div v-for="entry in auditLogs" :key="entry.id" class="p-4">
                    <p class="font-semibold text-primary">{{ entry.action }}</p>
                    <p class="text-xs text-secondary">{{ entry.user?.email || 'System' }} - {{ entry.created_at }}</p>
                    <p v-if="entry.note" class="mt-1 text-xs text-secondary">{{ entry.note }}</p>
                </div>
                <p v-if="!auditLogs.length" class="p-5 text-sm text-secondary">Noch keine Audit-Eintraege.</p>
            </div>
        </article>
    </section>
</template>

