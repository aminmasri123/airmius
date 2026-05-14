<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationMark from '../ApplicationMark.vue'
import NavGroup from '@/Components/NavGroup.vue'
import NavItem from './NavItem.vue'
import TeamSwitcher from './TeamSwitcher.vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps({
    open: Boolean
})

const emit = defineEmits(['close'])

const page = usePage()
const unreadNotificationsCount = computed(() => page.props.notificationCenter?.unread_count || 0)
const unreadChatsCount = computed(() => page.props.unreadChatsCount || 0)
const pendingFriendInvitationsCount = computed(() => page.props.friendCenter?.pending_received_count || 0)
const { can, hasAny } = usePermissions()
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
    'payments.view',
    'invoices.view',
    'subscriptions.view',
    'outfit-subscriptions.manage',
    'sponsors.view',
    'admin.moderation.view',
    'admin.settings.view',
    'system.manage',
]))

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
        <!-- Close Button Mobile -->
        <div class="flex items-center justify-between p-4 md:hidden">
            <span class="text-sm font-semibold text-primary">Menü</span>

            <button
                type="button"
                class="rounded-lg p-2 hover:bg-muted"
                @click="closeSidebar"
            >
                <i class="las la-times text-xl text-primary"></i>
            </button>
        </div>

        <Link
            :href="route('auth.feed.index')"
            class="block w-48 shrink-0 px-4 py-4"
            @click="closeSidebar"
        >
            <ApplicationMark />
        </Link>

        <!-- <TeamSwitcher /> -->

        <nav class="custom-scrollbar mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <NavItem @click="closeSidebar" :href="route('welcome')" label="Zur Gastseite" icon="las la-external-link-alt" />
            <NavItem v-if="can('dashboard.view')" @click="closeSidebar" :href="route('auth.dashboard')" label="Dashboard" icon="las la-th-large" />
            <NavItem v-if="can('workspaces.view')" @click="closeSidebar" :href="route('auth.workspaces.index')" label="Arbeitsbereiche" icon="las la-compass" />
            <NavItem v-if="can('team.index')" @click="closeSidebar" :href="route('auth.teams.index')" label="Teams" icon="las la-users" />
            <NavItem v-if="can('club-memberships.view')" @click="closeSidebar" :href="route('auth.club-memberships.index')" label="Mitglieder" icon="las la-id-card" />
            <NavItem
                v-if="can('chat.view')"
                @click="closeSidebar"
                :href="route('auth.conversations.index')"
                label="Chat"
                icon="las la-comments"
                :badge="unreadChatsCount || null"
            />
            <NavItem v-if="can('feed.view')" @click="closeSidebar" :href="route('auth.feed.index')" label="Feed" icon="las la-newspaper" />
            <NavItem v-if="can('file.index')" @click="closeSidebar" :href="route('auth.files.index')" label="Dateien" icon="las la-folder-open" />
            <NavItem v-if="can('event.index')" @click="closeSidebar" :href="route('auth.events.index')" label="Events & Training" icon="las la-calendar" />
            <NavItem
                v-if="can('friends.view')"
                @click="closeSidebar"
                :href="route('auth.friends.index')"
                label="Freunde"
                icon="las la-user-plus"
                :badge="pendingFriendInvitationsCount || null"
            />
            <NavItem v-if="can('rides.view')" @click="closeSidebar" :href="route('auth.rides.index')" label="Fahrgemeinschaften" icon="las la-car" />
            <NavItem
                v-if="can('notifications.view')"
                @click="closeSidebar"
                :href="route('auth.notifications.index')"
                label="Benachrichtigungen"
                icon="las la-bell"
                :badge="unreadNotificationsCount || null"
            />
            <NavItem v-if="can('profile.view')" @click="closeSidebar" :href="route('auth.badges.index')" label="Meine Badges" icon="las la-medal" />
            <NavItem v-if="can('settings.view')" @click="closeSidebar" :href="route('auth.settings')" label="Einstellungen" icon="las la-cog" />
            <NavItem v-if="can('guardians.children.view')" @click="closeSidebar" :href="route('guardian-access.children')" label="Elternbereich" icon="las la-user-shield" />
            <NavItem @click="closeSidebar" :href="route('auth.learning.studio.index')" label="Sportschule" icon="las la-graduation-cap" />
            <NavItem @click="closeSidebar" :href="route('guest.marketplace')" label="Marketplace" icon="las la-shopping-bag" />
            <NavItem @click="closeSidebar" :href="route('auth.commerce.index')" label="Commerce" icon="las la-credit-card" />
            <NavItem @click="closeSidebar" :href="route('auth.outfit-subscriptions.index')" label="Outfit-Abo" icon="las la-tshirt" />
            <NavItem
                @click="closeSidebar"
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
                    <span class="text-xs font-semibold uppercase text-secondary">Speicher</span>
                    <span class="text-xs font-bold text-primary">{{ storageUsage.used_percent }}%</span>
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-card">
                    <div
                        class="h-1.5 rounded-full bg-buttonPrimary"
                        :style="{ width: `${storageUsage.used_percent}%` }"
                    ></div>
                </div>
                <p class="mt-2 text-xs text-secondary">
                    {{ formatStorage(storageUsage.remaining_bytes) }} frei
                </p>
            </Link>

            <NavGroup v-if="canAdmin" label="Admin" icon="las la-shield-alt">
                <NavItem v-if="can('users.view')" @click="closeSidebar" :href="route('members.index')" label="Users" icon="las la-user" />
                <NavItem v-if="can('roles.manage')" @click="closeSidebar" :href="route('roles-permissions.index')" label="Rollen & Rechte" icon="las la-user-shield" />
                <NavItem v-if="can('blog.view')" @click="closeSidebar" :href="route('blogs.index')" label="Blogs" icon="las la-pen-nib" />
                <NavItem v-if="can('blog.view')" @click="closeSidebar" :href="route('admin.media-guidelines.index')" label="Bildmasse" icon="las la-ruler-combined" />
                <NavItem v-if="can('payments.view')" @click="closeSidebar" :href="route('payments.index')" label="Payments" icon="las la-credit-card" />
                <NavItem v-if="can('invoices.view')" @click="closeSidebar" :href="route('invoices.index')" label="Invoices" icon="las la-file-invoice" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.subscriptions.index')" label="Abos" icon="las la-tags" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.subscription-invoices.index')" label="Abo-Rechnungen" icon="las la-receipt" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.commerce.index')" label="Commerce" icon="las la-chart-line" />
                <NavItem v-if="can('outfit-subscriptions.manage')" @click="closeSidebar" :href="route('admin.outfit-subscriptions.index')" label="Outfit-Abos" icon="las la-tshirt" />
                <NavItem v-if="can('sponsors.view')" @click="closeSidebar" :href="route('sponsors.index')" label="Sponsors" icon="las la-handshake" />
                <NavItem v-if="can('admin.moderation.view')" @click="closeSidebar" :href="route('admin.moderation.index')" label="Moderation" icon="las la-user-check" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('admin.sports.index')" label="Sportarten" icon="las la-running" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('admin.club-verifications.index')" label="Vereinspruefung" icon="las la-clipboard-check" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('gamification-rules.index')" label="Gamification" icon="las la-trophy" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('admin.badges.index')" label="Badges" icon="las la-medal" />
                <NavItem v-if="can('admin.settings.view')" @click="closeSidebar" :href="route('admin.settings.index')" label="Settings" icon="las la-cog" />
            </NavGroup>
        </nav>
    </aside>
</template>
