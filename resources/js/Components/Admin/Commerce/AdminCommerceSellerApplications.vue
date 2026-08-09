<script setup>
defineProps({
    formatDateTime: { type: Function, required: true },
    sellerApplications: { type: Array, default: () => [] },
    sellerApplicationStatusLabel: { type: Function, required: true },
})

const emit = defineEmits(['update-seller-application'])
</script>

<template>
    <article class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Shop-Zugang</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">Verkäufer-Anträge</h2>
            <p class="mt-1 text-sm text-secondary">Erst freigegebene Nutzer können eigene Marketplace-Produkte erstellen.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-start text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="application in sellerApplications" :key="application.id">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ application.user?.name || application.user?.email }}</p>
                            <p class="text-xs text-secondary">{{ application.user?.email || '-' }} &middot; {{ application.business_name || application.applicant_type }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">
                            <p class="font-semibold text-primary">{{ sellerApplicationStatusLabel(application.status) }}</p>
                            <p class="text-xs text-secondary">Beantragt: {{ formatDateTime(application.created_at) }}</p>
                            <div v-if="application.readiness" class="mt-2 rounded-lg border border-border bg-bg p-2">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-semibold text-primary">Readiness {{ application.readiness.score || 0 }}%</span>
                                    <span :class="application.readiness.can_approve ? 'text-success' : 'text-warning'">
                                        {{ application.readiness.required_done || 0 }}/{{ application.readiness.required_total || 0 }} Pflicht
                                    </span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${Math.min(100, Math.max(0, application.readiness.score || 0))}%` }"></div>
                                </div>
                                <p v-if="application.readiness.blocks?.length" class="mt-1 text-xs text-warning">
                                    Offen: {{ application.readiness.blocks.join(', ') }}
                                </p>
                            </div>
                            <p v-if="application.review_note" class="mt-1 text-xs text-warning">{{ application.review_note }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">
                            <p v-if="application.notes">{{ application.notes }}</p>
                            <p v-else>-</p>
                        </td>
                        <td class="px-5 py-3 text-end">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button
                                    v-if="application.status !== 'approved'"
                                    type="button"
                                    class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="application.verification_version && !application.readiness?.can_approve"
                                    :title="application.verification_version && !application.readiness?.can_approve ? 'Pflichtangaben und Auszahlungsprüfung fehlen.' : ''"
                                    @click="emit('update-seller-application', application, 'approved')"
                                >
                                    Freigeben
                                </button>
                                <button
                                    v-if="application.status !== 'rejected'"
                                    type="button"
                                    class="rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning"
                                    @click="emit('update-seller-application', application, 'rejected')"
                                >
                                    Ablehnen
                                </button>
                                <button
                                    v-if="application.status !== 'pending'"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                    @click="emit('update-seller-application', application, 'pending')"
                                >
                                    Zurück auf Prüfung
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!sellerApplications.length" class="px-5 py-6 text-sm text-secondary">Noch keine Shop-Anträge.</p>
        </div>
    </article>
</template>
