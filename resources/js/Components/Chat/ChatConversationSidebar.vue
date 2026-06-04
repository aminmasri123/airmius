<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    showChatOnMobile: { type: Boolean, default: false },
    conversationSearch: { type: String, default: '' },
    activeConversationFilter: { type: String, default: 'all' },
    conversationFilters: { type: Array, default: () => [] },
    conversationsByType: { type: Object, default: () => ({}) },
    conversations: { type: Array, default: () => [] },
    groupInvitations: { type: Array, default: () => [] },
    filteredConversations: { type: Array, default: () => [] },
    selectedConversation: { type: Object, default: null },
    initials: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    titleFor: { type: Function, required: true },
    typeLabelFor: { type: Function, required: true },
    latestMessagePreviewFor: { type: Function, required: true },
})

const emit = defineEmits([
    'accept-invitation',
    'decline-invitation',
    'new-conversation',
    'select-conversation',
    'update:activeConversationFilter',
    'update:conversationSearch',
])

const searchModel = computed({
    get: () => props.conversationSearch,
    set: (value) => emit('update:conversationSearch', value),
})

const activeFilterModel = computed({
    get: () => props.activeConversationFilter,
    set: (value) => emit('update:activeConversationFilter', value),
})
</script>

<template>
    <aside
        class="min-h-0 flex-col rounded-lg border border-border bg-card"
        :class="showChatOnMobile ? 'hidden lg:flex' : 'flex'"
    >
        <div class="border-b border-border p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-primary">Chat</h1>
                    <p class="mt-1 text-sm text-secondary">
                        Erst Person oder Gruppe wählen, dann öffnen.
                    </p>
                </div>
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                    title="Neue Konversation"
                    @click="emit('new-conversation')"
                >
                    <i class="las la-plus text-xl"></i>
                </button>
            </div>

            <label class="mt-4 flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                <i class="las la-search text-lg text-secondary"></i>
                <input
                    v-model="searchModel"
                    type="search"
                    placeholder="Person, Team oder Training suchen"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                >
            </label>

            <div class="mt-3 flex gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <button
                    v-for="filter in conversationFilters"
                    :key="filter.key"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition"
                    :class="activeFilterModel === filter.key
                        ? 'border-primary bg-buttonPrimary text-buttonTextPrimary'
                        : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                    @click="activeFilterModel = filter.key"
                >
                    <i :class="filter.icon"></i>
                    <span>{{ filter.label }}</span>
                    <span v-if="filter.key !== 'all'" class="text-xs opacity-75">
                        {{ conversationsByType[filter.key] || 0 }}
                    </span>
                    <span v-else class="text-xs opacity-75">{{ conversations.length }}</span>
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
            <div v-if="groupInvitations.length" class="mb-3 space-y-2 rounded-lg border border-border bg-inputBg p-2">
                <p class="px-1 text-xs font-semibold uppercase text-secondary">Gruppeneinladungen</p>
                <div
                    v-for="invitation in groupInvitations"
                    :key="invitation.id"
                    class="rounded-lg bg-card p-3"
                >
                    <p class="truncate text-sm font-semibold text-primary">
                        {{ titleFor(invitation.conversation) }}
                    </p>
                    <p class="mt-1 truncate text-xs text-secondary">
                        Von {{ invitation.inviter?.name || 'Mitglied' }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                            @click="emit('accept-invitation', invitation)"
                        >
                            Annehmen
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary"
                            @click="emit('decline-invitation', invitation)"
                        >
                            Ablehnen
                        </button>
                    </div>
                </div>
            </div>

            <Link
                v-for="conversation in filteredConversations"
                :key="conversation.id"
                :href="route('auth.conversations.index', { conversation: conversation.id })"
                preserve-scroll
                class="mb-1 flex gap-3 rounded-lg p-3 text-primary transition hover:bg-muted"
                :class="selectedConversation?.id === conversation.id ? 'bg-muted' : ''"
                @click="emit('select-conversation')"
            >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                    {{ initials(titleFor(conversation)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="truncate text-sm font-semibold">{{ titleFor(conversation) }}</h2>
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="conversation.unread_count"
                                class="rounded-full bg-error px-2 py-0.5 text-[11px] font-semibold text-white"
                            >
                                {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                            </span>
                            <span class="text-xs text-secondary">
                                {{ formatTime(conversation.messages_max_created_at) }}
                            </span>
                        </div>
                    </div>
                    <p
                        class="mt-1 truncate text-xs"
                        :class="conversation.unread_count ? 'font-semibold text-primary' : 'text-secondary'"
                    >
                        {{ latestMessagePreviewFor(conversation) }}
                    </p>
                    <p class="mt-0.5 truncate text-[11px] text-secondary">
                        {{ typeLabelFor(conversation) }} - {{ conversation.users.length }} Mitglieder
                    </p>
                </div>
            </Link>

            <div v-if="conversations.length === 0" class="p-8 text-center text-sm text-secondary">
                Noch keine Chats. Starte oben eine neue Konversation.
            </div>

            <div v-else-if="filteredConversations.length === 0" class="p-8 text-center text-sm text-secondary">
                Keine passenden Chats für diesen Filter.
            </div>
        </div>
    </aside>
</template>

