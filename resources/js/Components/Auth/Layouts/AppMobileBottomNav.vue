<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const page = usePage()
const { locale } = useI18n()

const copy = {
    de: {
        training: 'Training',
        teams: 'Teams',
        feed: 'Feed',
        nutrition: 'Ernährung',
        profile: 'Profil',
    },
    en: {
        training: 'Training',
        teams: 'Teams',
        feed: 'Feed',
        nutrition: 'Nutrition',
        profile: 'Profile',
    },
    fr: {
        training: 'Entrainement',
        teams: 'Equipes',
        feed: 'Fil',
        nutrition: 'Nutrition',
        profile: 'Profil',
    },
    ar: {
        training: 'التدريب',
        teams: 'الفرق',
        feed: 'الخلاصة',
        nutrition: 'التغذية',
        profile: 'الملف',
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
const path = computed(() => page.url || window.location.pathname)

const items = computed(() => [
    {
        key: 'training',
        label: c('training'),
        icon: 'las la-dumbbell',
        href: route('auth.training.index'),
        active: path.value.startsWith('/training'),
    },
    {
        key: 'teams',
        label: c('teams'),
        icon: 'las la-users',
        href: route('auth.teams.index'),
        active: path.value.startsWith('/teams'),
    },
    {
        key: 'feed',
        label: c('feed'),
        icon: 'las la-newspaper',
        href: route('auth.feed.index'),
        active: path.value.startsWith('/feed'),
        primary: true,
    },
    {
        key: 'nutrition',
        label: c('nutrition'),
        icon: 'las la-apple-alt',
        href: route('auth.nutrition.index'),
        active: path.value.startsWith('/nutrition'),
    },
    {
        key: 'profile',
        label: c('profile'),
        icon: 'las la-user',
        href: route('profile.show'),
        active: path.value.startsWith('/user/profile') || path.value.startsWith('/settings'),
    },
])
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-2 pb-[calc(0.35rem+env(safe-area-inset-bottom))] pt-2 shadow-[0_-12px_24px_rgba(0,0,0,0.16)] backdrop-blur md:hidden">
        <div class="mx-auto grid max-w-md grid-cols-5 items-end gap-1">
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
