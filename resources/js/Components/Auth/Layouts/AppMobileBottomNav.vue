<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'

const page = usePage()
const { locale } = useI18n()
const { can, hasAnyRole } = usePermissions()

const copy = {
    de: {
        training: 'Training',
        teams: 'Teams',
        feed: 'Feed',
        nutrition: 'Ernährung',
        profile: 'Profil',
        coach: 'Coach',
        club: 'Verein',
        sponsors: 'Sponsoren',
        events: 'Events',
        files: 'Dateien',
        messages: 'Chat',
        commerce: 'Kampagnen',
        workspaces: 'Bereiche',
        notifications: 'Inbox',
        publicSponsors: 'Öffentlich',
        dashboard: 'Dashboard',
        users: 'Nutzer',
    },
    en: {
        training: 'Training',
        teams: 'Teams',
        feed: 'Feed',
        nutrition: 'Nutrition',
        profile: 'Profile',
        coach: 'Coach', club: 'Club', sponsors: 'Sponsors', events: 'Events', files: 'Files', messages: 'Chat', commerce: 'Campaigns', workspaces: 'Workspaces', notifications: 'Inbox', publicSponsors: 'Public', dashboard: 'Dashboard', users: 'Users',
    },
    fr: {
        training: 'Entrainement',
        teams: 'Equipes',
        feed: 'Fil',
        nutrition: 'Nutrition',
        profile: 'Profil',
        coach: 'Coach', club: 'Club', sponsors: 'Sponsors', events: 'Événements', files: 'Fichiers', messages: 'Chat', commerce: 'Campagnes', workspaces: 'Espaces', notifications: 'Inbox', publicSponsors: 'Public', dashboard: 'Tableau', users: 'Utilisateurs',
    },
    ar: {
        training: 'التدريب',
        teams: 'الفرق',
        feed: 'الخلاصة',
        nutrition: 'التغذية',
        profile: 'الملف',
        coach: 'المدرب', club: 'النادي', sponsors: 'الرعاة', events: 'الفعاليات', files: 'الملفات', messages: 'الدردشة', commerce: 'الحملات', workspaces: 'المساحات', notifications: 'الوارد', publicSponsors: 'عام', dashboard: 'الرئيسية', users: 'المستخدمون',
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
const path = computed(() => page.url || window.location.pathname)

const coachRoles = ['coach', 'assistant_coach', 'performance_coach', 'fitness_coach', 'team_manager', 'captain', 'trainer']
const clubRoles = ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller', 'media_manager']
const sponsorRoles = ['sponsor', 'sponsor_manager']
const platformRoles = ['super_admin', 'admin', 'system_admin']
const isPlatformAdmin = computed(() => hasAnyRole(platformRoles))
const isCoach = computed(() => !isPlatformAdmin.value && (hasAnyRole(coachRoles) || can('trainer-cockpit.view')))
const isClub = computed(() => !isPlatformAdmin.value && (hasAnyRole(clubRoles) || can('club-cockpit.view')))
const isSponsor = computed(() => !isPlatformAdmin.value && (hasAnyRole(sponsorRoles) || can('sponsor.workspace.view')))
const operationalCount = computed(() => [isCoach.value, isClub.value, isSponsor.value].filter(Boolean).length)

const item = (key, icon, href, activePath, primary = false) => ({
    key,
    label: c(key),
    icon,
    href,
    active: Array.isArray(activePath)
        ? activePath.some((candidate) => path.value.startsWith(candidate))
        : path.value.startsWith(activePath),
    primary,
})

const items = computed(() => {
    const profile = item('profile', 'las la-user', route('profile.show'), ['/user/profile', '/settings'])
    if (isPlatformAdmin.value) {
        return [
            item('dashboard', 'las la-tachometer-alt', route('auth.dashboard'), '/dashboard', true),
            ...(can('users.view') ? [item('users', 'las la-users-cog', route('members.index'), '/users')] : []),
            item('notifications', 'las la-bell', route('auth.notifications.index'), '/notifications'),
            profile,
        ]
    }
    if (operationalCount.value > 1) {
        return [
            item('workspaces', 'las la-compass', route('auth.workspaces.index'), '/workspaces', true),
            ...(can('chat.view') ? [item('messages', 'las la-comments', route('auth.conversations.index'), '/conversations')] : []),
            item('feed', 'las la-newspaper', route('auth.feed.index'), '/feed'),
            item('notifications', 'las la-bell', route('auth.notifications.index'), '/notifications'),
            profile,
        ].slice(0, 5)
    }
    if (isClub.value) {
        return [
            item('club', 'las la-building', route('auth.club-cockpit.index'), '/club-cockpit', true),
            item('teams', 'las la-users', route('auth.teams.index'), ['/teams', '/clubs']),
            item('events', 'las la-calendar-check', route('auth.events.index'), '/events'),
            item('files', 'las la-folder-open', route('auth.files.index'), '/files'),
            profile,
        ]
    }
    if (isCoach.value) {
        return [
            item('coach', 'las la-chalkboard-teacher', route('auth.trainer-cockpit.index'), '/trainer-cockpit', true),
            item('training', 'las la-dumbbell', route('auth.training.index'), '/training'),
            item('teams', 'las la-users', route('auth.teams.index'), ['/teams', '/clubs']),
            item('events', 'las la-calendar-check', route('auth.events.index'), '/events'),
            profile,
        ]
    }
    if (isSponsor.value) {
        return [
            item('sponsors', 'las la-handshake', route('auth.sponsor-workspace.index'), '/sponsor-cockpit', true),
            item('commerce', 'las la-bullhorn', route('auth.commerce.index'), '/commerce'),
            item('publicSponsors', 'las la-eye', route('guest.sponsors'), '/sponsoren'),
            item('notifications', 'las la-bell', route('auth.notifications.index'), '/notifications'),
            profile,
        ]
    }
    return [
        item('training', 'las la-dumbbell', route('auth.training.index'), '/training'),
        item('teams', 'las la-users', route('auth.teams.index'), ['/teams', '/clubs']),
        item('feed', 'las la-newspaper', route('auth.feed.index'), '/feed', true),
        item('nutrition', 'las la-apple-alt', route('auth.nutrition.index'), '/nutrition'),
        profile,
    ]
})
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-2 pb-[calc(0.35rem+env(safe-area-inset-bottom))] pt-2 shadow-[0_-12px_24px_rgba(0,0,0,0.16)] backdrop-blur md:hidden">
        <div
            class="mx-auto grid max-w-md items-end gap-1"
            :style="{ gridTemplateColumns: `repeat(${Math.max(items.length, 1)}, minmax(0, 1fr))` }"
        >
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                class="flex min-w-0 flex-col items-center justify-center gap-1 rounded-lg px-1 py-1.5 text-[11px] font-semibold transition"
                :class="[
                    item.primary
                        ? 'relative -mt-6 h-16 bg-buttonPrimary text-buttonTextPrimary shadow-lg'
                        : (item.active ? 'bg-muted text-primary' : 'text-secondary hover:bg-muted hover:text-primary')
                ]"
            >
                <span
                    class="grid place-items-center"
                    :class="item.primary ? 'h-8 w-8 rounded-full bg-white/15' : 'h-6 w-6'"
                >
                    <i :class="[item.icon, item.primary ? 'text-2xl' : 'text-xl']"></i>
                </span>
                <span class="w-full truncate text-center leading-none">{{ item.label }}</span>
            </Link>
        </div>
    </nav>
</template>
