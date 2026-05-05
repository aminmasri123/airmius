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
const pendingFriendInvitationsCount = computed(() => page.props.friendCenter?.pending_received_count || 0)
const { can, hasAny } = usePermissions()

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
            <NavItem v-if="can('dashboard.view')" @click="closeSidebar" :href="route('auth.dashboard')" label="Dashboard" icon="las la-th-large" />
            <NavItem v-if="can('team.index')" @click="closeSidebar" :href="route('auth.teams.index')" label="Teams" icon="las la-users" />
            <NavItem v-if="can('club-memberships.view')" @click="closeSidebar" :href="route('auth.club-memberships.index')" label="Mitglieder" icon="las la-id-card" />
            <NavItem v-if="can('chat.view')" @click="closeSidebar" :href="route('auth.conversations.index')" label="Chat" icon="las la-comments" />
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
            <NavItem v-if="can('settings.view')" @click="closeSidebar" :href="route('auth.settings')" label="Einstellungen" icon="las la-cog" />
            <NavItem @click="closeSidebar" :href="route('auth.commerce.index')" label="Marketplace" icon="las la-store" />
            <NavItem @click="closeSidebar" :href="route('auth.outfit-subscriptions.index')" label="Outfit-Abo" icon="las la-tshirt" />

            <NavGroup v-if="canAdmin" label="Admin" icon="las la-shield-alt">
                <NavItem v-if="can('users.view')" @click="closeSidebar" :href="route('members.index')" label="Users" icon="las la-user" />
                <NavItem v-if="can('roles.manage')" @click="closeSidebar" :href="route('roles-permissions.index')" label="Rollen & Rechte" icon="las la-user-shield" />
                <NavItem v-if="can('blog.view')" @click="closeSidebar" :href="route('blogs.index')" label="Blogs" icon="las la-pen-nib" />
                <NavItem v-if="can('payments.view')" @click="closeSidebar" :href="route('payments.index')" label="Payments" icon="las la-credit-card" />
                <NavItem v-if="can('invoices.view')" @click="closeSidebar" :href="route('invoices.index')" label="Invoices" icon="las la-file-invoice" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.subscriptions.index')" label="Abos" icon="las la-tags" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.subscription-invoices.index')" label="Abo-Rechnungen" icon="las la-receipt" />
                <NavItem v-if="can('subscriptions.view')" @click="closeSidebar" :href="route('admin.commerce.index')" label="Commerce" icon="las la-chart-line" />
                <NavItem v-if="can('outfit-subscriptions.manage')" @click="closeSidebar" :href="route('admin.outfit-subscriptions.index')" label="Outfit-Abos" icon="las la-tshirt" />
                <NavItem v-if="can('sponsors.view')" @click="closeSidebar" :href="route('sponsors.index')" label="Sponsors" icon="las la-handshake" />
                <NavItem v-if="can('admin.moderation.view')" @click="closeSidebar" :href="route('admin.moderation.index')" label="Moderation" icon="las la-user-check" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('admin.sports.index')" label="Sportarten" icon="las la-running" />
                <NavItem v-if="can('system.manage')" @click="closeSidebar" :href="route('gamification-rules.index')" label="Gamification" icon="las la-trophy" />
                <NavItem v-if="can('admin.settings.view')" @click="closeSidebar" :href="route('admin.settings.index')" label="Settings" icon="las la-cog" />
            </NavGroup>
        </nav>
    </aside>
</template>
