<!-- Components/Sidebar.vue -->
<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationMark from '../ApplicationMark.vue'
import NavGroup from '@/Components/NavGroup.vue'
import NavItem from './NavItem.vue'
import TeamSwitcher from './TeamSwitcher.vue'
import { usePermissions } from '@/composables/usePermissions'

const page = usePage()
const unreadNotificationsCount = page.props.notificationCenter?.unread_count || 0
const { can, hasAny } = usePermissions()

const canAdmin = computed(() => hasAny([
    'users.view',
    'roles.manage',
    'blog.view',
    'payments.view',
    'invoices.view',
    'sponsors.view',
    'admin.settings.view',
    'system.manage',
]))
const isOpen = ref(false)
const isRtl = computed(() => page.props.direction === 'rtl')
</script>

<template>
    <button
        v-if="!isOpen"
        class="fixed top-2 z-50 rounded-xl border border-border bg-card/90 p-3 text-primary shadow md:hidden"
        :class="isRtl ? 'left-2' : 'right-2'"
        @click="isOpen = true"
    >
        <i class="las la-bars text-xl"></i>
    </button>

    <div v-if="isOpen" class="fixed inset-0 bg-black/50 z-50 md:hidden" @click="isOpen = false" />

    <aside
        class="fixed top-0 z-[60] flex h-full w-[260px] flex-col border-border bg-card transition-transform duration-300 md:static md:translate-x-0"
        :class="[
            isRtl ? 'right-0 border-l' : 'left-0 border-r',
            isOpen ? 'translate-x-0' : (isRtl ? 'translate-x-full' : '-translate-x-full')
        ]"
    >
        <div class="md:hidden p-4">
            <button @click="isOpen = false">
                <i class="las la-times text-xl text-primary"></i>
            </button>
        </div>

        <Link :href="route('auth.feed.index')" class="w-48 py-4">
            <ApplicationMark />
        </Link>

        <TeamSwitcher />

        <nav class="flex-1 px-3 space-y-1 mt-4 overflow-y-auto custom-scrollbar">
            <NavItem v-if="can('dashboard.view')" @click="isOpen = false" :href="route('auth.dashboard')" label="Dashboard" icon="las la-th-large" />
            <NavItem v-if="can('team.index')" @click="isOpen = false" :href="route('auth.teams.index')" label="Teams" icon="las la-users" />
            <NavItem v-if="can('chat.view')" @click="isOpen = false" :href="route('auth.conversations.index')" label="Chat" icon="las la-comments" />
            <NavItem v-if="can('feed.view')" @click="isOpen = false" :href="route('auth.feed.index')" label="Feed" icon="las la-newspaper" />
            <NavItem v-if="can('file.index')" @click="isOpen = false" :href="route('auth.files.index')" label="Dateien" icon="las la-folder-open" />
            <NavItem v-if="can('event.index')" @click="isOpen = false" :href="route('auth.events.index')" label="Events & Training" icon="las la-calendar" />
            <NavItem v-if="can('friends.view')" @click="isOpen = false" :href="route('auth.friends.index')" label="Freunde" icon="las la-user-plus" />
            <NavItem v-if="can('rides.view')" @click="isOpen = false" :href="route('auth.rides.index')" label="Fahrgemeinschaften" icon="las la-car" />
            <NavItem
                v-if="can('notifications.view')"
                @click="isOpen = false"
                :href="route('auth.notifications.index')"
                label="Benachrichtigungen"
                icon="las la-bell"
                :badge="unreadNotificationsCount || null"
            />
            <NavItem v-if="can('settings.view')" @click="isOpen = false" :href="route('auth.settings')" label="Einstellungen" icon="las la-cog" />

            <NavGroup v-if="canAdmin" label="Admin" icon="las la-shield-alt">
                <NavItem v-if="can('users.view')" @click="isOpen = false" :href="route('members.index')" label="Users" icon="las la-user" />
                <NavItem v-if="can('roles.manage')" @click="isOpen = false" :href="route('roles-permissions.index')" label="Rollen & Rechte" icon="las la-user-shield" />
                <NavItem v-if="can('blog.view')" @click="isOpen = false" :href="route('blogs.index')" label="Blogs" icon="las la-pen-nib" />
                <NavItem v-if="can('payments.view')" @click="isOpen = false" :href="route('payments.index')" label="Payments" icon="las la-credit-card" />
                <NavItem v-if="can('invoices.view')" @click="isOpen = false" :href="route('invoices.index')" label="Invoices" icon="las la-file-invoice" />
                <NavItem v-if="can('sponsors.view')" @click="isOpen = false" :href="route('sponsors.index')" label="Sponsors" icon="las la-handshake" />
                <NavItem v-if="can('system.manage')" @click="isOpen = false" :href="route('gamification-rules.index')" label="Gamification" icon="las la-trophy" />
                <NavItem v-if="can('admin.settings.view')" @click="isOpen = false" :href="route('settings.index')" label="Settings" icon="las la-cog" />
            </NavGroup>
        </nav>
    </aside>
</template>
