<script setup>
defineProps({
    addMembersForm: { type: Object, required: true },
    availableUsersToAdd: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    selectedConversation: { type: Object, default: null },
    show: { type: Boolean, default: false },
    titleFor: { type: Function, required: true },
})

defineEmits(['close', 'submit', 'toggle-member'])
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
        <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
            <div class="border-b border-border p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Personen einladen</h2>
                        <p class="mt-1 text-sm text-secondary">{{ titleFor(selectedConversation) }}</p>
                    </div>
                    <button type="button" class="text-secondary hover:text-primary" @click="$emit('close')">
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="$emit('submit')">
                <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                    <button
                        v-for="member in availableUsersToAdd"
                        :key="member.id"
                        type="button"
                        class="mb-2 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
                        :class="addMembersForm.participant_ids.includes(member.id)
                            ? 'border-primary bg-inputBg'
                            : 'border-border hover:bg-inputBg'"
                        @click="$emit('toggle-member', member.id)"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(member.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                            <p class="truncate text-xs text-secondary">{{ member.email }}</p>
                        </div>
                        <i
                            class="las text-xl"
                            :class="addMembersForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                        ></i>
                    </button>

                    <p v-if="availableUsersToAdd.length === 0" class="p-6 text-center text-sm text-secondary">
                        Keine weiteren Personen verfügbar.
                    </p>
                </div>

                <div class="border-t border-border p-3">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                            @click="$emit('close')"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            :disabled="addMembersForm.processing || addMembersForm.participant_ids.length === 0"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Einladung senden
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

