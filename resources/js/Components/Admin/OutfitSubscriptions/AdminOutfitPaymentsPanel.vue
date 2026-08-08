<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    subscriptions: { type: Array, default: () => [] },
    paymentModal: { type: Object, required: true },
    shippingAddressModal: { type: Object, required: true },
    cancelSubscriptionModal: { type: Object, required: true },
    deleteSubscriptionModal: { type: Object, required: true },
    shippingAddressForm: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    shippingAddressLine: { type: Function, required: true },
    statusLabel: { type: Function, required: true },
    issueTypeLabel: { type: Function, required: true },
    issueStatusLabel: { type: Function, required: true },
    paymentStatusLabel: { type: Function, required: true },
    paymentProviderLabel: { type: Function, required: true },
    badgeClass: { type: Function, required: true },
    openDeliveryModal: { type: Function, required: true },
    openPaymentModal: { type: Function, required: true },
    closePaymentModal: { type: Function, required: true },
    markSubscriptionPaid: { type: Function, required: true },
    remindPayment: { type: Function, required: true },
    markPaymentOpen: { type: Function, required: true },
    openShippingAddressModal: { type: Function, required: true },
    closeShippingAddressModal: { type: Function, required: true },
    saveShippingAddress: { type: Function, required: true },
    openCancelSubscriptionModal: { type: Function, required: true },
    closeCancelSubscriptionModal: { type: Function, required: true },
    cancelSubscription: { type: Function, required: true },
    openDeleteSubscriptionModal: { type: Function, required: true },
    closeDeleteSubscriptionModal: { type: Function, required: true },
    deleteSubscription: { type: Function, required: true },
})
</script>
<template>
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-accent">Zahlungen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">Outfit-Abos verwalten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Hier sehen Marketplace- und Abo-Verantwortliche, wer bezahlt hat und welche Zahlungen noch offen sind.
                    </p>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-sm font-semibold text-secondary">
                    {{ subscriptions.length }} letzte Einträge
                </span>
            </div>

            <div v-if="subscriptions.length" class="mt-5 overflow-hidden rounded-lg border border-border">
                <div class="hidden grid-cols-[minmax(12rem,1.2fr)_minmax(10rem,1fr)_8rem_8rem_10rem_12rem_9rem] gap-3 border-b border-border bg-inputBg px-4 py-3 text-xs font-semibold uppercase text-secondary lg:grid">
                    <span>Kunde</span>
                    <span>Plan</span>
                    <span>Status</span>
                    <span>Zahlung</span>
                    <span>{{ t('outfit_admin.ui.delivery_label') }}</span>
                    <span>Referenz</span>
                    <span class="text-end">Aktion</span>
                </div>

                <article
                    v-for="subscription in subscriptions"
                    :key="subscription.id"
                    class="grid gap-4 border-b border-border px-4 py-4 last:border-b-0 lg:grid-cols-[minmax(12rem,1.2fr)_minmax(10rem,1fr)_8rem_8rem_10rem_12rem_9rem] lg:items-center"
                >
                    <div>
                        <p class="font-semibold text-primary">{{ subscription.user?.name || 'Unbekannter Kunde' }}</p>
                        <p class="mt-1 break-all text-xs text-secondary">{{ subscription.user?.email }}</p>
                        <p class="mt-1 text-xs text-secondary">Anfrage: {{ formatDate(subscription.created_at) }}</p>
                        <p v-if="subscription.shipping_address" class="mt-2 text-xs text-secondary">
                            {{ subscription.shipping_address.name || 'Lieferadresse' }} - {{ shippingAddressLine(subscription.shipping_address) || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="font-semibold text-primary">{{ subscription.plan?.name || 'Plan gelöscht' }}</p>
                        <p v-if="subscription.sponsor" class="mt-1 text-xs text-accent">Sponsor: {{ subscription.sponsor.name }}</p>
                        <p class="mt-1 text-sm font-semibold text-primary">{{ formatMoney(subscription.total_cents, subscription.currency) }}</p>
                    </div>

                    <div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(subscription.status)">
                            {{ statusLabel(subscription.status) }}
                        </span>
                    </div>

                    <div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="badgeClass(subscription.payment_status)">
                            {{ paymentStatusLabel(subscription.payment_status) }}
                        </span>
                        <p class="mt-1 text-xs text-secondary">{{ paymentProviderLabel(subscription.payment_provider) }}</p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            Erinnerung: {{ subscription.last_payment_reminder_sent_at ? formatDate(subscription.last_payment_reminder_sent_at) : 'Noch nie' }}
                        </p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            {{ subscription.payment_reminders_sent || 0 }}/3 gesendet
                        </p>
                        <p v-if="subscription.dunning_level" class="mt-1 text-xs text-amber-200">
                            Mahnstufe {{ subscription.dunning_level }}/3
                        </p>
                        <p v-if="subscription.last_dunning_sent_at" class="mt-1 text-xs text-secondary">
                            Letzte Mahnung: {{ formatDate(subscription.last_dunning_sent_at) }}
                        </p>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold transition hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="badgeClass(subscription.latest_delivery?.status)"
                            :disabled="!subscription.latest_delivery"
                            @click="openDeliveryModal(subscription)"
                        >
                            {{ subscription.latest_delivery ? statusLabel(subscription.latest_delivery.status) : 'Keine' }}
                        </button>
                        <p v-if="subscription.latest_delivery" class="mt-1 text-xs text-secondary">
                            {{ formatDate(subscription.latest_delivery.delivery_month) }}
                        </p>
                        <p v-if="subscription.latest_delivery?.tracking_number" class="mt-1 break-all text-xs text-secondary">
                            {{ subscription.latest_delivery.carrier || 'Tracking' }}: {{ subscription.latest_delivery.tracking_number }}
                        </p>
                        <a v-if="subscription.latest_delivery?.tracking_url" :href="subscription.latest_delivery.tracking_url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex break-all text-xs font-semibold text-accent underline underline-offset-2">
                            Tracking öffnen
                        </a>
                        <p v-if="subscription.latest_delivery?.issue" class="mt-2 rounded-lg border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-xs font-semibold text-amber-200">
                            {{ issueTypeLabel(subscription.latest_delivery.issue.type) }}: {{ issueStatusLabel(subscription.latest_delivery.issue.status) }}
                        </p>
                    </div>

                    <div class="text-sm">
                        <p class="font-semibold text-primary">{{ subscription.payment_reference || 'Keine Referenz' }}</p>
                        <p class="mt-1 text-xs text-secondary">Fällig: {{ formatDate(subscription.payment_due_at) }}</p>
                        <p v-if="subscription.payment_status !== 'paid'" class="mt-1 text-xs text-secondary">
                            Autom. Löschung: {{ formatDate(subscription.payment_expires_at) }}
                        </p>
                        <p v-if="subscription.bank_transfer?.iban" class="mt-1 break-all text-xs text-secondary">
                            IBAN: {{ subscription.bank_transfer.iban }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 lg:items-end">
                        <button
                            v-if="subscription.payment_status !== 'paid' && subscription.status !== 'cancelled'"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90"
                            @click="openPaymentModal(subscription)"
                        >
                            Bezahlt markieren
                        </button>
                        <button
                            v-if="subscription.payment_status !== 'paid'"
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!subscription.can_send_payment_reminder"
                            @click="remindPayment(subscription)"
                        >
                            Erinnern
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                            @click="openShippingAddressModal(subscription)"
                        >
                            Adresse
                        </button>
                        <button
                            v-if="subscription.payment_status === 'paid' && subscription.status === 'active'"
                            type="button"
                            class="rounded-lg border border-amber-400/50 px-3 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-400/10"
                            @click="markPaymentOpen(subscription)"
                        >
                            Zahlung offen
                        </button>
                        <button
                            v-if="subscription.status !== 'cancelled'"
                            type="button"
                            class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10"
                            @click="openCancelSubscriptionModal(subscription)"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10"
                            @click="openDeleteSubscriptionModal(subscription)"
                        >
                            Löschen
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-border bg-inputBg p-6 text-center text-secondary">
                Noch keine Outfit-Abo-Anfragen vorhanden.
            </div>
        </section>

        <Teleport to="body">
            <div v-if="paymentModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Zahlung bestätigen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ paymentModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Markiere die Zahlung erst als bezahlt, wenn der Betrag wirklich eingegangen ist. Danach wird das Abo aktiviert und der Kunde benachrichtigt.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closePaymentModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="mt-4 rounded-lg border border-border bg-inputBg p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-secondary">Kunde</span>
                            <span class="text-end font-semibold text-primary">{{ paymentModal.subscription?.user?.name }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-secondary">Betrag</span>
                            <span class="font-semibold text-primary">{{ formatMoney(paymentModal.subscription?.total_cents, paymentModal.subscription?.currency) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-secondary">Referenz</span>
                            <span class="text-end font-semibold text-primary">{{ paymentModal.subscription?.payment_reference }}</span>
                        </div>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Interne Notiz</span>
                        <textarea
                            v-model="paymentModal.note"
                            rows="3"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Optional, z.B. Zahlung am Kontoauszug geprüft."
                        ></textarea>
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closePaymentModal">
                            Abbrechen
                        </button>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="markSubscriptionPaid">
                            Zahlung bestätigen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="shippingAddressModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl rounded-lg border border-border bg-card shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-border p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-accent">Lieferadresse bearbeiten</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ shippingAddressModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ shippingAddressModal.subscription?.user?.name || 'Kunde' }} - {{ shippingAddressModal.subscription?.payment_reference || 'Keine Referenz' }}
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeShippingAddressModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid max-h-[75vh] gap-4 overflow-y-auto p-5 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">{{ t('outfit_ui.name') }}</span>
                            <input v-model="shippingAddressForm.shipping_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Vor- und Nachname">
                            <span v-if="shippingAddressForm.errors.shipping_name" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_name }}</span>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Straße</span>
                            <input v-model="shippingAddressForm.shipping_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Straße">
                            <span v-if="shippingAddressForm.errors.shipping_street" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_street }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Hausnummer</span>
                            <input v-model="shippingAddressForm.shipping_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="12a">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Land</span>
                            <input v-model="shippingAddressForm.shipping_country" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary" placeholder="DE">
                            <span v-if="shippingAddressForm.errors.shipping_country" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_country }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">PLZ</span>
                            <input v-model="shippingAddressForm.shipping_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="12345">
                            <span v-if="shippingAddressForm.errors.shipping_postal_code" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_postal_code }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Stadt</span>
                            <input v-model="shippingAddressForm.shipping_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Berlin">
                            <span v-if="shippingAddressForm.errors.shipping_city" class="mt-1 block text-xs text-red-300">{{ shippingAddressForm.errors.shipping_city }}</span>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Bundesland / Region</span>
                            <input v-model="shippingAddressForm.shipping_state" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional">
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-primary">Lieferhinweis</span>
                            <textarea v-model="shippingAddressForm.shipping_note" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Optional, z.B. bei Nachbarn abgeben"></textarea>
                        </label>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border p-5 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeShippingAddressModal">
                            Abbrechen
                        </button>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90 disabled:opacity-50" :disabled="shippingAddressForm.processing" @click="saveShippingAddress">
                            Adresse speichern
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="cancelSubscriptionModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Abo-Anfrage abbrechen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ cancelSubscriptionModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Die Anfrage wird beendet und der Kunde bekommt eine In-App-Benachrichtigung.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCancelSubscriptionModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-primary">Grund für den Kunden</span>
                        <textarea
                            v-model="cancelSubscriptionModal.reason"
                            rows="3"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Optional, z.B. Zahlung nicht eingegangen."
                        ></textarea>
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeCancelSubscriptionModal">
                            Zurück
                        </button>
                        <button type="button" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90" @click="cancelSubscription">
                            Anfrage abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="deleteSubscriptionModal.open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-300">Outfit-Abo löschen</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ deleteSubscriptionModal.subscription?.plan?.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                            Das Abo von {{ deleteSubscriptionModal.subscription?.user?.name || 'diesem Kunden' }} wird dauerhaft entfernt. Zugehörige Lieferungen werden ebenfalls gelöscht.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeDeleteSubscriptionModal">
                            <i class="las la-times text-xl"></i>
                        </button>
                    </div>

                    <label class="mt-4 block">
                            <span class="text-sm font-semibold text-primary">Zur Bestätigung delete eingeben</span>
                        <input
                            v-model="deleteSubscriptionModal.confirmation"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="delete"
                        />
                    </label>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="closeDeleteSubscriptionModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500/90 disabled:opacity-50"
                            :disabled="deleteSubscriptionModal.confirmation !== 'delete'"
                            @click="deleteSubscription"
                        >
                            Endgültig löschen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

</template>
