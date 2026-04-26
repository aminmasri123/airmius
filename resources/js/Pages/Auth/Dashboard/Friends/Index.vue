<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    friends: {
        type: Array,
        default: () => [],
    },
    receivedInvitations: {
        type: Array,
        default: () => [],
    },
    sentInvitations: {
        type: Array,
        default: () => [],
    },
})

const inviteForm = useForm({
    email: '',
})

const invite = () => {
    inviteForm.post(route('auth.friends.invitations.store'), {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    })
}

const acceptForm = useForm({})
const declineForm = useForm({})

const accept = (invitation) => {
    acceptForm.post(route('auth.friends.invitations.accept', invitation.id), {
        preserveScroll: true,
    })
}

const decline = (invitation) => {
    declineForm.post(route('auth.friends.invitations.decline', invitation.id), {
        preserveScroll: true,
    })
}

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
</script>

<template>
    <Head title="Freunde" />

    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-primary">Freunde</h1>
            <p class="mt-1 text-sm text-secondary">
                Lade Freunde ein, nimm Anfragen an und behalte deine Sport-Buddys im Blick.
            </p>
        </div>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">Freund einladen</h2>
            <p class="mt-1 text-sm text-secondary">
                Gib die E-Mail-Adresse eines bestehenden AIRMIUS-Nutzers ein.
            </p>

            <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="invite">
                <input
                    v-model="inviteForm.email"
                    type="email"
                    placeholder="freund@email.de"
                    class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                    required
                />
                <button
                    type="submit"
                    :disabled="inviteForm.processing"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:opacity-50"
                >
                    <i class="las la-user-plus"></i>
                    Einladen
                </button>
            </form>

            <p v-if="inviteForm.errors.email" class="mt-2 text-sm text-error">
                {{ inviteForm.errors.email }}
            </p>
        </section>

        <section v-if="receivedInvitations.length" class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">Offene Anfragen</h2>

            <div class="mt-4 grid gap-3">
                <div
                    v-for="invitation in receivedInvitations"
                    :key="invitation.id"
                    class="flex flex-col gap-3 rounded-lg border border-border bg-inputBg p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="invitation.sender.profile_photo_url"
                            :src="invitation.sender.profile_photo_url"
                            :alt="invitation.sender.name"
                            class="h-11 w-11 rounded-full object-cover"
                        />
                        <div v-else class="flex h-11 w-11 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(invitation.sender.name) }}
                        </div>
                        <div>
                            <div class="font-semibold text-primary">{{ invitation.sender.name }}</div>
                            <div class="text-sm text-secondary">{{ invitation.sender.email }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                            @click="accept(invitation)"
                        >
                            Annehmen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary transition hover:border-borderHover"
                            @click="decline(invitation)"
                        >
                            Ablehnen
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Meine Freunde</h2>

                <div v-if="friends.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="friend in friends"
                        :key="friend.id"
                        class="flex items-center gap-3 rounded-lg border border-border bg-inputBg p-4"
                    >
                        <img
                            v-if="friend.profile_photo_url"
                            :src="friend.profile_photo_url"
                            :alt="friend.name"
                            class="h-11 w-11 rounded-full object-cover"
                        />
                        <div v-else class="flex h-11 w-11 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(friend.name) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-primary">{{ friend.name }}</div>
                            <div class="truncate text-sm text-secondary">{{ friend.email }}</div>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 rounded-lg border border-dashed border-border p-8 text-center text-secondary">
                    Noch keine Freunde. Lade jemanden ein, um zu starten.
                </div>
            </section>

            <aside class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Gesendet</h2>

                <div v-if="sentInvitations.length" class="mt-4 space-y-3">
                    <div
                        v-for="invitation in sentInvitations"
                        :key="invitation.id"
                        class="rounded-lg border border-border bg-inputBg p-3"
                    >
                        <div class="text-sm font-semibold text-primary">{{ invitation.recipient.name }}</div>
                        <div class="text-xs text-secondary">{{ invitation.recipient.email }}</div>
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-secondary">
                    Keine offenen gesendeten Einladungen.
                </p>
            </aside>
        </div>
    </div>
</template>
