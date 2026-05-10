<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    vertical: {
        type: Boolean,
        default: false,
    },
})

const safeRoute = (name, fallback) => {
    try {
        return route().has(name) ? route(name) : fallback
    } catch (error) {
        return fallback
    }
}

const items = [
    ['las la-bullhorn', 'Top Inhalte', safeRoute('guest.top-inhalte', '/top-inhalte')],
    ['las la-briefcase', 'Jobs', safeRoute('guest.jobs', '/jobs')],
    ['las la-laptop-code', 'Werbeagentur', safeRoute('guest.werbeagentur', '/werbeagentur-fuer-vereine')],
    ['las la-chalkboard-teacher', 'E-Learning', safeRoute('guest.e-learning', '/e-learning')],
    ['las la-trophy', 'Gamification', safeRoute('guest.gamification', '/gamification'), { hideOnMobile: true }],
    ['las la-warehouse', 'Vereine', safeRoute('guest.vereine', '/vereine')],
    ['las la-tags', 'Abos', safeRoute('guest.pricing', '/abos')],
    ['las la-shopping-bag', 'Marketplace', safeRoute('guest.marketplace', '/marketplace')],
]
</script>

<template>
    <div
        v-if="vertical"
        class="fixed bottom-0 left-0 right-0 z-40 border-y border-border bg-card/95 backdrop-blur md:bottom-auto md:left-auto md:right-4 md:top-24 md:w-20 md:rounded-xl md:border md:shadow-xl"
    >
        <div class="flex w-full justify-center gap-5 overflow-x-auto px-4 py-2 text-xs text-secondary md:flex-col md:items-stretch md:gap-1 md:overflow-visible md:p-2">
            <Link
                v-for="[icon, label, href, options] in items"
                :key="label"
                :href="href || '#'"
                :class="[
                    'rounded-lg px-2 py-2 text-center transition hover:bg-muted hover:text-primary',
                    options?.hideOnMobile ? 'hidden md:block' : ''
                ]"
            >
                <p><i :class="[icon, 'text-2xl']"></i></p>
                <span class="mt-1 block text-[10px] font-semibold leading-tight md:text-[11px]">{{ $t(label) }}</span>
            </Link>
        </div>
    </div>

    <div
        v-else
        class="fixed bottom-0 left-0 right-0 z-40 mx-auto h-16 max-w-7xl border-y border-border bg-card/90 backdrop-blur transition-all duration-300 sm:px-6 md:top-16"
    >
        <div class="hidden items-center justify-center gap-6 py-2 text-sm font-medium text-secondary md:flex">
            <Link
                v-for="[icon, label, href] in items"
                :key="label"
                :href="href || '#'"
                class="pb-1 text-center transition hover:text-primary"
            >
                <p><i :class="[icon, 'la-2x']"></i></p>
                {{ $t(label) }}
            </Link>
        </div>

        <div class="custom-scrollbar flex w-full justify-center gap-5 overflow-x-auto bg-card/95 px-4 py-2 text-xs text-secondary md:hidden">
            <Link
                v-for="[icon, label, href, options] in items"
                :key="label"
                :href="href || '#'"
                :class="[
                    'rounded-full py-1 text-center transition hover:text-primary whitespace-nowrap',
                    options?.hideOnMobile ? 'hidden' : ''
                ]"
            >
                <p><i :class="[icon, 'la-2x']"></i></p>
                {{ $t(label) }}
            </Link>
        </div>
    </div>
</template>
