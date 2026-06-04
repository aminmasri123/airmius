<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    profileUser: { type: Object, required: true },
    viewer: { type: Object, required: true },
    privacyLabel: { type: String, required: true },
    membershipCount: { type: Number, required: true },
    primarySportProfiles: { type: Array, default: () => [] },
    friendshipStatus: { type: String, default: null },
    canSendFriendRequest: { type: Boolean, default: false },
    friendshipProcessing: { type: Boolean, default: false },
    friendshipNotice: { type: Object, default: null },
    profileActionMenuOpen: { type: Boolean, default: false },
    initials: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    levelLabel: { type: Function, required: true },
    sendMessage: { type: Function, required: true },
    follow: { type: Function, required: true },
    unfollow: { type: Function, required: true },
    sendFriendRequest: { type: Function, required: true },
    acceptFriendRequest: { type: Function, required: true },
    removeFriend: { type: Function, required: true },
    toggleProfileActionMenu: { type: Function, required: true },
    setProfileActionMenuOpen: { type: Function, required: true },
    openProfileReport: { type: Function, required: true },
    blockUser: { type: Function, required: true },
    unblockUser: { type: Function, required: true },
})
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="relative h-32 bg-[color:var(--surface-strong)] sm:h-40">
            <div class="absolute inset-0 opacity-90" style="background: linear-gradient(135deg, color-mix(in srgb, var(--buttonPrimary) 34%, transparent), color-mix(in srgb, var(--accent-2) 18%, transparent) 52%, color-mix(in srgb, var(--accent-3) 18%, transparent));"></div>
            <div class="absolute inset-x-0 bottom-0 h-24" style="background: linear-gradient(180deg, transparent, var(--card));"></div>
        </div>

        <div class="px-4 pb-6 sm:px-6">
            <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-start">
                <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="relative z-10 -mt-14 shrink-0 sm:-mt-16">
                        <img
                            v-if="profileUser.profile_photo_url"
                            :src="profileUser.profile_photo_url"
                            :alt="profileUser.name"
                            class="size-28 rounded-xl border-4 border-card object-cover shadow-lg sm:size-32"
                        />
                        <div
                            v-else
                            class="flex size-28 items-center justify-center rounded-xl border-4 border-card bg-buttonPrimary text-3xl font-bold text-buttonTextPrimary shadow-lg sm:size-32 sm:text-4xl"
                        >
                            {{ initials(profileUser.name) }}
                        </div>
                    </div>

                    <div class="min-w-0 pt-1 sm:pt-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-secondary">
                                {{ privacyLabel }}
                            </span>
                            <span v-if="profileUser.gamification.rank" class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">
                                {{ profileUser.gamification.rank }}
                            </span>
                            <span v-if="viewer.friendship_status === 'friends'" class="rounded-full border border-success/40 bg-success/10 px-3 py-1 text-xs font-semibold text-success">
                                Befreundet
                            </span>
                        </div>

                        <h1 class="mt-3 break-words text-2xl font-bold leading-tight tracking-normal text-primary sm:text-4xl">
                            {{ profileUser.name }}
                        </h1>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-secondary">
                            <span v-if="profileUser.email" class="inline-flex items-center gap-1.5">
                                <i class="las la-envelope text-base"></i>
                                {{ profileUser.email }}
                            </span>
                            <span v-if="profileUser.athlete_license_number" class="inline-flex items-center gap-1.5">
                                <i class="las la-id-card text-base"></i>
                                Lizenz {{ profileUser.athlete_license_number }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <i class="las la-users text-base"></i>
                                {{ membershipCount }} Bereiche
                            </span>
                        </div>

                        <div v-if="viewer.can_view_private_profile" class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="profile in primarySportProfiles"
                                :key="profile.id"
                                class="rounded-full border border-border bg-inputBg px-3 py-1.5 text-xs font-semibold text-primary"
                            >
                                {{ sportLabel(profile.sport) }} - {{ levelLabel(profile.experience_level) }}
                            </span>
                            <span v-if="!primarySportProfiles.length" class="rounded-full border border-border bg-inputBg px-3 py-1.5 text-xs font-semibold text-secondary">
                                Keine Sportarten hinterlegt
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 lg:w-auto lg:items-end">
                    <Link
                        v-if="viewer.is_self"
                        :href="route('profile.show')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        <i class="las la-user-edit text-lg"></i>
                        Profil bearbeiten
                    </Link>

                    <template v-else>
                        <div class="relative flex w-full flex-wrap items-stretch gap-2 lg:w-auto lg:justify-end">
                        <button
                        v-if="viewer.can_send_message"
                        type="button"
                        class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl bg-buttonPrimary px-2.5 py-2 text-xs font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                        @click="sendMessage"
                    >
                        <i class="las la-comment text-lg"></i>
                        <span>Nachricht</span>
                    </button>
                        <span v-else-if="viewer.is_blocked" class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center rounded-xl border border-border px-2.5 py-2 text-center text-xs text-secondary sm:px-4 sm:text-sm lg:flex-none">
                            Nachrichten blockiert
                        </span>

                        <button
                        v-if="viewer.can_follow && !viewer.is_following"
                        type="button"
                        class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                        @click="follow"
                    >
                            <i class="las la-plus text-lg"></i>
                            <span>Folgen</span>
                        </button>
                        <button
                        v-else-if="viewer.can_follow"
                        type="button"
                        class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                        @click="unfollow"
                    >
                            <i class="las la-user-minus text-lg"></i>
                            <span>Entfolgen</span>
                        </button>

                        <button
                        v-if="canSendFriendRequest"
                        type="button"
                        :disabled="friendshipProcessing"
                        class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-border bg-card px-2.5 py-2 text-xs font-bold text-primary hover:border-borderHover disabled:cursor-wait disabled:opacity-70 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                        @click="sendFriendRequest"
                    >
                            <i class="las la-user-plus text-lg"></i>
                            <span>Freund</span>
                        </button>
                        <button
                        v-else-if="friendshipStatus === 'received'"
                        type="button"
                        :disabled="friendshipProcessing"
                        class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl bg-buttonPrimary px-2.5 py-2 text-xs font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-wait disabled:opacity-70 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                        @click="acceptFriendRequest"
                    >
                            <i class="las la-check text-lg"></i>
                            <span>Annehmen</span>
                        </button>
                        <span v-else-if="friendshipStatus === 'sent'" class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-success/35 bg-success/10 px-2.5 py-2 text-center text-xs font-bold text-success sm:gap-2 sm:px-4 sm:text-sm lg:flex-none">
                            <i class="las la-check-circle text-lg"></i>
                            <span>Anfrage</span>
                        </span>
                        <button
                            v-else-if="friendshipStatus === 'friends'"
                            type="button"
                            class="inline-flex min-h-11 min-w-[7.25rem] flex-1 items-center justify-center gap-1 rounded-xl border border-error/40 px-2.5 py-2 text-xs font-bold text-error hover:bg-error/10 sm:gap-2 sm:px-4 sm:text-sm lg:flex-none"
                            @click="removeFriend"
                        >
                            <i class="las la-user-times text-lg"></i>
                            <span>Entfernen</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex min-h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-primary hover:border-borderHover"
                            aria-label="Weitere Aktionen"
                            @click="toggleProfileActionMenu"
                        >
                            <i class="las la-ellipsis-v text-xl"></i>
                        </button>
                        <div
                            v-if="profileActionMenuOpen"
                            class="absolute right-0 top-full z-20 mt-2 w-48 overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-primary hover:bg-inputBg"
                                @click="openProfileReport"
                            >
                                <i class="las la-flag text-lg"></i>
                                Melden
                            </button>
                            <button
                                v-if="viewer.has_blocked"
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-primary hover:bg-inputBg"
                                @click="setProfileActionMenuOpen(false); unblockUser()"
                            >
                                <i class="las la-unlock text-lg"></i>
                                Entblockieren
                            </button>
                            <button
                                v-else
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-error hover:bg-error/10"
                                @click="setProfileActionMenuOpen(false); blockUser()"
                            >
                                <i class="las la-ban text-lg"></i>
                                Blockieren
                            </button>
                        </div>
                        </div>
                        <p
                            v-if="friendshipNotice"
                            :class="[
                                'rounded-xl border px-3 py-2 text-center text-xs font-bold lg:w-full lg:border-0 lg:p-0 lg:text-left lg:text-sm',
                                friendshipNotice.type === 'error' ? 'text-error' : 'text-success',
                                friendshipNotice.type === 'error' ? 'border-error/30 bg-error/10' : 'border-success/30 bg-success/10',
                            ]"
                        >
                            {{ friendshipNotice.message }}
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>
