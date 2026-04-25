<!-- Components/Sidebar.vue -->
<script setup>
import TeamSwitcher from './TeamSwitcher.vue'
import NavItem from './NavItem.vue'
import UserCard from './UserCard.vue'
import { ref } from 'vue'

const isOpen = ref(false)
</script>

<template>
    <!-- Mobile Toggle Button -->
    <button v-if="!isOpen" class="md:hidden p-3 text-white fixed top-2 right-2 z-50" @click="isOpen = true">
        <i class="las la-bars text-xl"></i>
    </button>

    <!-- Overlay -->
    <div v-if="isOpen" class="fixed inset-0 bg-black/50 z-50 md:hidden" @click="isOpen = false" />
    <!-- Sidebar -->
    <aside class="fixed md:static z-[60] top-0 left-0 h-full w-[260px] bg-card border-r border-border flex flex-col
         transition-transform duration-300 md:translate-x-0" :class="isOpen ? 'translate-x-0' : '-translate-x-full'">
        <!-- Close Button (Mobile) -->
        <div class="md:hidden p-4">
            <button @click="isOpen = false">
                <i class="las la-times text-xl text-white"></i>
            </button>
        </div>

        <!-- Logo -->
        <div class="p-5 flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-500 rounded-xl flex items-center justify-center">
                A
            </div>
            <div>
                <div class="font-semibold text-sm">Airmius</div>
                <div class="text-xs text-gray-500">Sports Hub</div>
            </div>
        </div>

        <!-- Teams -->
        <TeamSwitcher />

        <!-- Navigation -->
        <nav class="flex-1 px-3 space-y-1 mt-4 overflow-y-auto">
            <NavItem @click="isOpen = false" :href="route('auth.dashboard')" label="Dashboard" icon="las la-th-large" />
            <NavItem @click="isOpen = false" :href="route('auth.teams.index')" label="Teams" icon="las la-users" />
            <NavItem @click="isOpen = false" :href="route('auth.conversations.index')" label="Chat"
                icon="las la-comments" />

            <NavItem @click="isOpen = false" :href="route('profile.show')" label="Mein Profil" icon="las la-user" />
            <NavItem @click="isOpen = false" href="" label="Feed" icon="las la-newspaper" />
            <NavItem @click="isOpen = false" href="" label="Events & Training" icon="las la-calendar" />
            <NavItem @click="isOpen = false" href="" label="Nachrichten" icon="las la-comment-dots" badge="3" />
            <NavItem @click="isOpen = false" href="" label="Statistiken" icon="las la-chart-bar" />
            <NavItem @click="isOpen = false" href="" label="Sport-Buddys" icon="las la-user-plus" />
            <NavItem @click="isOpen = false" href="" label="Verfügbarkeit" icon="las la-clock" />
            <NavItem @click="isOpen = false" href="" label="Fahrgemeinschaften" icon="las la-car" />
            <NavItem @click="isOpen = false" href="" label="Benachrichtigungen" icon="las la-bell" badge="5" />
            <NavItem @click="isOpen = false" href="" label="Equipment" icon="las la-shopping-cart" />
            <NavItem @click="isOpen = false" :href="route('auth.settings')" label="Einstellungen" icon="las la-cog" />
        </nav>

        <!-- User -->
        <UserCard />
    </aside>
</template>
