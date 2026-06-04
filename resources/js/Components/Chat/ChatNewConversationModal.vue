<script setup>
defineProps({
    canCreateConversation: { type: Boolean, default: false },
    conversationForm: { type: Object, required: true },
    initials: { type: Function, required: true },
    show: { type: Boolean, default: false },
    teams: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
})

defineEmits(['close', 'set-type', 'submit', 'toggle-participant'])
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-3">
        <div class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-border bg-card shadow-2xl">
            <div class="border-b border-border p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Neue Konversation</h2>
                        <p class="mt-1 text-sm text-secondary">Für Gruppen mindestens zwei Personen auswählen.</p>
                    </div>
                    <button type="button" class="text-secondary hover:text-primary" @click="$emit('close')">
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="border-b border-border bg-inputBg p-2">
                <div class="grid grid-cols-3 gap-1 rounded-lg">
                    <button
                        type="button"
                        class="rounded px-3 py-2 text-sm font-medium transition"
                        :class="conversationForm.type === 'direct' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                        @click="$emit('set-type', 'direct')"
                    >
                        Direkt
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-2 text-sm font-medium transition"
                        :class="conversationForm.type === 'group' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                        @click="$emit('set-type', 'group')"
                    >
                        Gruppe
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-2 text-sm font-medium transition"
                        :class="conversationForm.type === 'team' ? 'bg-card text-primary shadow-sm' : 'text-secondary'"
                        @click="$emit('set-type', 'team')"
                    >
                        Team
                    </button>
                </div>
            </div>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="$emit('submit')">
                <div v-if="conversationForm.type === 'team'" class="p-3">
                    <select
                        v-model="conversationForm.team_id"
                        class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-primary focus:ring-primary"
                    >
                        <option :value="null">Team auswählen</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>

                <div v-else class="min-h-0 flex-1 overflow-y-auto p-2 custom-scrollbar">
                    <button
                        v-for="member in users"
                        :key="member.id"
                        type="button"
                        class="mb-2 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
                        :class="conversationForm.participant_ids.includes(member.id)
                            ? 'border-primary bg-inputBg'
                            : 'border-border hover:bg-inputBg'"
                        @click="$emit('toggle-participant', member.id)"
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
                            :class="conversationForm.participant_ids.includes(member.id) ? 'la-check-circle text-success' : 'la-circle text-secondary'"
                        ></i>
                    </button>
                </div>

                <div class="border-t border-border p-3">
                    <textarea
                        v-model="conversationForm.message"
                        rows="2"
                        placeholder="Erste Nachricht optional"
                        class="mb-3 w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-primary focus:ring-primary"
                    />

                    <div class="flex gap-2">
                        <div v-if="conversationForm.type === 'group'" class="flex flex-1 items-center text-xs text-secondary">
                            {{ conversationForm.participant_ids.length }} von 2 Personen ausgewählt
                        </div>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-secondary transition hover:bg-inputBg"
                            @click="$emit('close')"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            :disabled="conversationForm.processing || !canCreateConversation"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Chat starten
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

