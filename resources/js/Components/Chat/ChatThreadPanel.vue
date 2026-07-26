<script setup>
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

const props = defineProps({
    selectedConversation: { type: Object, default: null },
    showChatOnMobile: { type: Boolean, default: false },
    selectedUsers: { type: Array, default: () => [] },
    isSelectedConversationMuted: { type: Boolean, default: false },
    muteForm: { type: Object, required: true },
    canLeaveConversation: { type: Boolean, default: false },
    chatMessageSearch: { type: String, default: '' },
    setMessagesContainer: { type: Function, required: true },
    setAttachmentInput: { type: Function, required: true },
    messagePage: { type: Object, default: () => ({}) },
    loadingOlderMessages: { type: Boolean, default: false },
    selectedMessages: { type: Array, default: () => [] },
    activeTypingUsers: { type: Array, default: () => [] },
    messageForm: { type: Object, required: true },
    canSendMessage: { type: Boolean, default: false },
    downloadingFileIds: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    titleFor: { type: Function, required: true },
    typeLabelFor: { type: Function, required: true },
    goBackToConversations: { type: Function, required: true },
    muteSelectedConversation: { type: Function, required: true },
    openConversationSettingsModal: { type: Function, required: true },
    openAddMembersModal: { type: Function, required: true },
    openLeaveConversationModal: { type: Function, required: true },
    onMessagesScroll: { type: Function, required: true },
    loadOlderMessages: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    isSystemMessage: { type: Function, required: true },
    isOwnMessage: { type: Function, required: true },
    systemIconFor: { type: Function, required: true },
    isImageMime: { type: Function, required: true },
    isVideoMime: { type: Function, required: true },
    fileUrl: { type: Function, required: true },
    fileThumbnailUrl: { type: Function, required: true },
    fileDownloadUrl: { type: Function, required: true },
    attachmentLabel: { type: Function, required: true },
    trackDownload: { type: Function, required: true },
    fileIconFor: { type: Function, required: true },
    fileExtension: { type: Function, required: true },
    fileSizeLabel: { type: Function, required: true },
    userReaction: { type: Function, required: true },
    reactionCounts: { type: Function, required: true },
    reactToMessage: { type: Function, required: true },
    canDeleteMessage: { type: Function, required: true },
    deleteMessage: { type: Function, required: true },
    hideMessageForMe: { type: Function, required: true },
    retryMessage: { type: Function, required: true },
    cancelUpload: { type: Function, required: true },
    statusIconFor: { type: Function, required: true },
    deliverySummaryFor: { type: Function, required: true },
    openReport: { type: Function, required: true },
    openMediaPreview: { type: Function, required: true },
    sendMessage: { type: Function, required: true },
    removePendingAttachment: { type: Function, required: true },
    onAttachmentChange: { type: Function, required: true },
    announceTyping: { type: Function, required: true },
})

const emit = defineEmits(['update:chatMessageSearch'])
const openMessageActionsId = ref(null)

const chatSearch = computed({
    get: () => props.chatMessageSearch,
    set: (value) => emit('update:chatMessageSearch', value),
})
</script>

<template>
    <section
        class="min-h-0 flex-col rounded-lg border border-border bg-card"
        :class="showChatOnMobile ? 'flex' : 'hidden lg:flex'"
    >
        <div v-if="selectedConversation" class="flex items-center justify-between gap-4 border-b border-border p-4">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-inputBg lg:hidden"
                    :title="tx('chat.back')"
                    :aria-label="tx('chat.back')"
                    @click="goBackToConversations"
                >
                    <i class="las la-arrow-left text-xl"></i>
                </button>
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ tx('chat.ui.header_summary', { type: typeLabelFor(selectedConversation), count: selectedUsers.length }) }}
                        <span v-if="isSelectedConversationMuted"> - {{ tx('chat.muted') }}</span>
                    </p>
                </div>
            </div>
            <div class="hidden -space-x-2 sm:flex">
                <div
                    v-for="member in selectedUsers.slice(0, 4)"
                    :key="member.id"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-card bg-inputBg text-xs font-semibold text-primary"
                >
                    {{ initials(member.name) }}
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                    :title="isSelectedConversationMuted ? tx('chat.unmute') : tx('chat.mute')"
                    :aria-label="isSelectedConversationMuted ? tx('chat.unmute') : tx('chat.mute')"
                    :disabled="muteForm.processing"
                    @click="muteSelectedConversation(isSelectedConversationMuted ? 0 : 480)"
                >
                    <i :class="['las text-xl', isSelectedConversationMuted ? 'la-bell-slash' : 'la-bell']"></i>
                </button>
                <button
                    v-if="selectedConversation.type === 'group'"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                    :title="tx('chat.group_profile')"
                    :aria-label="tx('chat.group_profile')"
                    @click="openConversationSettingsModal"
                >
                    <i class="las la-cog text-xl"></i>
                </button>
                <button
                    v-if="selectedConversation.type === 'group'"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                    :title="tx('chat.add_people')"
                    :aria-label="tx('chat.add_people')"
                    @click="openAddMembersModal"
                >
                    <i class="las la-user-plus text-xl"></i>
                </button>
                <button
                    v-if="canLeaveConversation"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-error"
                    :title="tx('chat.leave_group')"
                    :aria-label="tx('chat.leave_group')"
                    @click="openLeaveConversationModal"
                >
                    <i class="las la-sign-out-alt text-xl"></i>
                </button>
            </div>
        </div>

        <label v-if="selectedConversation" class="mx-3 mt-3 flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 sm:mx-4">
            <i class="las la-search text-lg text-secondary"></i>
            <input
                v-model="chatSearch"
                type="search"
                :placeholder="tx('chat.message_search')"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
            >
            <button
                v-if="chatSearch"
                type="button"
                class="text-secondary hover:text-primary"
                :title="tx('chat.clear_search')"
                :aria-label="tx('chat.clear_search')"
                @click="chatSearch = ''"
            >
                <i class="las la-times"></i>
            </button>
        </label>

        <div v-if="selectedConversation" :ref="setMessagesContainer" class="custom-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4" @scroll.passive="onMessagesScroll">
            <div v-if="messagePage?.has_more || loadingOlderMessages" class="flex justify-center">
                <AppButton
                    type="button"
                    variant="secondary"
                    size="xs"
                    :loading="loadingOlderMessages"
                    :disabled="loadingOlderMessages"
                    @click="loadOlderMessages"
                >
                    {{ loadingOlderMessages ? tx('chat.loading_older') : tx('chat.load_older') }}
                </AppButton>
            </div>

            <div
                v-for="message in selectedMessages"
                :key="message.id"
                class="flex"
                :class="isSystemMessage(message) ? 'justify-center' : (isOwnMessage(message) ? 'justify-end' : 'justify-start')"
            >
                <div
                    class="relative max-w-[92%] rounded-lg px-3 py-2.5 transition sm:max-w-[82%] sm:px-4 sm:py-3"
                    :class="isSystemMessage(message)
                        ? 'border border-border bg-card text-secondary'
                        : isOwnMessage(message)
                        ? 'cursor-pointer bg-buttonPrimary text-buttonTextPrimary'
                        : 'cursor-pointer bg-inputBg text-primary'"
                    :title="isSystemMessage(message) ? undefined : tx('chat.reactions_hint')"
                    @click="!isSystemMessage(message) && (openMessageActionsId = openMessageActionsId === message.id ? null : message.id)"
                >
                    <div v-if="isSystemMessage(message)" class="flex items-center justify-center gap-2 text-center text-xs">
                        <i :class="[systemIconFor(message), 'text-base']"></i>
                        <span>{{ message.message }}</span>
                        <span class="shrink-0 opacity-70">{{ formatTime(message.created_at) }}</span>
                    </div>

                    <template v-else>
                        <div class="mb-1 flex items-center justify-between gap-4 text-xs opacity-80">
                            <span class="truncate">{{ message.sender?.name }}</span>
                            <span class="shrink-0">{{ formatTime(message.created_at) }}</span>
                        </div>
                        <p v-if="message.message" class="break-words whitespace-pre-line text-sm leading-6">{{ message.message }}</p>

                        <div v-if="message.attachments?.length" class="mt-2 space-y-1">
                            <div
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                class="overflow-hidden rounded border border-border/50 bg-card/40"
                            >
                                <button
                                    v-if="attachment.file?.id && isImageMime(attachment.file.type)"
                                    type="button"
                                    class="block w-full"
                                    :title="tx('chat.ui.view_attachment', { name: attachmentLabel(attachment) })"
                                    @click="openMediaPreview(attachment)"
                                >
                                    <img
                                        :src="fileUrl(attachment.file)"
                                        :alt="attachmentLabel(attachment)"
                                        width="960"
                                        height="720"
                                        loading="lazy"
                                        decoding="async"
                                        class="max-h-72 w-full object-cover"
                                    />
                                </button>

                                <video
                                    v-else-if="attachment.file?.id && isVideoMime(attachment.file.type)"
                                    :src="fileUrl(attachment.file)"
                                    :poster="fileThumbnailUrl(attachment.file)"
                                    controls
                                    preload="metadata"
                                    class="max-h-72 w-full bg-black"
                                ></video>

                                <a
                                    v-else-if="attachment.file"
                                    :href="attachment.file.id ? fileDownloadUrl(attachment.file) : undefined"
                                    class="flex items-center gap-3 px-3 py-2 text-xs"
                                    :class="attachment.file.id ? 'hover:bg-inputBg/70' : 'cursor-default'"
                                    :title="tx('chat.ui.download_attachment', { name: attachmentLabel(attachment) })"
                                    @click="trackDownload(attachment.file)"
                                >
                                    <i :class="[fileIconFor(attachment.file), 'text-2xl']"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ attachmentLabel(attachment) }}</span>
                                        <span class="block text-[11px] opacity-75">
                                            {{ fileExtension(attachment.file).toUpperCase() || tx('chat.file').toUpperCase() }}
                                            <span v-if="fileSizeLabel(attachment.file.size)"> - {{ fileSizeLabel(attachment.file.size) }}</span>
                                        </span>
                                    </span>
                                    <i v-if="attachment.file.id" :class="['las text-lg opacity-75', downloadingFileIds.includes(attachment.file.id) ? 'la-spinner la-spin' : 'la-download']"></i>
                                </a>
                            </div>
                        </div>

                        <div v-if="message.local_status === 'sending' && message.upload_progress !== null" class="mt-2">
                            <div class="h-1.5 overflow-hidden rounded-full bg-black/20">
                                <div
                                    class="h-full rounded-full bg-white/80 transition-all"
                                    :style="{ width: `${message.upload_progress || 8}%` }"
                                ></div>
                            </div>
                            <button
                                type="button"
                                class="mt-2 text-xs font-semibold opacity-80 hover:opacity-100"
                                @click="cancelUpload(message)"
                            >
                                {{ tx('chat.ui.cancel_upload') }}
                            </button>
                        </div>

                        <div v-if="openMessageActionsId === message.id" class="mt-2 flex justify-end text-xs">
                            <div class="w-56 max-w-full overflow-hidden rounded-xl border border-border bg-card shadow-xl">
                                <div class="grid grid-cols-3 gap-1 border-b border-border/60 p-2">
                                    <button
                                        v-for="reaction in ['like', 'heart', 'ok']"
                                        :key="reaction"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border text-xs font-semibold transition"
                                        :class="userReaction(message) === reaction ? 'border-primary bg-primary/10 text-primary' : 'border-border/60 bg-input text-secondary hover:text-primary'"
                                        :title="tx(`chat.ui.reactions.${reaction}`)"
                                        @click.stop="reactToMessage(message, reaction); openMessageActionsId = null"
                                    >
                                        <i :class="['las text-lg', reaction === 'heart' ? 'la-heart' : reaction === 'ok' ? 'la-check' : 'la-thumbs-up']"></i>
                                        <span v-if="reactionCounts(message)[reaction]" class="ml-1 opacity-70">{{ reactionCounts(message)[reaction] }}</span>
                                    </button>
                                </div>
                                <button
                                    v-if="canDeleteMessage(message)"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click.stop="deleteMessage(message); openMessageActionsId = null"
                                >
                                    <i class="las la-trash text-lg"></i>
                                    {{ tx('chat.ui.delete_for_all') }}
                                </button>
                                <button
                                    v-if="!String(message.id).startsWith('local-')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click.stop="hideMessageForMe(message); openMessageActionsId = null"
                                >
                                    <i class="las la-eye-slash text-lg"></i>
                                    {{ tx('chat.ui.hide_for_me') }}
                                </button>
                                <button
                                    v-if="message.local_status === 'failed'"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-error transition hover:bg-input"
                                    :title="message.error_message || tx('chat.ui.retry')"
                                    @click.stop="retryMessage(message); openMessageActionsId = null"
                                >
                                    <i class="las la-redo-alt text-lg"></i>
                                    {{ tx('chat.ui.retry') }}
                                </button>
                                <div
                                    v-if="isOwnMessage(message) && !String(message.id).startsWith('local-') && !canDeleteMessage(message) && message.local_status !== 'failed'"
                                    class="flex items-center gap-2 px-3 py-2.5 font-semibold text-secondary/70"
                                    :title="tx('chat.ui.not_deletable')"
                                >
                                    <i class="las la-lock text-lg"></i>
                                    {{ tx('chat.ui.not_deletable') }}
                                </div>
                                <button
                                    v-if="!isOwnMessage(message) && !String(message.id).startsWith('local-')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click.stop="openReport(message); openMessageActionsId = null"
                                >
                                    <i class="las la-flag text-lg"></i>
                                    {{ tx('chat.ui.report_message') }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="Object.keys(reactionCounts(message)).length"
                            class="mt-2 flex flex-wrap gap-1"
                            :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                        >
                            <span
                                v-for="(count, reaction) in reactionCounts(message)"
                                :key="reaction"
                                class="inline-flex h-7 items-center gap-1 rounded-full border border-border/60 bg-card px-2 text-xs font-semibold text-secondary shadow-sm"
                                :class="userReaction(message) === reaction ? 'border-primary/60 text-primary' : ''"
                            >
                                <i :class="['las text-base', reaction === 'heart' ? 'la-heart' : reaction === 'ok' ? 'la-check' : 'la-thumbs-up']"></i>
                                <span>{{ count }}</span>
                            </span>
                        </div>

                        <div v-if="isOwnMessage(message)" class="mt-1 flex justify-end">
                            <span class="inline-flex items-center gap-1 text-xs opacity-80" :title="deliverySummaryFor(message)">
                                <i :class="[statusIconFor(message).icon, message.local_status === 'failed' ? 'text-error' : '']"></i>
                                <span class="hidden sm:inline">{{ deliverySummaryFor(message) }}</span>
                            </span>
                        </div>
                        <p v-if="message.error_message" class="mt-1 text-right text-xs text-error">
                            {{ message.error_message }}
                        </p>
                    </template>
                </div>
            </div>

            <div v-if="selectedMessages.length === 0" class="flex h-full items-center justify-center text-sm text-secondary">
                {{ tx('chat.no_messages') }}
            </div>

            <div v-if="activeTypingUsers.length" class="text-xs text-secondary">
                {{ tx('chat.ui.typing', { names: activeTypingUsers.map((user) => user.name).join(', ') }) }}
            </div>
        </div>

        <form v-if="selectedConversation" class="border-t border-border p-3 sm:p-4" @submit.prevent="sendMessage">
            <AppLoadingState
                v-if="messageForm.processing"
                class="mb-2"
                :label="tx('chat.ui.sending')"
                inline
            />
            <div v-if="messageForm.attachments.length" class="mb-2 flex flex-wrap gap-2 text-xs text-secondary">
                <span v-for="(file, index) in messageForm.attachments" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-2 rounded border border-border px-2 py-1">
                    {{ file.name }}
                    <button type="button" class="text-secondary hover:text-primary" :title="tx('chat.ui.remove_attachment')" @click="removePendingAttachment(index)">
                        <i class="las la-times"></i>
                    </button>
                </span>
            </div>
            <div class="flex gap-2">
                <textarea
                    v-model="messageForm.message"
                    rows="2"
                    :placeholder="tx('chat.ui.message_placeholder')"
                    class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                    @keydown.enter.exact.prevent="sendMessage"
                    @input="announceTyping"
                />
                <label class="flex cursor-pointer items-center rounded-lg border border-border px-3 py-2 text-primary hover:bg-inputBg" :aria-label="tx('chat.file')">
                    <i class="las la-paperclip text-xl"></i>
                    <input :ref="setAttachmentInput" type="file" multiple class="hidden" @change="onAttachmentChange">
                </label>
                <AppButton
                    type="submit"
                    :disabled="messageForm.processing || !canSendMessage"
                    :loading="messageForm.processing"
                    icon-only
                    :aria-label="tx('chat.ui.send_message')"
                    :title="tx('chat.ui.send_message')"
                >
                    <i v-if="!messageForm.processing" class="las la-paper-plane text-xl" aria-hidden="true"></i>
                </AppButton>
            </div>
        </form>

        <div v-else class="flex min-h-0 flex-1 items-center justify-center p-8 text-center">
            <div class="max-w-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg text-primary">
                    <i class="las la-user-lock text-3xl"></i>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-primary">{{ tx('chat.ui.no_chat_open') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    {{ tx('chat.ui.no_chat_hint') }}
                </p>
                <button
                    type="button"
                    class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover lg:hidden"
                    @click="goBackToConversations"
                >
                    {{ tx('chat.ui.choose_chat') }}
                </button>
            </div>
        </div>
    </section>
</template>
