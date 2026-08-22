<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { confirmDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

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

const friendsList = ref([...props.friends])
const receivedInvitationList = ref([...props.receivedInvitations])
const sentInvitationList = ref([...props.sentInvitations])
const activeTab = ref('friends')
const acceptNotice = ref(null)
const acceptingInvitationIds = ref([])
const decliningInvitationIds = ref([])
const withdrawingInvitationIds = ref([])
const removingFriendIds = ref([])

watch(() => props.friends, (friends) => {
    friendsList.value = [...friends]
})

watch(() => props.receivedInvitations, (invitations) => {
    receivedInvitationList.value = [...invitations]
})

watch(() => props.sentInvitations, (invitations) => {
    sentInvitationList.value = [...invitations]
})

const inviteForm = useForm({
    email: '',
})
const inviteNotice = ref(null)
const inviting = ref(false)

const invite = async () => {
    inviteNotice.value = null
    inviteForm.clearErrors()
    inviting.value = true

    try {
        const email = inviteForm.email.trim()
        const response = await window.axios.post(
            route('auth.friends.invitations.store'),
            { email },
            { headers: { Accept: 'application/json' } },
        )
        const data = response.data?.data || {}

        if (!['already_sent', 'already_friends'].includes(data.status)) {
            const inverseInvitation = receivedInvitationList.value.find((invitation) => (
                invitation.sender?.email?.toLowerCase() === email.toLowerCase()
            ))

            if (data.status === 'accepted' && inverseInvitation) {
                receivedInvitationList.value = receivedInvitationList.value.filter((item) => item.id !== inverseInvitation.id)
                friendsList.value = [{
                    id: inverseInvitation.sender.id,
                    name: inverseInvitation.sender.name,
                    email: inverseInvitation.sender.email,
                    profile_photo_url: inverseInvitation.sender.profile_photo_url,
                    friends_since: new Date().toISOString(),
                }, ...friendsList.value.filter((friend) => friend.id !== inverseInvitation.sender.id)]
                activeTab.value = 'friends'
            } else if (data.invitation_id) {
                sentInvitationList.value = [{
                    id: data.invitation_id,
                    recipient: {
                        id: data.recipient_id || null,
                        name: email,
                        email,
                    },
                    created_at: new Date().toISOString(),
                }, ...sentInvitationList.value.filter((invitation) => invitation.id !== data.invitation_id)]
                activeTab.value = 'sent'
            }
        }

        inviteForm.reset()
        inviteNotice.value = {
            type: 'success',
            message: response.data?.message || tx('friends.invite_success', 'Einladung wurde erfolgreich gesendet.'),
        }
    } catch (error) {
        const errors = error.response?.data?.errors || {}
        const emailError = Array.isArray(errors.email) ? errors.email[0] : errors.email
        if (emailError) inviteForm.setError('email', emailError)
        inviteNotice.value = {
            type: 'error',
            message: emailError || errors.user_id || error.response?.data?.message || tx('friends.invite_failed', 'Einladung konnte nicht gesendet werden.'),
        }
    } finally {
        inviting.value = false
    }
}

const openFriendMenuId = ref(null)

const accept = async (invitation) => {
    const previousInvitations = [...receivedInvitationList.value]
    const previousFriends = [...friendsList.value]

    acceptNotice.value = null
    acceptingInvitationIds.value = [...acceptingInvitationIds.value, invitation.id]
    receivedInvitationList.value = receivedInvitationList.value.filter((item) => item.id !== invitation.id)
    friendsList.value = [
        {
            id: invitation.sender.id,
            name: invitation.sender.name,
            email: invitation.sender.email,
            profile_photo_url: invitation.sender.profile_photo_url,
            friends_since: new Date().toISOString(),
        },
        ...friendsList.value.filter((friend) => friend.id !== invitation.sender.id),
    ]

    try {
        await window.axios.post(
            route('auth.friends.invitations.accept', invitation.id),
            {},
            { headers: { Accept: 'application/json' } },
        )

        acceptNotice.value = { type: 'success', message: tx('friends.accepted', 'Freundschaft angenommen.') }
        activeTab.value = 'friends'
    } catch (error) {
        const errors = error.response?.data?.errors || {}

        receivedInvitationList.value = previousInvitations
        friendsList.value = previousFriends
        acceptNotice.value = {
            type: 'error',
            message: errors.invitation?.[0]
                || error.response?.data?.message
                || tx('friends.accept_failed', 'Anfrage konnte nicht angenommen werden.'),
        }
    } finally {
        acceptingInvitationIds.value = acceptingInvitationIds.value.filter((id) => id !== invitation.id)
    }
}

const decline = async (invitation) => {
    const previousInvitations = [...receivedInvitationList.value]
    acceptNotice.value = null
    decliningInvitationIds.value = [...decliningInvitationIds.value, invitation.id]
    receivedInvitationList.value = receivedInvitationList.value.filter((item) => item.id !== invitation.id)

    try {
        const response = await window.axios.post(
            route('auth.friends.invitations.decline', invitation.id),
            {},
            { headers: { Accept: 'application/json' } },
        )
        acceptNotice.value = {
            type: 'success',
            message: response.data?.message || tx('friends.declined', 'Anfrage abgelehnt.'),
        }
    } catch (error) {
        receivedInvitationList.value = previousInvitations
        acceptNotice.value = {
            type: 'error',
            message: error.response?.data?.message || tx('friends.decline_failed', 'Anfrage konnte nicht abgelehnt werden.'),
        }
    } finally {
        decliningInvitationIds.value = decliningInvitationIds.value.filter((id) => id !== invitation.id)
    }
}

const removeFriend = async (friend) => {
    const confirmed = await confirmDialog({
        title: tx('friends.remove_title', 'Freundschaft beenden'),
        message: tx('friends.remove_message', 'Möchtest du die Freundschaft mit {name} wirklich beenden?', { name: friend.name }),
        confirmLabel: tx('friends.end', 'Beenden'),
        danger: true,
    })

    if (!confirmed) {
        return
    }

    openFriendMenuId.value = null
    const previousFriends = [...friendsList.value]
    removingFriendIds.value = [...removingFriendIds.value, friend.id]
    friendsList.value = friendsList.value.filter((item) => item.id !== friend.id)

    try {
        const response = await window.axios.delete(
            route('auth.friends.destroy', friend.id),
            { headers: { Accept: 'application/json' } },
        )
        acceptNotice.value = {
            type: 'success',
            message: response.data?.message || tx('friends.ended', 'Freundschaft beendet.'),
        }
    } catch (error) {
        friendsList.value = previousFriends
        acceptNotice.value = {
            type: 'error',
            message: error.response?.data?.message || tx('friends.remove_failed', 'Freundschaft konnte nicht beendet werden.'),
        }
    } finally {
        removingFriendIds.value = removingFriendIds.value.filter((id) => id !== friend.id)
    }
}

const withdrawInvitation = async (invitation) => {
    const previousInvitations = [...sentInvitationList.value]
    withdrawingInvitationIds.value = [...withdrawingInvitationIds.value, invitation.id]
    sentInvitationList.value = sentInvitationList.value.filter((item) => item.id !== invitation.id)

    try {
        const response = await window.axios.delete(
            route('auth.friends.invitations.withdraw', invitation.id),
            { headers: { Accept: 'application/json' } },
        )
        acceptNotice.value = {
            type: 'success',
            message: response.data?.message || tx('friends.withdrawn', 'Einladung zurückgezogen.'),
        }
    } catch (error) {
        sentInvitationList.value = previousInvitations
        acceptNotice.value = {
            type: 'error',
            message: error.response?.data?.message || tx('friends.withdraw_failed', 'Einladung konnte nicht zurückgezogen werden.'),
        }
    } finally {
        withdrawingInvitationIds.value = withdrawingInvitationIds.value.filter((id) => id !== invitation.id)
    }
}

const toggleFriendMenu = (friend) => {
    openFriendMenuId.value = openFriendMenuId.value === friend.id ? null : friend.id
}

const reportFriend = async (friend) => {
    const confirmed = await confirmDialog({
        title: tx('friends.report_title', 'Profil melden'),
        message: tx('friends.report_message', '{name} melden? Die Meldung wird an die Moderation gesendet.', { name: friend.name }),
        confirmLabel: tx('friends.report', 'Melden'),
        danger: true,
    })

    if (!confirmed) {
        return
    }

    openFriendMenuId.value = null

    router.post(route('auth.reports.store'), {
        type: 'user',
        id: friend.id,
        reason: 'other',
        details: 'Meldung aus der Freunde-Liste.',
    }, {
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
    <Head :title="tx('friends.page_title', 'Freunde')" />

    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-primary">{{ tx('friends.title', 'Freunde') }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ tx('friends.intro', 'Lade Freunde ein, nimm Anfragen an und behalte deine Sport-Buddys im Blick.') }}
            </p>
        </div>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ tx('friends.invite_title', 'Freund einladen') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ tx('friends.invite_hint', 'Gib eine E-Mail-Adresse ein. Bestehende Nutzer erhalten eine Anfrage, externe Personen eine Einladung per E-Mail.') }}
            </p>

            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="invite">
                <div class="min-w-0 flex-1">
                    <label for="friend-invite-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-secondary">
                        {{ t('settings.profile.email') }}
                    </label>
                    <input
                        id="friend-invite-email"
                        v-model="inviteForm.email"
                        type="email"
                        placeholder="freund@email.de"
                        class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                        :aria-invalid="Boolean(inviteForm.errors.email)"
                        :aria-describedby="inviteForm.errors.email ? 'friend-invite-email-error' : undefined"
                        required
                    />
                </div>
                <button
                    type="submit"
                    :disabled="inviting"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:opacity-50"
                >
                    <i class="las la-user-plus"></i>
                    {{ inviting ? tx('friends.inviting', 'Wird gesendet...') : tx('friends.invite', 'Einladen') }}
                </button>
            </form>

            <p v-if="inviteForm.errors.email" id="friend-invite-email-error" role="alert" class="mt-2 text-sm text-error">
                {{ inviteForm.errors.email }}
            </p>

            <p
                v-if="inviteNotice"
                class="mt-3 rounded-lg border px-3 py-2 text-sm font-semibold"
                :class="inviteNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ inviteNotice.message }}
            </p>
        </section>

        <p
            v-if="acceptNotice"
            :class="[
                'rounded-lg border px-4 py-3 text-sm font-semibold',
                acceptNotice.type === 'error'
                    ? 'border-error/30 bg-error/10 text-error'
                    : 'border-success/30 bg-success/10 text-success',
            ]"
        >
            {{ acceptNotice.message }}
        </p>

        <nav
            role="tablist"
            :aria-label="tx('friends.sections', 'Freundschaftsbereiche')"
            class="grid grid-cols-1 gap-2 rounded-2xl border border-border bg-card p-2 sm:grid-cols-3"
        >
            <button
                v-for="item in [
                    { id: 'friends', label: tx('friends.mine', 'Meine Freunde'), count: friendsList.length, icon: 'las la-user-friends' },
                    { id: 'received', label: tx('friends.pending', 'Offene Anfragen'), count: receivedInvitationList.length, icon: 'las la-envelope-open-text' },
                    { id: 'sent', label: tx('friends.sent', 'Gesendet'), count: sentInvitationList.length, icon: 'las la-paper-plane' },
                ]"
                :key="item.id"
                type="button"
                role="tab"
                :aria-selected="activeTab === item.id"
                class="flex min-h-12 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === item.id ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-inputBg hover:text-primary'"
                @click="activeTab = item.id"
            >
                <i :class="item.icon"></i>
                <span>{{ item.label }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs" :class="activeTab === item.id ? 'bg-white/20' : 'bg-inputBg text-primary'">{{ item.count }}</span>
            </button>
        </nav>

        <section v-if="activeTab === 'received'" class="surface-card p-5" role="tabpanel">
            <h2 class="text-lg font-semibold text-primary">{{ tx('friends.pending', 'Offene Anfragen') }}</h2>

            <div v-if="receivedInvitationList.length" class="mt-4 grid gap-3">
                <div
                    v-for="invitation in receivedInvitationList"
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
                            :disabled="acceptingInvitationIds.includes(invitation.id)"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-wait disabled:opacity-70"
                            @click="accept(invitation)"
                        >
                            {{ acceptingInvitationIds.includes(invitation.id) ? tx('friends.accepting', 'Wird angenommen...') : tx('friends.accept', 'Annehmen') }}
                        </button>
                        <button
                            type="button"
                            :disabled="decliningInvitationIds.includes(invitation.id) || acceptingInvitationIds.includes(invitation.id)"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary transition hover:border-borderHover disabled:cursor-wait disabled:opacity-70"
                            @click="decline(invitation)"
                        >
                            {{ decliningInvitationIds.includes(invitation.id) ? tx('friends.declining', 'Wird abgelehnt...') : tx('friends.decline', 'Ablehnen') }}
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="mt-6 rounded-lg border border-dashed border-border p-8 text-center text-secondary">
                {{ tx('friends.received_empty', 'Keine offenen empfangenen Anfragen.') }}
            </div>
        </section>

        <section v-else-if="activeTab === 'friends'" class="surface-card p-5" role="tabpanel">
                <h2 class="text-lg font-semibold text-primary">{{ tx('friends.mine', 'Meine Freunde') }}</h2>

                <div v-if="friendsList.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="friend in friendsList"
                        :key="friend.id"
                        class="relative flex flex-col gap-3 rounded-lg border border-border bg-inputBg p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            :href="route('auth.users.show', friend.id)"
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg transition hover:bg-bg focus:outline-none focus:ring-2 focus:ring-borderHover"
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
                        </Link>
                        <div class="relative self-end sm:self-auto">
                            <button
                                type="button"
                                :disabled="removingFriendIds.includes(friend.id)"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-border text-secondary transition hover:border-borderHover hover:bg-bg hover:text-primary"
                                :aria-expanded="openFriendMenuId === friend.id"
                                :aria-label="tx('friends.actions_for', 'Aktionen für {name}', { name: friend.name })"
                                @click.stop="toggleFriendMenu(friend)"
                            >
                                <i class="las la-ellipsis-v text-xl"></i>
                            </button>

                            <div
                                v-if="openFriendMenuId === friend.id"
                                class="absolute right-0 top-11 z-20 w-56 overflow-hidden rounded-lg border border-border bg-card py-1 shadow-xl"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-primary transition hover:bg-inputBg"
                                    @click="removeFriend(friend)"
                                >
                                    <i class="las la-user-minus text-lg"></i>
                                    {{ tx('friends.end_friendship', 'Freundschaft beenden') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-error transition hover:bg-error/10"
                                    @click="reportFriend(friend)"
                                >
                                    <i class="las la-flag text-lg"></i>
                                    {{ tx('friends.report_friend', 'Freund melden') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 rounded-lg border border-dashed border-border p-8 text-center text-secondary">
                    {{ tx('friends.empty', 'Noch keine Freunde. Lade jemanden ein, um zu starten.') }}
                </div>
        </section>

        <section v-else class="surface-card p-5" role="tabpanel">
            <h2 class="text-lg font-semibold text-primary">{{ tx('friends.sent', 'Gesendet') }}</h2>

                <div v-if="sentInvitationList.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="invitation in sentInvitationList"
                        :key="invitation.id"
                        class="flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-inputBg p-4"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-primary">{{ invitation.recipient.name }}</div>
                            <div class="truncate text-xs text-secondary">{{ invitation.recipient.email }}</div>
                        </div>
                        <button
                            type="button"
                            :disabled="withdrawingInvitationIds.includes(invitation.id)"
                            class="mt-auto inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary transition hover:border-borderHover disabled:cursor-wait disabled:opacity-70"
                            @click="withdrawInvitation(invitation)"
                        >
                            <i class="las la-undo"></i>
                            {{ withdrawingInvitationIds.includes(invitation.id) ? tx('friends.withdrawing', 'Wird zurückgezogen...') : tx('friends.withdraw', 'Zurückziehen') }}
                        </button>
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-secondary">
                    {{ tx('friends.sent_empty', 'Keine offenen gesendeten Einladungen.') }}
                </p>
        </section>
    </div>
</template>
