<script setup>
import UserProfileSportCv from '@/Components/Users/UserProfileSportCv.vue'
import UserProfilePrivacyMatrix from '@/Components/Users/UserProfilePrivacyMatrix.vue'

defineProps({
    profileUser: { type: Object, required: true },
    visibleBadges: { type: Array, default: () => [] },
    trustTone: { type: String, default: 'text-air-blue' },
})
</script>

<template>
    <div class="space-y-6">
        <UserProfileSportCv :sport-cv="profileUser.sport_cv" />
        <UserProfilePrivacyMatrix :privacy="profileUser.profile_privacy || profileUser.sport_cv?.privacy_matrix" />

        <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="grid lg:grid-cols-[1.25fr_.75fr]">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">
                            {{ profileUser.gamification.title }}
                        </span>
                        <span class="rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                            {{ profileUser.gamification.streak_days }} Tage Streak
                        </span>
                        <span class="rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                            {{ profileUser.gamification.health_label }}
                        </span>
                    </div>

                    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold uppercase tracking-wide text-secondary">Fortschritt</div>
                            <div class="mt-1 text-3xl font-bold text-primary">Level {{ profileUser.gamification.level }}</div>
                            <div class="mt-1 text-sm text-secondary">
                                {{ profileUser.gamification.xp }} XP von {{ profileUser.gamification.next_level_xp }} XP
                            </div>
                            <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-secondary">
                                Noch {{ profileUser.gamification.xp_to_next_level }} XP bis zum nächsten Level
                            </div>
                        </div>
                        <div class="rounded-xl border border-border bg-inputBg px-4 py-3">
                            <div :class="['text-2xl font-bold', trustTone]">{{ profileUser.gamification.trust_score }}</div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-secondary">Trust Score</div>
                            <div class="mt-1 text-xs text-secondary">Heute {{ profileUser.gamification.earned_today }} XP</div>
                        </div>
                    </div>

                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-inputBg">
                        <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${profileUser.gamification.progress}%` }"></div>
                    </div>
                </div>

                <div class="border-t border-border bg-bg p-5 sm:p-6 lg:border-l lg:border-t-0">
                    <div class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ $t('Badges') }}</div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div
                            v-for="badge in visibleBadges"
                            :key="badge.id"
                            class="rounded-xl border border-border bg-card p-3"
                        >
                            <div class="text-2xl text-primary">
                                <i :class="badge.icon || 'las la-medal'"></i>
                            </div>
                            <div class="mt-2 line-clamp-2 text-sm font-semibold text-primary">{{ badge.name }}</div>
                        </div>
                        <p v-if="!visibleBadges.length" class="col-span-2 text-sm text-secondary">Noch keine Badges sichtbar.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-primary">{{ $t('Profil') }}</h2>
                    <p class="mt-1 text-sm text-secondary">Bio, Sportarten und öffentliche Einordnung.</p>
                </div>
            </div>

            <p v-if="profileUser.bio" class="mt-5 whitespace-pre-line text-sm leading-7 text-primary">
                {{ profileUser.bio }}
            </p>
            <p v-else class="mt-5 rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                Dieses Profil hat noch keine Bio.
            </p>
        </section>
    </div>
</template>
