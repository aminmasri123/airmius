<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const page = usePage()
const { locale } = useI18n()

const copy = {
    de: {
        today: 'Heute',
        start: 'Start',
        map: 'Karte',
        team: 'Team',
        more: 'Mehr',
    },
    en: {
        today: 'Today',
        start: 'Start',
        map: 'Map',
        team: 'Team',
        more: 'More',
    },
    fr: {
        today: "Aujourd'hui",
        start: 'Depart',
        map: 'Carte',
        team: 'Equipe',
        more: 'Plus',
    },
    ar: {
        today: 'اليوم',
        start: 'ابدأ',
        map: 'الخريطة',
        team: 'الفريق',
        more: 'المزيد',
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
const path = computed(() => page.url || window.location.pathname)

const items = computed(() => [
    {
        key: 'today',
        label: c('today'),
        icon: 'las la-home',
        href: route('auth.dashboard'),
        active: path.value.startsWith('/dashboard'),
    },
    {
        key: 'map',
        label: c('map'),
        icon: 'las la-route',
        href: route('auth.sport-map.index'),
        active: path.value.startsWith('/sport-map'),
    },
    {
        key: 'start',
        label: c('start'),
        icon: 'las la-play',
        href: route('auth.training.logs.create'),
        active: path.value.startsWith('/training/logs/create'),
        primary: true,
    },
    {
        key: 'team',
        label: c('team'),
        icon: 'las la-users',
        href: route('auth.teams.index'),
        active: path.value.startsWith('/teams'),
    },
    {
        key: 'more',
        label: c('more'),
        icon: 'las la-ellipsis-h',
        href: route('auth.settings'),
        active: path.value.startsWith('/settings'),
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

