<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

defineProps({
    authUser: { type: Object, default: null },
    canManageSelectedGroup: { type: Boolean, default: false },
    groupProfileForm: { type: Object, required: true },
    initials: { type: Function, required: true },
    isSelectedConversationMuted: { type: Boolean, default: false },
    ownerTransferCandidates: { type: Array, default: () => [] },
    ownerTransferForm: { type: Object, required: true },
    pendingGroupInvitations: { type: Array, default: () => [] },
    selectedConversation: { type: Object, default: null },
    selectedUsers: { type: Array, default: () => [] },
    show: { type: Boolean, default: false },
})

defineEmits(['close', 'mute', 'remove-member', 'save', 'transfer-owner'])
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
        <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
            <div class="border-b border-border p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-primary">{{ tx('chat.group_profile') }}</h2>
                        <p class="mt-1 truncate text-sm text-secondary">
                            {{ selectedConversation?.owner?.name ? tx('chat.ui.owner_named', { name: selectedConversation.owner.name }) : tx('chat.ui.group_settings') }}
                        </p>
                    </div>
                    <button type="button" class="text-secondary hover:text-primary" :aria-label="tx('chat.ui.close')" @click="$emit('close')">
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <form class="min-h-0 flex-1 overflow-y-auto p-4 custom-scrollbar" @submit.prevent="$emit('save')">
                <div class="space-y-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.name') }}</span>
                        <input
                            v-model="groupProfileForm.name"
                            type="text"
                            maxlength="120"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                            :placeholder="tx('chat.ui.group_name_placeholder')"
                            :disabled="!canManageSelectedGroup"
                        >
                        <p v-if="groupProfileForm.errors.name" class="mt-1 text-sm text-error">{{ groupProfileForm.errors.name }}</p>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.description') }}</span>
                        <textarea
                            v-model="groupProfileForm.description"
                            rows="4"
                            maxlength="500"
                            class="mt-1 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                            :placeholder="tx('chat.ui.group_description_placeholder')"
                            :disabled="!canManageSelectedGroup"
                        />
                        <p v-if="groupProfileForm.errors.description" class="mt-1 text-sm text-error">{{ groupProfileForm.errors.description }}</p>
                    </label>

                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.notifications') }}</p>
                        <p class="mt-1 text-sm text-primary">
                            {{ isSelectedConversationMuted ? tx('chat.ui.muted_notice') : tx('chat.ui.notifications_active') }}
                        </p>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="$emit('mute', 60)">
                                {{ tx('chat.ui.mute_hour') }}
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="$emit('mute', 480)">
                                {{ tx('chat.ui.mute_eight_hours') }}
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="$emit('mute', 10080)">
                                {{ tx('chat.ui.mute_week') }}
                            </button>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-card" @click="$emit('mute', 0)">
                                {{ tx('chat.ui.active') }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border p-3">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.members') }}</p>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="member in selectedUsers"
                                :key="member.id"
                                class="flex items-center gap-3 rounded-lg bg-inputBg px-3 py-2"
                            >
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                    {{ initials(member.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                    <p v-if="member.id === selectedConversation?.owner_id" class="text-xs text-secondary">{{ tx('chat.ui.owner') }}</p>
                                </div>
                                <button
                                    v-if="canManageSelectedGroup && member.id !== authUser?.id && member.id !== selectedConversation?.owner_id"
                                    type="button"
                                    class="rounded-lg border border-danger/40 px-2 py-1 text-xs font-semibold text-danger"
                                    @click="$emit('remove-member', member)"
                                >
                                    {{ tx('chat.ui.remove') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="pendingGroupInvitations.length" class="rounded-lg border border-border p-3">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.pending_invitations') }}</p>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="invitation in pendingGroupInvitations"
                                :key="invitation.id"
                                class="flex items-center justify-between gap-3 rounded-lg bg-inputBg px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-primary">
                                        {{ invitation.recipient?.name || invitation.recipient?.email || tx('chat.ui.invited_member') }}
                                    </p>
                                    <p class="truncate text-xs text-secondary">
                                        {{ tx('chat.invited_by') }} {{ invitation.inviter?.name || tx('chat.member') }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-secondary">{{ tx('chat.ui.pending') }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="canManageSelectedGroup && ownerTransferCandidates.length" class="rounded-lg border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tx('chat.ui.transfer_owner') }}</p>
                        <div class="mt-3 flex gap-2">
                            <select
                                v-model="ownerTransferForm.user_id"
                                class="min-w-0 flex-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary"
                            >
                                <option :value="null">{{ tx('chat.ui.choose_member') }}</option>
                                <option v-for="member in ownerTransferCandidates" :key="member.id" :value="member.id">
                                    {{ member.name }}
                                </option>
                            </select>
                            <button
                                type="button"
                                :disabled="ownerTransferForm.processing || !ownerTransferForm.user_id"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                @click="$emit('transfer-owner')"
                            >
                                    {{ tx('chat.ui.transfer') }}
                            </button>
                        </div>
                    </div>

                    <p v-if="!canManageSelectedGroup" class="text-sm text-secondary">
                        {{ tx('chat.ui.owner_only') }}
                    </p>
                </div>
            </form>

            <div class="flex gap-2 border-t border-border p-3">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                    @click="$emit('close')"
                >
                    {{ tx('chat.ui.close') }}
                </button>
                <button
                    v-if="canManageSelectedGroup"
                    type="button"
                    :disabled="groupProfileForm.processing"
                    class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                    @click="$emit('save')"
                >
                    {{ tx('chat.ui.save') }}
                </button>
            </div>
        </div>
    </div>
</template>
