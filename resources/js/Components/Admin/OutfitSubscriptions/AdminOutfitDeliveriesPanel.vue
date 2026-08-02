<script setup>
defineProps({
    deliveries: { type: Array, default: () => [] },
    deliveryModal: { type: Object, required: true },
    deleteDeliveryModal: { type: Object, required: true },
    shippingAddressLine: { type: Function, required: true },
    issueTypeLabel: { type: Function, required: true },
    issueStatusLabel: { type: Function, required: true },
    badgeClass: { type: Function, required: true },
    statusLabel: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formForDelivery: { type: Function, required: true },
    saveDelivery: { type: Function, required: true },
    markDeliveryShipped: { type: Function, required: true },
    markDeliveryDelivered: { type: Function, required: true },
    saveDeliveryIssue: { type: Function, required: true },
    openDeleteDeliveryModal: { type: Function, required: true },
    closeDeliveryModal: { type: Function, required: true },
    saveDeliveryModal: { type: Function, required: true },
    markDeliveryModalShipped: { type: Function, required: true },
    markDeliveryModalDelivered: { type: Function, required: true },
    closeDeleteDeliveryModal: { type: Function, required: true },
    deleteDelivery: { type: Function, required: true },
})
</script>
<template>
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Lieferungen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Outfit-Abo Lieferungen verwalten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Plane Boxen, pflege Paketdienst und Trackingnummer und markiere Lieferungen als versendet oder geliefert.
                    </p>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-sm font-semibold text-secondary">
                    {{ deliveries.length }} letzte Lieferungen
                </span>
            </div>

            <div v-if="deliveries.length" class="mt-5 space-y-4">
                <article v-for="delivery in deliveries" :key="delivery.id" class="rounded-lg border border-border bg-inputBg p-4">
                    <div class="grid gap-4 xl:grid-cols-[minmax(14rem,1.2fr)_minmax(12rem,1fr)_minmax(18rem,1.4fr)_minmax(15rem,1fr)_auto] xl:items-start">
                        <div>
                            <p class="font-semibold text-primary">{{ delivery.subscription?.user?.name || 'Unbekannter Kunde' }}</p>
                            <p class="mt-1 break-all text-xs text-secondary">{{ delivery.subscription?.user?.email || '-' }}</p>
                            <p class="mt-2 text-sm font-semibold text-primary">{{ delivery.subscription?.plan?.name || 'Plan gelöscht' }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ delivery.subscription?.payment_reference || 'Keine Referenz' }}</p>
                            <p v-if="delivery.subscription?.shipping_address" class="mt-2 text-xs text-secondary">
                                {{ delivery.subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(delivery.subscription.shipping_address) || '-' }}
                            </p>
                            <div v-if="delivery.issue" class="mt-3 rounded-lg border border-amber-400/30 bg-amber-400/10 p-3">
                                <p class="text-xs font-semibold uppercase text-amber-200">{{ issueTypeLabel(delivery.issue.type) }}</p>
                                <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(delivery.issue.status) }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ delivery.issue.description }}</p>
                                <p v-if="delivery.issue.exchange_size" class="mt-1 text-xs text-secondary">Grüße: {{ delivery.issue.exchange_size }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(formForDelivery(delivery).status)">
                                {{ statusLabel(formForDelivery(delivery).status) }}
                            </span>
                            <select v-model="formForDelivery(delivery).status" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                                <option value="planned">Geplant</option>
                                <option value="preparing">In Vorbereitung</option>
                                <option value="shipped">Versendet</option>
                                <option value="delivered">Geliefert</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                            <input v-model="formForDelivery(delivery).delivery_month" type="date" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            <input v-model="formForDelivery(delivery).carrier" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Paketdienst, z.B. DHL">
                            <input v-model="formForDelivery(delivery).tracking_number" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Trackingnummer">
                            <input v-model="formForDelivery(delivery).tracking_url" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Tracking-Link https://...">
                            <a v-if="delivery.tracking_url" :href="delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="break-all text-xs font-semibold text-accent underline underline-offset-2">
                                Tracking öffnen
                            </a>
                            <div class="text-xs text-secondary sm:col-span-2 xl:col-span-1">
                                <p>Versendet: {{ formatDate(delivery.shipped_at) }}</p>
                                <p>Geliefert: {{ formatDate(delivery.delivered_at) }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <textarea v-model="formForDelivery(delivery).items_text" rows="3" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Artikel je Zeile, z.B. Laufshirt M"></textarea>
                            <textarea v-model="formForDelivery(delivery).notes" rows="3" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Interne Notiz"></textarea>
                            <template v-if="delivery.issue">
                                <select v-model="formForDelivery(delivery).issue_status" class="rounded-lg border-border bg-card text-sm text-primary">
                                    <option value="open">Offen</option>
                                    <option value="reviewing">In Prüfung</option>
                                    <option value="approved">Freigegeben</option>
                                    <option value="return_waiting">Rücksendung offen</option>
                                    <option value="replacement_preparing">Ersatz wird vorbereitet</option>
                                    <option value="resolved">Gelöst</option>
                                    <option value="rejected">Abgeschlossen</option>
                                </select>
                                <input v-model="formForDelivery(delivery).return_tracking_number" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Retouren-Trackingnummer">
                                <input v-model="formForDelivery(delivery).return_tracking_url" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Retouren-Link https://...">
                                <textarea v-model="formForDelivery(delivery).issue_admin_note" rows="2" class="rounded-lg border-border bg-card text-sm text-primary" placeholder="Antwort / interne Support-Notiz"></textarea>
                            </template>
                        </div>

                        <div class="flex flex-wrap gap-2 xl:flex-col xl:items-end">
                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForDelivery(delivery).processing" @click="saveDelivery(delivery)">
                                Speichern
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryShipped(delivery)">
                                Versendet
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryDelivered(delivery)">
                                Geliefert
                            </button>
                            <button v-if="delivery.issue" type="button" class="rounded-lg border border-amber-400/50 px-3 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10" @click="saveDeliveryIssue(delivery)">
                                Support speichern
                            </button>
                            <button type="button" class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10" @click="openDeleteDeliveryModal(delivery)">
                                Löschen
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-border bg-inputBg p-6 text-center text-secondary">
                Noch keine Outfit-Lieferungen vorhanden.
            </div>
        </section>


        <Teleport to="body">
            <div v-if="deliveryModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-3xl overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferstatus bearbeiten</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deliveryModal.subscription?.plan?.name || 'Outfit-Lieferung' }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            {{ deliveryModal.subscription?.user?.name || 'Kunde' }} - {{ deliveryModal.subscription?.payment_reference || 'Keine Referenz' }}
                        </p>
                        <p v-if="deliveryModal.subscription?.shipping_address" class="mt-2 text-xs leading-5 text-secondary">
                            {{ deliveryModal.subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(deliveryModal.subscription.shipping_address) || '-' }}
                        </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeliveryModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid max-h-[75vh] gap-4 overflow-y-auto p-5 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Lieferstatus</span>
                            <select v-model="formForDelivery(deliveryModal.delivery).status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="planned">Geplant</option>
                                <option value="preparing">In Vorbereitung</option>
                                <option value="shipped">Versendet</option>
                                <option value="delivered">Geliefert</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Liefermonat</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).delivery_month" type="date" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Paketdienst</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).carrier" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z.B. DHL">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Trackingnummer</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).tracking_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Trackingnummer">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Tracking-Link</span>
                            <input v-model="formForDelivery(deliveryModal.delivery).tracking_url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://...">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Artikel in der Lieferung</span>
                            <textarea v-model="formForDelivery(deliveryModal.delivery).items_text" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Ein Artikel pro Zeile"></textarea>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Notiz</span>
                            <textarea v-model="formForDelivery(deliveryModal.delivery).notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Interne Notiz"></textarea>
                        </label>

                        <div v-if="deliveryModal.delivery?.issue" class="rounded-lg border border-amber-400/30 bg-amber-400/10 p-4 md:col-span-2">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-amber-200">{{ issueTypeLabel(deliveryModal.delivery.issue.type) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ issueStatusLabel(deliveryModal.delivery.issue.status) }}</p>
                                    <p class="mt-2 text-sm text-secondary">{{ deliveryModal.delivery.issue.description }}</p>
                                    <p v-if="deliveryModal.delivery.issue.requested_resolution" class="mt-1 text-xs text-secondary">Wunsch: {{ deliveryModal.delivery.issue.requested_resolution }}</p>
                                    <p v-if="deliveryModal.delivery.issue.exchange_size" class="mt-1 text-xs text-secondary">Grüße: {{ deliveryModal.delivery.issue.exchange_size }}</p>
                                </div>
                                <span class="rounded-full border border-amber-400/40 px-3 py-1 text-xs font-semibold text-amber-200">
                                    {{ formatDate(deliveryModal.delivery.issue.requested_at) }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Support-Status</span>
                                    <select v-model="formForDelivery(deliveryModal.delivery).issue_status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                        <option value="open">Offen</option>
                                        <option value="reviewing">In Prüfung</option>
                                        <option value="approved">Freigegeben</option>
                                        <option value="return_waiting">Rücksendung offen</option>
                                        <option value="replacement_preparing">Ersatz wird vorbereitet</option>
                                        <option value="resolved">Gelöst</option>
                                        <option value="rejected">Abgeschlossen</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-semibold text-primary">Retouren-Trackingnummer</span>
                                    <input v-model="formForDelivery(deliveryModal.delivery).return_tracking_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Retouren-Link</span>
                                    <input v-model="formForDelivery(deliveryModal.delivery).return_tracking_url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://...">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-sm font-semibold text-primary">Antwort / Support-Notiz</span>
                                    <textarea v-model="formForDelivery(deliveryModal.delivery).issue_admin_note" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Was soll der Kunde sehen?"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryModalShipped">
                                Als versendet markieren
                            </button>
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="markDeliveryModalDelivered">
                                Als geliefert markieren
                            </button>
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row">
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeliveryModal">
                                Abbrechen
                            </button>
                            <button v-if="deliveryModal.delivery?.issue" type="button" class="rounded-lg border border-amber-400/50 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10" @click="saveDeliveryIssue(deliveryModal.delivery)">
                                Support speichern
                            </button>
                            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="formForDelivery(deliveryModal.delivery).processing" @click="saveDeliveryModal">
                                Speichern
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="deleteDeliveryModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Lieferung löschen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deleteDeliveryModal.delivery?.subscription?.plan?.name || 'Outfit-Lieferung' }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Diese Lieferung wird dauerhaft entfernt. Das Outfit-Abo selbst bleibt bestehen.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeleteDeliveryModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Zur Bestätigung delete eingeben</span>
                        <input
                            v-model="deleteDeliveryModal.confirmation"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="delete"
                        />
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeleteDeliveryModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90 disabled:opacity-50"
                            :disabled="deleteDeliveryModal.confirmation !== 'delete'"
                            @click="deleteDelivery"
                        >
                            Endgültig löschen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

</template>
