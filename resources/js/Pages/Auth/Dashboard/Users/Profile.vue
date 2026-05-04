<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    profileUser: Object,
    posts: { type: Array, default: () => [] },
    viewer: Object,
    sports: { type: Array, default: () => [] },
})

const { t, te } = useI18n()

const follow = () => {
    router.post(route('auth.users.follow', props.profileUser.id), {}, { preserveScroll: true })
}

const unfollow = () => {
    router.delete(route('auth.users.unfollow', props.profileUser.id), { preserveScroll: true })
}

const sendFriendRequest = () => {
    router.post(route('auth.friends.invitations.store'), {
        user_id: props.profileUser.id,
    }, { preserveScroll: true })
}

const acceptFriendRequest = () => {
    if (!props.viewer.friend_invitation_id) return

    router.post(route('auth.friends.invitations.accept', props.viewer.friend_invitation_id), {}, { preserveScroll: true })
}

const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()

const sportForm = useForm({
    sport_id: '',
    status: 'active',
    experience_level: 'beginner',
})

const recommendationForm = useForm({
    relationship: 'team_member',
    body: '',
})

const skillForms = ref({})

const sportLabel = (sport) => {
    if (!sport) return 'Sportart'

    const key = `sports.${sport.slug}`

    return te(key) ? t(key) : sport.name
}

const statusLabel = (status) => ({
    active: 'Betreibe ich',
    wants_to_learn: 'Möchte ich lernen',
    coach: 'Trainiere ich',
    interested: 'Interessiert mich',
}[status] || status)

const levelLabel = (level) => ({
    beginner: 'Einsteiger',
    intermediate: 'Fortgeschritten',
    advanced: 'Erfahren',
    expert: 'Experte',
    learning: 'Lerne ich',
    developing: 'In Entwicklung',
    solid: 'Solide',
    strong: 'Stark',
}[level] || level)

const relationshipLabel = (relationship) => ({
    visitor: 'Besucher',
    friend: 'Freund',
    team_member: 'Teamkollege',
    trainer: 'Trainer',
    club_admin: 'Verein',
}[relationship] || relationship)

const trustTone = computed(() => {
    const trust = props.profileUser.gamification.trust_score

    if (trust >= 115) return 'text-air-green'
    if (trust < 90) return 'text-error'

    return 'text-air-blue'
})

const groupedSkills = computed(() => {
    return props.profileUser.sport_skills.reduce((groups, skill) => {
        const key = skill.sport?.id || 'other'
        groups[key] ??= {
            sport: skill.sport,
            skills: [],
        }
        groups[key].skills.push(skill)

        return groups
    }, {})
})

const skillFormFor = (skill) => {
    skillForms.value[skill.id] ??= useForm({
        self_level: skill.self_level || 'learning',
        is_visible: true,
        notes: skill.notes || '',
        relationship: 'team_member',
        level: 'confirmed',
        comment: '',
    })

    return skillForms.value[skill.id]
}

const addSport = () => {
    sportForm.post(route('auth.profile.sports.store'), {
        preserveScroll: true,
        onSuccess: () => sportForm.reset('sport_id'),
    })
}

const updateSkill = (skill) => {
    const form = skillFormFor(skill)

    form.put(route('auth.profile.skills.update', skill.id), {
        preserveScroll: true,
    })
}

const endorseSkill = (skill) => {
    const form = skillFormFor(skill)

    form.post(route('auth.users.skills.endorse', [props.profileUser.id, skill.id]), {
        preserveScroll: true,
        onSuccess: () => form.reset('comment'),
    })
}

const sendRecommendation = () => {
    recommendationForm.post(route('auth.users.recommendations.store', props.profileUser.id), {
        preserveScroll: true,
        onSuccess: () => recommendationForm.reset('body'),
    })
}

const approveRecommendation = (recommendation) => {
    router.put(route('auth.profile.recommendations.approve', recommendation.id), {}, { preserveScroll: true })
}

const rejectRecommendation = (recommendation) => {
    router.put(route('auth.profile.recommendations.reject', recommendation.id), {}, { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <Head :title="profileUser.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="h-32 bg-card border border-border"></div>
                <div class="px-5 pb-5">
                <div class="-mt-11 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex items-center gap-4">
                        <img
                            v-if="profileUser.profile_photo_url"
                            :src="profileUser.profile_photo_url"
                            :alt="profileUser.name"
                            class="size-24 rounded-full border-4 border-card object-cover"
                        />
                        <div
                            v-else
                            class="flex size-24 items-center justify-center rounded-full border-4 border-card bg-buttonPrimary text-2xl font-semibold text-buttonTextPrimary"
                        >
                            {{ initials(profileUser.name) }}
                        </div>
                        <div>
                            <h1 class="text-2xl font-semibold text-primary">{{ profileUser.name }}</h1>
                        <p v-if="profileUser.email" class="text-sm text-secondary">{{ profileUser.email }}</p>
                        <p v-if="profileUser.athlete_license_number" class="text-sm text-secondary">
                            Lizenznummer: {{ profileUser.athlete_license_number }}
                        </p>
                        <p class="mt-1 text-xs uppercase tracking-wide text-secondary">
                                {{ profileUser.profile_visibility === 'private' ? 'Privates Profil' : 'Öffentliches Profil' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link
                            v-if="viewer.is_self"
                            :href="route('profile.show')"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover"
                        >
                            Profil bearbeiten
                        </Link>
                        <template v-else>
                            <button
                                v-if="viewer.can_follow && !viewer.is_following"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="follow"
                            >
                                Folgen
                            </button>
                            <button
                                v-else-if="viewer.can_follow"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover"
                                @click="unfollow"
                            >
                                Entfolgen
                            </button>

                            <button
                                v-if="viewer.can_send_friend_request"
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:border-borderHover"
                                @click="sendFriendRequest"
                            >
                                Freundschaft anfragen
                            </button>
                            <button
                                v-else-if="viewer.friendship_status === 'received'"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="acceptFriendRequest"
                            >
                                Anfrage annehmen
                            </button>
                            <span
                                v-else-if="viewer.friendship_status === 'sent'"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-secondary"
                            >
                                Anfrage gesendet
                            </span>
                            <span
                                v-else-if="viewer.friendship_status === 'friends'"
                                class="rounded-lg border border-border px-4 py-2 text-sm text-secondary"
                            >
                                Befreundet
                            </span>
                        </template>
                    </div>
                </div>
                </div>
            </section>

            <section v-if="viewer.can_view_private_profile" class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.followers_count }}</div>
                    <div class="text-sm text-secondary">Follower</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.following_count }}</div>
                    <div class="text-sm text-secondary">Folgt</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ profileUser.posts_count }}</div>
                    <div class="text-sm text-secondary">Beiträge</div>
                </div>
            </section>

            <section v-if="viewer.can_view_private_profile" class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="grid gap-0 lg:grid-cols-[1.2fr_1fr]">
                    <div class="p-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">
                                {{ profileUser.gamification.rank }}
                            </span>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                {{ profileUser.gamification.streak_days }} Tage Streak
                            </span>
                        </div>
                        <div class="mt-4 text-3xl font-bold text-primary">Level {{ profileUser.gamification.level }}</div>
                        <div class="mt-1 text-sm text-secondary">
                            {{ profileUser.gamification.xp }} XP · nächstes Level bei {{ profileUser.gamification.next_level_xp }} XP
                        </div>
                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-inputBg">
                            <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${profileUser.gamification.progress}%` }"></div>
                        </div>
                        <div class="mt-2 text-xs text-secondary">
                            Fortschritt basiert auf sinnvoller Aktivität, Skills, Bestätigungen, Empfehlungen und hilfreichen Beiträgen.
                        </div>
                    </div>

                    <div class="border-t border-border bg-bg p-5 lg:border-l lg:border-t-0">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Trust & Fairness</h2>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-border bg-card p-3">
                                <div :class="['text-2xl font-bold', trustTone]">{{ profileUser.gamification.trust_score }}</div>
                                <div class="text-xs text-secondary">Trust Score</div>
                            </div>
                            <div class="rounded-lg border border-border bg-card p-3">
                                <div class="text-2xl font-bold text-primary">x{{ profileUser.gamification.trust_multiplier }}</div>
                                <div class="text-xs text-secondary">XP-Multiplikator</div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-secondary">
                            Daily Limits, Trust-Multiplikator und Streak-Boni schützen vor Spam und fördern echte sportliche Entwicklung.
                        </p>
                    </div>
                </div>
            </section>

            <!-- <section v-if="viewer.can_view_private_profile" class="rounded-lg border border-border bg-card p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-primary">Level {{ profileUser.gamification.level }}</div>
                        <div class="text-sm text-secondary">{{ profileUser.gamification.title }} · {{ profileUser.gamification.xp }} XP</div>
                    </div>
                    <div class="w-full sm:w-64">
                        <div class="h-2 overflow-hidden rounded-full bg-inputBg">
                            <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${profileUser.gamification.progress}%` }"></div>
                        </div>
                        <div class="mt-1 text-xs text-secondary">Nächstes Level bei {{ profileUser.gamification.next_level_xp }} XP</div>
                    </div>
                </div>
            </section> -->

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <section class="rounded-lg border border-border bg-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Profil</h2>
                        <p v-if="viewer.can_view_private_profile && profileUser.bio" class="mt-3 whitespace-pre-line text-sm text-primary">
                            {{ profileUser.bio }}
                        </p>
                        <p v-else-if="viewer.can_view_private_profile" class="mt-3 text-sm text-secondary">
                            Dieses Profil hat noch keine Bio.
                        </p>
                        <p v-else class="mt-3 text-sm text-secondary">
                            Dieses Profil ist privat.
                        </p>
                    </section>

                    <section v-if="viewer.can_view_private_profile" class="rounded-lg border border-border bg-card p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Sportliches Profil</h2>
                                <p class="mt-1 text-sm text-secondary">Sportarten, Ziele und automatisch passende Skills für dieses Profil.</p>
                            </div>
                            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                                {{ profileUser.sport_profiles.length }} Sportarten
                            </span>
                        </div>

                        <form v-if="viewer.is_self" class="mt-5 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-[1fr_160px_160px_auto]" @submit.prevent="addSport">
                            <SearchableSelect
                                v-model="sportForm.sport_id"
                                :options="sports"
                                value-key="id"
                                translation-prefix="sports"
                                category-translation-prefix="sport_categories"
                                placeholder="Sportart suchen"
                            />
                            <select v-model="sportForm.status" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="active">Betreibe ich</option>
                                <option value="wants_to_learn">Möchte ich lernen</option>
                                <option value="coach">Trainiere ich</option>
                                <option value="interested">Interessiert mich</option>
                            </select>
                            <select v-model="sportForm.experience_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="beginner">Einsteiger</option>
                                <option value="intermediate">Fortgeschritten</option>
                                <option value="advanced">Erfahren</option>
                                <option value="expert">Experte</option>
                            </select>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                                Hinzufügen
                            </button>
                        </form>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="profile in profileUser.sport_profiles"
                                :key="profile.id"
                                class="rounded-full border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            >
                                {{ sportLabel(profile.sport) }} · {{ statusLabel(profile.status) }} · {{ levelLabel(profile.experience_level) }}
                            </span>
                            <span v-if="!profileUser.sport_profiles.length" class="text-sm text-secondary">Noch keine Sportarten hinterlegt.</span>
                        </div>
                    </section>

                    <section v-if="viewer.can_view_private_profile" class="rounded-lg border border-border bg-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Skills & Bestätigungen</h2>
                        <p class="mt-1 text-sm text-secondary">Skills entstehen aus den gewählten Sportarten. Andere können sie bestätigen und Kontext geben.</p>

                        <div class="mt-5 space-y-5">
                            <article v-for="group in groupedSkills" :key="group.sport?.id" class="rounded-lg border border-border bg-bg p-4">
                                <h3 class="font-semibold text-primary">{{ sportLabel(group.sport) }}</h3>

                                <div class="mt-4 space-y-3">
                                    <div v-for="skill in group.skills" :key="skill.id" class="rounded-lg border border-border bg-card p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-primary">{{ skill.skill.name }}</h4>
                                                <p class="mt-1 text-sm text-secondary">{{ skill.skill.description }}</p>
                                                <p class="mt-2 text-xs text-secondary">
                                                    Eigenes Level: {{ levelLabel(skill.self_level) }} · {{ skill.endorsements_count }} Bestätigungen
                                                </p>
                                            </div>

                                            <button
                                                v-if="!viewer.is_self"
                                                type="button"
                                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                                                :disabled="skill.viewer_has_endorsed"
                                                @click="endorseSkill(skill)"
                                            >
                                                {{ skill.viewer_has_endorsed ? 'Bestätigt' : 'Bestätigen' }}
                                            </button>
                                        </div>

                                        <form v-if="viewer.is_self" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]" @submit.prevent="updateSkill(skill)">
                                            <select v-model="skillFormFor(skill).self_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                <option value="learning">Lerne ich</option>
                                                <option value="developing">In Entwicklung</option>
                                                <option value="solid">Solide</option>
                                                <option value="strong">Stark</option>
                                                <option value="expert">Experte</option>
                                            </select>
                                            <input v-model="skillFormFor(skill).notes" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Kurze Notiz, optional" />
                                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                                        </form>

                                        <form v-else-if="!skill.viewer_has_endorsed" class="mt-3 grid gap-2 sm:grid-cols-[150px_150px_1fr]" @submit.prevent="endorseSkill(skill)">
                                            <select v-model="skillFormFor(skill).relationship" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                <option value="visitor">Besucher</option>
                                                <option value="friend">Freund</option>
                                                <option value="team_member">Teamkollege</option>
                                                <option value="trainer">Trainer</option>
                                                <option value="club_admin">Verein</option>
                                            </select>
                                            <select v-model="skillFormFor(skill).level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                                <option value="confirmed">Kann ich bestätigen</option>
                                                <option value="good">Gut</option>
                                                <option value="strong">Stark</option>
                                                <option value="exceptional">Außergewöhnlich</option>
                                            </select>
                                            <input v-model="skillFormFor(skill).comment" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Kommentar, optional" />
                                        </form>

                                        <div v-if="skill.endorsements.length" class="mt-3 flex flex-wrap gap-2">
                                            <span v-for="endorsement in skill.endorsements" :key="endorsement.id" class="rounded-full bg-inputBg px-3 py-1 text-xs text-secondary">
                                                {{ endorsement.endorser.name }} · {{ relationshipLabel(endorsement.relationship) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <p v-if="!profileUser.sport_skills.length" class="text-sm text-secondary">Sobald Sportarten hinzugefügt werden, erscheinen hier passende Skills.</p>
                        </div>
                    </section>

                    <section v-if="viewer.can_view_private_profile" class="rounded-lg border border-border bg-card p-5">
                        <h2 class="text-lg font-semibold text-primary">Empfehlungen</h2>
                        <p class="mt-1 text-sm text-secondary">Empfehlungen werden erst nach Freigabe auf dem Profil sichtbar.</p>

                        <form v-if="!viewer.is_self" class="mt-4 space-y-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="sendRecommendation">
                            <select v-model="recommendationForm.relationship" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="visitor">Besucher</option>
                                <option value="friend">Freund</option>
                                <option value="team_member">Teamkollege</option>
                                <option value="trainer">Trainer</option>
                                <option value="club_admin">Verein</option>
                            </select>
                            <textarea v-model="recommendationForm.body" rows="4" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Schreibe konkret, wobei du diese Person erlebt hast."></textarea>
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Empfehlung senden</button>
                        </form>

                        <div class="mt-5 space-y-3">
                            <article v-for="recommendation in profileUser.recommendations" :key="recommendation.id" class="rounded-lg border border-border bg-bg p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm leading-relaxed text-primary">{{ recommendation.body }}</p>
                                        <p class="mt-2 text-xs text-secondary">
                                            {{ recommendation.author.name }} · {{ relationshipLabel(recommendation.relationship) }} · {{ recommendation.status === 'pending' ? 'wartet auf Freigabe' : 'veröffentlicht' }}
                                        </p>
                                    </div>
                                    <div v-if="viewer.is_self && recommendation.status === 'pending'" class="flex gap-2">
                                        <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary" @click="approveRecommendation(recommendation)">Freigeben</button>
                                        <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="rejectRecommendation(recommendation)">Ablehnen</button>
                                    </div>
                                </div>
                            </article>

                            <p v-if="!profileUser.recommendations.length" class="text-sm text-secondary">Noch keine Empfehlungen sichtbar.</p>
                        </div>
                    </section>

                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3 text-xs text-secondary">
                            <Link v-if="post.team" :href="route('auth.teams.show', post.team.id)" class="font-medium text-primary hover:underline">{{ post.team.name }}</Link>
                            <Link v-else-if="post.club" :href="route('auth.clubs.show', post.club.id)" class="font-medium text-primary hover:underline">{{ post.club.name }}</Link>
                            <span v-else class="font-medium text-primary">Public</span>
                            <span>· {{ formatDate(post.created_at) }}</span>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} Likes</span>
                            <span>{{ post.comments_count }} Kommentare</span>
                        </div>
                    </article>
                </section>

                <aside v-if="viewer.can_view_private_profile" class="space-y-4">
                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Teams</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="team in profileUser.teams" :key="team.id" :href="route('auth.teams.show', team.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">{{ initials(team.name) }}</div>
                                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ team.name }}</span>
                            </Link>
                            <p v-if="!profileUser.teams.length" class="text-sm text-secondary">Keine Teams sichtbar.</p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Vereine</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="club in profileUser.clubs" :key="club.id" :href="route('auth.clubs.show', club.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div class="flex h-9 w-9 items-center justify-center rounded bg-inputBg text-sm font-semibold text-primary">{{ initials(club.name) }}</div>
                                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ club.name }}</span>
                            </Link>
                            <p v-if="!profileUser.clubs.length" class="text-sm text-secondary">Keine Vereine sichtbar.</p>
                        </div>
                    </section>
                </aside>
            </div>

            <section v-if="viewer.can_manage_roles" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Rollen & Berechtigungen</h2>
                <div class="mt-3 space-y-3 text-sm">
                    <div>
                        <div class="font-medium text-primary">Rollen</div>
                        <div class="mt-1 text-secondary">{{ profileUser.roles.length ? profileUser.roles.join(', ') : 'Keine Rollen' }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-primary">Berechtigungen</div>
                        <div class="mt-1 max-h-32 overflow-auto text-secondary">
                            {{ profileUser.permissions.length ? profileUser.permissions.join(', ') : 'Keine Berechtigungen' }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
