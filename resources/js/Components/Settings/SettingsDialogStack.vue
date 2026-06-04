<script setup>
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'

defineProps({
    bankTransferModal: { type: Object, required: true },
    bankTransferRows: { type: Function, required: true },
    closeBankTransferModal: { type: Function, required: true },
    closeDisconnectIntegrationModal: { type: Function, required: true },
    closeOpenPaymentModal: { type: Function, required: true },
    closeSportActivityDeleteModal: { type: Function, required: true },
    closeSportActivityEditModal: { type: Function, required: true },
    closeSportProfileRemoveModal: { type: Function, required: true },
    closeSubscriptionCancelModal: { type: Function, required: true },
    confirmOpenPaymentAction: { type: Function, required: true },
    confirmSportActivityDelete: { type: Function, required: true },
    confirmSportProfileRemove: { type: Function, required: true },
    confirmSubscriptionCancel: { type: Function, required: true },
    disconnectIntegration: { type: Function, required: true },
    disconnectIntegrationModal: { type: Object, required: true },
    openPaymentModal: { type: Object, required: true },
    openPaymentModalConfirmText: { type: Function, required: true },
    openPaymentModalMessage: { type: Function, required: true },
    openPaymentModalTitle: { type: Function, required: true },
    settingsText: { type: Function, required: true },
    sportActivityDeleteMessage: { type: Function, required: true },
    sportActivityDeleteModal: { type: Object, required: true },
    sportActivityDeleteTitle: { type: Function, required: true },
    sportActivityEditForm: { type: Object, required: true },
    sportActivityEditModal: { type: Object, required: true },
    sportProfileRemoveModal: { type: Object, required: true },
    sportProfileText: { type: Function, required: true },
    subscriptionCancelModal: { type: Object, required: true },
    subscriptionCancelModalMessage: { type: Function, required: true },
    updateSportActivityTitle: { type: Function, required: true },
})
</script>

<template>
    <DeleteConfirmModal
        :show="sportProfileRemoveModal.show"
        :title="sportProfileText('remove_sport', 'Sportart entfernen')"
        :message="sportProfileText('remove_message', 'Möchtest du {sport} aus deinem Sportprofil entfernen? Die hinterlegten Leistungsdaten werden gelöscht.', { sport: sportProfileRemoveModal.profile?.sport?.name || sportProfileText('this_sport', 'diese Sportart') })"
        :confirm-text="sportProfileText('remove_confirm', 'entfernen')"
        :cancel-text="sportProfileText('back', 'Zurück')"
        @confirm="confirmSportProfileRemove"
        @cancel="closeSportProfileRemoveModal"
    />

    <DeleteConfirmModal
        :show="openPaymentModal.show"
        :title="openPaymentModalTitle()"
        :message="openPaymentModalMessage()"
        :confirm-text="openPaymentModalConfirmText()"
        :cancel-text="settingsText('actions.back', 'Zurück')"
        @confirm="confirmOpenPaymentAction"
        @cancel="closeOpenPaymentModal"
    />

    <DeleteConfirmModal
        :show="subscriptionCancelModal.show"
        :title="settingsText('billing.cancel_subscription.title', 'Abo kündigen')"
        :message="subscriptionCancelModalMessage()"
        :confirm-text="settingsText('billing.cancel_subscription.confirm', 'kündigen')"
        :cancel-text="settingsText('actions.back', 'Zurück')"
        @confirm="confirmSubscriptionCancel"
        @cancel="closeSubscriptionCancelModal"
    />

    <DeleteConfirmModal
        :show="disconnectIntegrationModal.show"
        :title="settingsText('integrations.disconnect.title', 'Sport-App entfernen')"
        :message="settingsText('integrations.disconnect.message', 'Bist du sicher, dass du diese Sport-App-Verknüpfung entfernen möchtest? Gespeicherte Tokens werden gelöscht und die App muss danach neu verbunden werden.')"
        :confirm-text="settingsText('actions.remove', 'Entfernen')"
        :cancel-text="settingsText('actions.cancel', 'Abbrechen')"
        @confirm="disconnectIntegration(disconnectIntegrationModal.account)"
        @cancel="closeDisconnectIntegrationModal"
    />

    <DeleteConfirmModal
        :show="sportActivityDeleteModal.show"
        :title="sportActivityDeleteTitle()"
        :message="sportActivityDeleteMessage()"
        :confirm-text="settingsText('actions.delete', 'Löschen')"
        :cancel-text="settingsText('actions.back', 'Zurück')"
        @confirm="confirmSportActivityDelete"
        @cancel="closeSportActivityDeleteModal"
    />

    <div
        v-if="sportActivityEditModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
        @click.self="closeSportActivityEditModal"
    >
        <form
            class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl"
            @submit.prevent="updateSportActivityTitle"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.activities.rename_title', 'Aktivität umbenennen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('integrations.activities.rename_description', 'Der neue Name wird nur in Airmius gespeichert.') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeSportActivityEditModal"
                >
                    {{ settingsText('actions.close', 'Schließen') }}
                </button>
            </div>

            <label class="mt-5 block text-sm font-semibold text-primary" for="sport-activity-title">
                {{ settingsText('integrations.manual_activity.name', 'Name') }}
            </label>
            <input
                id="sport-activity-title"
                v-model="sportActivityEditForm.title"
                type="text"
                maxlength="120"
                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                required
            />
            <p v-if="sportActivityEditForm.errors.title" class="mt-2 text-sm text-danger">
                {{ sportActivityEditForm.errors.title }}
            </p>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                    @click="closeSportActivityEditModal"
                >
                    {{ settingsText('actions.cancel', 'Abbrechen') }}
                </button>
                <button
                    type="submit"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                    :disabled="sportActivityEditForm.processing"
                >
                    {{ settingsText('actions.save', 'Speichern') }}
                </button>
            </div>
        </form>
    </div>

    <div
        v-if="bankTransferModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
        @click.self="closeBankTransferModal"
    >
        <div class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.bank_transfer_title', 'Per Überweisung zahlen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('billing.bank_transfer_description', 'Nutze diese Daten für deine Banküberweisung.') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                    @click="closeBankTransferModal"
                >
                    {{ settingsText('actions.close', 'Schließen') }}
                </button>
            </div>

            <dl class="mt-5 divide-y divide-border rounded-lg border border-border bg-card">
                <div
                    v-for="[label, value] in bankTransferRows()"
                    :key="label"
                    class="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[150px_1fr]"
                >
                    <dt class="text-secondary">{{ label }}</dt>
                    <dd class="break-words font-semibold text-primary sm:text-right">{{ value }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>




