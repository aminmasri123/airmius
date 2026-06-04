<script setup>
defineProps({
    leaveConversationForm: { type: Object, required: true },
    remainingMembersAfterLeave: { type: Number, default: 0 },
    selectedConversation: { type: Object, default: null },
    show: { type: Boolean, default: false },
    titleFor: { type: Function, required: true },
})

defineEmits(['close', 'confirm'])
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-md rounded-lg bg-card shadow-xl">
            <div class="border-b border-border p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Gruppe verlassen?</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Du wirst aus "{{ titleFor(selectedConversation) }}" entfernt und siehst danach keine neuen Nachrichten mehr.
                        </p>
                    </div>
                    <button type="button" class="text-secondary hover:text-primary" @click="$emit('close')">
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="space-y-4 p-4">
                <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                    Nach deinem Austritt bleiben
                    <span class="font-semibold text-primary">{{ remainingMembersAfterLeave }}</span>
                    Mitglied(er) in dieser Gruppe.
                </div>

                <label
                    v-if="remainingMembersAfterLeave <= 1"
                    class="flex gap-3 rounded-lg border border-border p-3 text-sm text-primary"
                >
                    <input
                        v-model="leaveConversationForm.delete_conversation"
                        type="checkbox"
                        class="mt-1 rounded border-border text-buttonPrimary focus:ring-buttonPrimary"
                    >
                    <span>
                        Gruppe direkt löschen, weil danach höchstens eine Person übrig bleibt.
                        Die Conversation wird dadurch für alle verbleibenden Mitglieder entfernt.
                    </span>
                </label>
            </div>

            <div class="flex gap-2 border-t border-border p-4">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                    @click="$emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    :disabled="leaveConversationForm.processing"
                    class="flex-1 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="$emit('confirm')"
                >
                    {{ leaveConversationForm.delete_conversation ? 'Verlassen und löschen' : 'Gruppe verlassen' }}
                </button>
            </div>
        </div>
    </div>
</template>

