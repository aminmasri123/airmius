<script setup>
import { computed } from 'vue'

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
                    title="Zurück"
                    @click="goBackToConversations"
                >
                    <i class="las la-arrow-left text-xl"></i>
                </button>
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-semibold text-primary">{{ titleFor(selectedConversation) }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ typeLabelFor(selectedConversation) }}chat - {{ selectedUsers.length }} Mitglieder
                        <span v-if="isSelectedConversationMuted"> - stumm</span>
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
                    :title="isSelectedConversationMuted ? 'Benachrichtigungen aktivieren' : 'Chat stummschalten'"
                    :disabled="muteForm.processing"
                    @click="muteSelectedConversation(isSelectedConversationMuted ? 0 : 480)"
                >
                    <i :class="['las text-xl', isSelectedConversationMuted ? 'la-bell-slash' : 'la-bell']"></i>
                </button>
                <button
                    v-if="selectedConversation.type === 'group'"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                    title="Gruppenprofil"
                    @click="openConversationSettingsModal"
                >
                    <i class="las la-cog text-xl"></i>
                </button>
                <button
                    v-if="selectedConversation.type === 'group'"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-primary"
                    title="Personen hinzufügen"
                    @click="openAddMembersModal"
                >
                    <i class="las la-user-plus text-xl"></i>
                </button>
                <button
                    v-if="canLeaveConversation"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-secondary transition hover:bg-inputBg hover:text-error"
                    title="Gruppe verlassen"
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
                placeholder="Nachrichten in diesem Chat suchen"
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
            >
            <button
                v-if="chatSearch"
                type="button"
                class="text-secondary hover:text-primary"
                title="Suche leeren"
                @click="chatSearch = ''"
            >
                <i class="las la-times"></i>
            </button>
        </label>

        <div v-if="selectedConversation" :ref="setMessagesContainer" class="custom-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4" @scroll.passive="onMessagesScroll">
            <div v-if="messagePage?.has_more || loadingOlderMessages" class="flex justify-center">
                <button
                    type="button"
                    class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-secondary hover:bg-inputBg disabled:cursor-wait disabled:opacity-70"
                    :disabled="loadingOlderMessages"
                    @click="loadOlderMessages"
                >
                    {{ loadingOlderMessages ? 'Lade ältere Nachrichten...' : 'Ältere Nachrichten laden' }}
                </button>
            </div>

            <div
                v-for="message in selectedMessages"
                :key="message.id"
                class="flex"
                :class="isSystemMessage(message) ? 'justify-center' : (isOwnMessage(message) ? 'justify-end' : 'justify-start')"
            >
                <div
                    class="max-w-[92%] rounded-lg px-3 py-2.5 sm:max-w-[82%] sm:px-4 sm:py-3"
                    :class="isSystemMessage(message)
                        ? 'border border-border bg-card text-secondary'
                        : isOwnMessage(message)
                        ? 'bg-buttonPrimary text-buttonTextPrimary'
                        : 'bg-inputBg text-primary'"
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
                                    :title="`${attachmentLabel(attachment)} ansehen`"
                                    @click="openMediaPreview(attachment)"
                                >
                                    <img
                                        :src="fileUrl(attachment.file)"
                                        :alt="attachmentLabel(attachment)"
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
                                    :title="`${attachmentLabel(attachment)} herunterladen`"
                                    @click="trackDownload(attachment.file)"
                                >
                                    <i :class="[fileIconFor(attachment.file), 'text-2xl']"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ attachmentLabel(attachment) }}</span>
                                        <span class="block text-[11px] opacity-75">
                                            {{ fileExtension(attachment.file).toUpperCase() || 'DATEI' }}
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
                                Upload abbrechen
                            </button>
                        </div>

                        <details class="relative mt-2 flex justify-end text-xs">
                            <summary
                                class="ml-auto inline-flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-full border border-border/60 bg-card/80 text-secondary transition hover:border-primary/50 hover:text-primary [&::-webkit-details-marker]:hidden"
                                title="Nachrichtenaktionen"
                            >
                                <i class="las la-ellipsis-h text-lg"></i>
                            </summary>
                            <div class="absolute right-0 top-9 z-20 w-56 overflow-hidden rounded-xl border border-border bg-card shadow-xl">
                                <div class="grid grid-cols-3 gap-1 border-b border-border/60 p-2">
                                    <button
                                        v-for="reaction in ['like', 'heart', 'ok']"
                                        :key="reaction"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border text-xs font-semibold transition"
                                        :class="userReaction(message) === reaction ? 'border-primary bg-primary/10 text-primary' : 'border-border/60 bg-input text-secondary hover:text-primary'"
                                        :title="reaction"
                                        @click="reactToMessage(message, reaction)"
                                    >
                                        <i :class="['las text-lg', reaction === 'heart' ? 'la-heart' : reaction === 'ok' ? 'la-check' : 'la-thumbs-up']"></i>
                                        <span v-if="reactionCounts(message)[reaction]" class="ml-1 opacity-70">{{ reactionCounts(message)[reaction] }}</span>
                                    </button>
                                </div>
                                <button
                                    v-if="canDeleteMessage(message)"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click="deleteMessage(message)"
                                >
                                    <i class="las la-trash text-lg"></i>
                                    Für alle löschen
                                </button>
                                <button
                                    v-if="!String(message.id).startsWith('local-')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click="hideMessageForMe(message)"
                                >
                                    <i class="las la-eye-slash text-lg"></i>
                                    Nur für mich ausblenden
                                </button>
                                <button
                                    v-if="message.local_status === 'failed'"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-error transition hover:bg-input"
                                    :title="message.error_message || 'Erneut senden'"
                                    @click="retryMessage(message)"
                                >
                                    <i class="las la-redo-alt text-lg"></i>
                                    Erneut senden
                                </button>
                                <div
                                    v-if="isOwnMessage(message) && !String(message.id).startsWith('local-') && !canDeleteMessage(message) && message.local_status !== 'failed'"
                                    class="flex items-center gap-2 px-3 py-2.5 font-semibold text-secondary/70"
                                    title="Bereits gelesen - nicht mehr löschbar"
                                >
                                    <i class="las la-lock text-lg"></i>
                                    Nicht mehr löschbar
                                </div>
                                <button
                                    v-if="!isOwnMessage(message) && !String(message.id).startsWith('local-')"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left font-semibold text-secondary transition hover:bg-input hover:text-primary"
                                    @click="openReport(message)"
                                >
                                    <i class="las la-flag text-lg"></i>
                                    Nachricht melden
                                </button>
                            </div>
                        </details>

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
                Keine Nachrichten in diesem Chat.
            </div>

            <div v-if="activeTypingUsers.length" class="text-xs text-secondary">
                {{ activeTypingUsers.map((user) => user.name).join(', ') }} schreibt...
            </div>
        </div>

        <form v-if="selectedConversation" class="border-t border-border p-3 sm:p-4" @submit.prevent="sendMessage">
            <div v-if="messageForm.attachments.length" class="mb-2 flex flex-wrap gap-2 text-xs text-secondary">
                <span v-for="(file, index) in messageForm.attachments" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-2 rounded border border-border px-2 py-1">
                    {{ file.name }}
                    <button type="button" class="text-secondary hover:text-primary" title="Anhang entfernen" @click="removePendingAttachment(index)">
                        <i class="las la-times"></i>
                    </button>
                </span>
            </div>
            <div class="flex gap-2">
                <textarea
                    v-model="messageForm.message"
                    rows="2"
                    placeholder="Nachricht schreiben..."
                    class="min-w-0 flex-1 resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                    @keydown.enter.exact.prevent="sendMessage"
                    @input="announceTyping"
                />
                <label class="flex cursor-pointer items-center rounded-lg border border-border px-3 py-2 text-primary hover:bg-inputBg">
                    <i class="las la-paperclip text-xl"></i>
                    <input :ref="setAttachmentInput" type="file" multiple class="hidden" @change="onAttachmentChange">
                </label>
                <button
                    type="submit"
                    :disabled="messageForm.processing || !canSendMessage"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <i class="las la-paper-plane text-xl"></i>
                </button>
            </div>
        </form>

        <div v-else class="flex min-h-0 flex-1 items-center justify-center p-8 text-center">
            <div class="max-w-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg text-primary">
                    <i class="las la-user-lock text-3xl"></i>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-primary">Kein Chat geöffnet</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    Aus DatenschutzGründen wird keine Konversation automatisch angezeigt.
                    Wähle bewusst eine Person, ein Team oder eine Gruppe aus.
                </p>
                <button
                    type="button"
                    class="mt-5 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover lg:hidden"
                    @click="goBackToConversations"
                >
                    Chat auswählen
                </button>
            </div>
        </div>
    </section>
</template>





