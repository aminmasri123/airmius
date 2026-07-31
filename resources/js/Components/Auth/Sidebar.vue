<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationMark from '../ApplicationMark.vue'
import NavGroup from '@/Components/NavGroup.vue'
import NavItem from './NavItem.vue'
import TeamSwitcher from './TeamSwitcher.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useClubWorkspaceNavigation } from '@/composables/useClubWorkspaceNavigation'

const props = defineProps({
    open: Boolean
})

const emit = defineEmits(['close'])

const page = usePage()
const unreadNotificationsCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const unreadChatsCount = computed(() => page.props.unreadChatsCount || 0)
const pendingFriendInvitationsCount = computed(() => page.props.friendCenter?.pending_received_count || 0)
const { can, hasAny, hasAnyRole } = usePermissions()
const { items: clubWorkspaceItems, hasItems: canUseClubWorkspace } = useClubWorkspaceNavigation()
const hasMultipleClubWorkspaceItems = computed(() => clubWorkspaceItems.value.length > 1)
const singleClubWorkspaceItem = computed(() => clubWorkspaceItems.value[0] || null)
const activeSubscriptionSlugs = computed(() => page.props.auth?.user?.active_subscription_plan_slugs || [])
const currentSubscriptionPlan = computed(() => page.props.auth?.user?.current_subscription?.plan || null)
const hasSportlerPro = computed(() => activeSubscriptionSlugs.value.includes('sportler-pro'))
const storageUsage = computed(() => page.props.auth?.user?.storage_usage || null)
const upgradeLabel = computed(() => {
    if (hasSportlerPro.value) return 'Sportler Pro aktiv'

    return currentSubscriptionPlan.value?.target_actor === 'sportler'
        ? `${currentSubscriptionPlan.value.name} aktiv`
        : 'Upgrade Pro'
})
const upgradeIcon = computed(() => hasSportlerPro.value ? 'las la-check-circle' : 'las la-rocket')
const formatStorage = (bytes) => {
    const value = Number(bytes || 0)

    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}

const canAdmin = computed(() => hasAny([
    'users.view',
    'roles.manage',
    'blog.view',
    'blog.manage',
    'payments.view',
    'invoices.view',
    'operating-contracts.view',
    'subscriptions.view',
    'outfit-subscriptions.manage',
    'sponsors.view',
    'admin.moderation.view',
    'admin.settings.view',
    'system.manage',
]))

const athleteRoles = ['player', 'youth_player', 'minor_player', 'guest_player', 'captain']
const coachRoles = ['coach', 'assistant_coach', 'performance_coach', 'fitness_coach', 'team_manager', 'captain', 'trainer']
const clubRoles = ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller', 'media_manager']
const sponsorRoles = ['sponsor', 'sponsor_manager']
const platformRoles = ['super_admin', 'admin', 'system_admin']

const isPlatformAdmin = computed(() => hasAnyRole(platformRoles))
const isCoach = computed(() => !isPlatformAdmin.value && (hasAnyRole(coachRoles) || can('trainer-cockpit.view')))
const isClubManager = computed(() => !isPlatformAdmin.value && (hasAnyRole(clubRoles) || can('club-cockpit.view') || can('club-memberships.view')))
const isSponsor = computed(() => !isPlatformAdmin.value && (hasAnyRole(sponsorRoles) || can('sponsor.workspace.view')))
const hasOperationalWorkspace = computed(() => isCoach.value || isClubManager.value || isSponsor.value)
const isAthlete = computed(() => !isPlatformAdmin.value && (hasAnyRole(athleteRoles) || !hasOperationalWorkspace.value))
const workspaceCount = computed(() => [isCoach.value, isClubManager.value, isSponsor.value].filter(Boolean).length)
const roleHomeHref = computed(() => {
    if (isPlatformAdmin.value) return route('auth.dashboard')
    if (workspaceCount.value > 1) return route('auth.workspaces.index')
    if (isClubManager.value) return route('auth.club-cockpit.index')
    if (isCoach.value) return route('auth.trainer-cockpit.index')
    if (isSponsor.value) return route('auth.sponsor-workspace.index')

    return route('auth.feed.index')
})
const roleHomeLabel = computed(() => {
    if (isPlatformAdmin.value) return 'Dashboard'
    if (workspaceCount.value > 1) return 'Meine Arbeitsbereiche'
    if (isClubManager.value) return 'Vereins-Cockpit'
    if (isCoach.value) return 'Trainer-Cockpit'
    if (isSponsor.value) return 'Sponsor-Cockpit'

    return 'Mein Sport'
})

const isRtl = computed(() => page.props.direction === 'rtl')

const closeSidebar = () => {
    emit('close')
}
</script>

<template>
    <!-- Overlay Mobile -->
    <div v-if="open" class="fixed inset-0 z-[55] bg-black/55 backdrop-blur-[2px] md:hidden" @click="closeSidebar" />

    <aside
        class="fixed inset-y-0 top-0 z-[60] flex h-dvh w-full flex-col border-border bg-card shadow-2xl transition-transform duration-300 md:w-[260px] md:translate-x-0 md:shadow-none"
        :class="[
            isRtl ? 'right-0 border-l' : 'left-0 border-r',
            open ? 'translate-x-0' : (isRtl ? 'translate-x-full' : '-translate-x-full')
        ]"
    >
        <!-- Mobile header -->
        <div class="flex items-center justify-between p-4 md:hidden">
            <Link
                :href="roleHomeHref"
                class="block w-40 shrink-0"
                @click="closeSidebar"
            >
                <ApplicationMark />
            </Link>

            <button
                type="button"
                class="rounded-lg p-2 hover:bg-muted"
                @click="closeSidebar"
            >
                <i class="las la-times text-xl text-primary"></i>
            </button>
        </div>

        <Link
            :href="roleHomeHref"
            class="hidden w-48 shrink-0 px-4 py-4 md:block"
            @click="closeSidebar"
        >
            <ApplicationMark />
        </Link>

        <!-- <TeamSwitcher /> -->

        <nav class="custom-scrollbar mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <NavItem
                @navigate="closeSidebar"
                :href="roleHomeHref"
                :label="roleHomeLabel"
                icon="las la-home"
            />

            <NavItem
                v-if="can('chat.view')"
                @navigate="closeSidebar"
                :href="route('auth.conversations.index')"
                label="Nachrichten"
                icon="las la-comments"
                :badge="unreadChatsCount || null"
            />
            <NavItem
                v-if="can('notifications.view')"
                @navigate="closeSidebar"
                :href="route('auth.notifications.index')"
                label="Benachrichtigungen"
                icon="las la-bell"
                :badge="unreadNotificationsCount || null"
            />

            <NavGroup
                v-if="isAthlete"
                label="Mein Sport"
                icon="las la-running"
                :initial-open="!hasOperationalWorkspace"
            >
                <NavItem v-if="can('feed.view')" @navigate="closeSidebar" :href="route('auth.feed.index')" label="Feed" icon="las la-newspaper" />
                <NavItem v-if="can('event.index')" @navigate="closeSidebar" :href="route('auth.events.index')" label="Events" icon="las la-calendar" />
                <NavItem @navigate="closeSidebar" :href="route('auth.training.index')" label="Training" icon="las la-clipboard-list" />
                <NavItem @navigate="closeSidebar" :href="route('auth.nutrition.index')" label="Ernährung" icon="las la-apple-alt" />
                <NavItem @navigate="closeSidebar" :href="route('auth.sport-map.index')" label="Sportkarte" icon="las la-route" />
                <NavItem @navigate="closeSidebar" :href="route('auth.sport-matching.index')" label="Sport-Matching" icon="las la-people-arrows" />
                <NavItem
                    v-if="can('friends.view')"
                    @navigate="closeSidebar"
                    :href="route('auth.friends.index')"
                    label="Freunde"
                    icon="las la-user-plus"
                    :badge="pendingFriendInvitationsCount || null"
                />
                <NavItem v-if="can('rides.view')" @navigate="closeSidebar" :href="route('auth.rides.index')" label="Fahrgemeinschaften" icon="las la-car" />
                <NavItem v-if="can('profile.view')" @navigate="closeSidebar" :href="route('auth.badges.index')" label="Meine Badges" icon="las la-medal" />
                <NavItem @navigate="closeSidebar" :href="route('auth.learning.my-courses.index')" label="Meine Kurse" icon="las la-book-open" />
            </NavGroup>

            <NavGroup
                v-if="isCoach"
                label="Trainer & Team"
                icon="las la-chalkboard-teacher"
                :initial-open="!isClubManager && !isSponsor"
            >
                <NavItem @navigate="closeSidebar" :href="route('auth.trainer-cockpit.index')" label="Trainer-Cockpit" icon="las la-tachometer-alt" />
                <NavItem v-if="can('team.index')" @navigate="closeSidebar" :href="route('auth.teams.index')" label="Teams" icon="las la-users" />
                <NavItem v-if="can('event.index')" @navigate="closeSidebar" :href="route('auth.events.index')" label="Events & Anwesenheit" icon="las la-calendar-check" />
                <NavItem @navigate="closeSidebar" :href="route('auth.training.index')" label="Trainingsplanung" icon="las la-clipboard-list" />
                <NavItem v-if="can('file.index')" @navigate="closeSidebar" :href="route('auth.files.index')" label="Teamdateien" icon="las la-folder-open" />
            </NavGroup>

            <NavItem
                v-if="isClubManager && canUseClubWorkspace && hasMultipleClubWorkspaceItems"
                @navigate="closeSidebar"
                :href="clubWorkspaceItems[0]?.href"
                label="Vereinsverwaltung"
                icon="las la-building"
                :subitems="clubWorkspaceItems"
            />
            <NavItem
                v-else-if="isClubManager && singleClubWorkspaceItem"
                @navigate="closeSidebar"
                :href="singleClubWorkspaceItem.href"
                :label="singleClubWorkspaceItem.label"
                :icon="singleClubWorkspaceItem.icon"
                :active-paths="singleClubWorkspaceItem.activePaths"
            />

            <NavGroup
                v-if="isSponsor"
                label="Sponsoring & Reichweite"
                icon="las la-handshake"
                :initial-open="!isClubManager"
            >
                <NavItem @navigate="closeSidebar" :href="route('auth.sponsor-workspace.index')" label="Sponsor-Cockpit" icon="las la-chart-line" />
                <NavItem @navigate="closeSidebar" :href="route('auth.commerce.index')" label="Kampagnen & Angebote" icon="las la-bullhorn" />
                <NavItem @navigate="closeSidebar" :href="route('guest.sponsors')" label="Öffentliche Sponsorenseite" icon="las la-eye" />
            </NavGroup>

            <NavItem v-if="can('guardians.children.view')" @navigate="closeSidebar" :href="route('guardian-access.children')" label="Elternbereich" icon="las la-user-shield" />
            <NavItem v-if="can('file.index') && !isCoach && !isClubManager" @navigate="closeSidebar" :href="route('auth.files.index')" label="Dateien" icon="las la-folder-open" />
            <NavItem @navigate="closeSidebar" :href="route('guest.marketplace')" label="Marketplace" icon="las la-shopping-bag" />
            <NavItem v-if="isAthlete" @navigate="closeSidebar" :href="route('auth.outfit-subscriptions.index')" label="Outfit-Abo" icon="las la-tshirt" />
            <NavItem
                v-if="isAthlete"
                @navigate="closeSidebar"
                :href="route('guest.pricing', { audience: 'sportler' })"
                :label="upgradeLabel"
                :icon="upgradeIcon"
            />

            <Link
                v-if="storageUsage"
                :href="route('auth.files.index')"
                class="mt-3 block rounded-lg border border-border bg-inputBg/60 p-3 text-left"
                @click="closeSidebar"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Speicher') }}</span>
                    <span class="text-xs font-bold text-primary">{{ storageUsage.used_percent }}%</span>
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-card">
                    <div
                        class="h-1.5 rounded-full bg-buttonPrimary"
                        :style="{ width: `${storageUsage.used_percent}%` }"
                    ></div>
                </div>
                <p class="mt-2 text-xs text-secondary">
                    {{ formatStorage(storageUsage.remaining_bytes) }} {{ $t('frei') }}
                </p>
            </Link>

            <NavGroup v-if="canAdmin" label="Admin" icon="las la-shield-alt">
                <NavItem v-if="can('users.view')" @navigate="closeSidebar" :href="route('members.index')" label="Users" icon="las la-user" />
                <NavItem v-if="can('roles.manage')" @navigate="closeSidebar" :href="route('roles-permissions.index')" label="Rollen & Rechte" icon="las la-user-shield" />
                <NavItem v-if="can('blog.view')" @navigate="closeSidebar" :href="route('blogs.index')" label="Blogs" icon="las la-pen-nib" />
                <NavItem v-if="can('blog.manage')" @navigate="closeSidebar" :href="route('blog-categories.index')" label="Blog-Kategorien" icon="las la-list" />
                <NavItem v-if="can('blog.view')" @navigate="closeSidebar" :href="route('admin.media-guidelines.index')" label="Bildmasse" icon="las la-ruler-combined" />
                <NavItem v-if="can('payments.view')" @navigate="closeSidebar" :href="route('payments.index')" label="Payments" icon="las la-credit-card" />
                <NavItem v-if="can('invoices.view')" @navigate="closeSidebar" :href="route('invoices.index')" label="Invoices" icon="las la-file-invoice" />
                <NavItem v-if="can('operating-contracts.view')" @navigate="closeSidebar" :href="route('admin.operating-contracts.index')" label="Betriebskosten" icon="las la-file-contract" />
                <NavItem v-if="can('subscriptions.view')" @navigate="closeSidebar" :href="route('admin.subscriptions.index')" label="Abos" icon="las la-tags" />
                <NavItem v-if="can('subscriptions.view')" @navigate="closeSidebar" :href="route('admin.subscription-invoices.index')" label="Abo-Rechnungen" icon="las la-receipt" />
                <NavItem v-if="can('subscriptions.view')" @navigate="closeSidebar" :href="route('admin.commerce.index')" label="Commerce" icon="las la-chart-line" />
                <NavItem v-if="can('outfit-subscriptions.manage')" @navigate="closeSidebar" :href="route('admin.outfit-subscriptions.index')" label="Outfit-Abos" icon="las la-tshirt" />
                <NavItem v-if="can('sponsors.view')" @navigate="closeSidebar" :href="route('sponsors.index')" label="Sponsors" icon="las la-handshake" />
                <NavItem v-if="can('admin.moderation.view')" @navigate="closeSidebar" :href="route('admin.moderation.index')" label="Moderation" icon="las la-user-check" />
                <NavItem v-if="can('system.manage')" @navigate="closeSidebar" :href="route('admin.sports.index')" label="Sportarten" icon="las la-running" />
                <NavItem v-if="can('system.manage')" @navigate="closeSidebar" :href="route('admin.club-verifications.index')" label="Vereinsprüfung" icon="las la-clipboard-check" />
                <NavItem v-if="can('system.manage')" @navigate="closeSidebar" :href="route('gamification-rules.index')" label="Gamification" icon="las la-trophy" />
                <NavItem v-if="can('system.manage')" @navigate="closeSidebar" :href="route('admin.badges.index')" label="Badges" icon="las la-medal" />
                <NavItem v-if="can('admin.mail-center.view')" @navigate="closeSidebar" :href="route('admin.mail-center.index')" label="Mail-Zentrale" icon="las la-envelope-open-text" />
                <NavItem v-if="can('system.manage')" @navigate="closeSidebar" :href="route('admin.provider-costs.index')" label="Provider-Kosten" icon="las la-chart-pie" />
                <NavItem v-if="can('admin.settings.view')" @navigate="closeSidebar" :href="route('admin.settings.index')" label="Settings" icon="las la-cog" />
            </NavGroup>
        </nav>
    </aside>
</template>
