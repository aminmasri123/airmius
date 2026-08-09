<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

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

const page = usePage()
const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const isRtl = computed(() => page.props.direction === 'rtl')
const brandName = computed(() => isRtl.value ? 'إيرميوس' : 'Airmius')
const mobileSubnavOpen = ref(false)
const items = computed(() => [
    ...(isAuthenticated.value ? [['las la-newspaper', 'guest.subnav.feed', safeRoute('auth.feed.index', '/feed')]] : []),
    ['las la-bullhorn', 'guest.subnav.top_content', safeRoute('guest.top-inhalte', '/top-inhalte')],
    ['las la-briefcase', 'guest.subnav.jobs', safeRoute('guest.jobs', '/jobs')],
    ['las la-laptop-code', 'guest.subnav.advertising', safeRoute('guest.werbeagentur', '/werbeagentur-für-vereine')],
    ['las la-chalkboard-teacher', 'guest.subnav.e_learning', safeRoute('guest.e-learning', '/e-learning')],
    ['las la-trophy', 'guest.subnav.levels', safeRoute('guest.gamification', '/gamification'), { hideOnMobile: true }],
    ['las la-warehouse', 'guest.subnav.clubs', safeRoute('guest.vereine', '/vereine')],
    ['las la-calendar-alt', 'Events', safeRoute('guest.events', '/veranstaltungen')],
    ['las la-running', 'Sportarten', safeRoute('guest.sports', '/sportarten')],
    ['las la-tags', 'guest.subnav.subscriptions', safeRoute('guest.pricing', '/abos')],
    ['las la-shopping-bag', 'guest.subnav.shop', safeRoute('guest.marketplace', '/marketplace')],
])
</script>

<template>
    <template v-if="vertical">
        <button
            type="button"
            class="fixed bottom-[calc(0.75rem+env(safe-area-inset-bottom))] end-3 z-50 flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-buttonPrimary text-buttonTextPrimary shadow-2xl shadow-black/40 ring-1 ring-black/20 transition active:scale-95 md:hidden"
            :class="mobileSubnavOpen ? 'pointer-events-none scale-90 opacity-0' : 'opacity-100'"
            :aria-expanded="mobileSubnavOpen"
            aria-controls="guest-mobile-subnav"
            :aria-label="$t('guest.subnav.open')"
            @click="mobileSubnavOpen = !mobileSubnavOpen"
        >
            <i :class="[mobileSubnavOpen ? 'las la-times' : 'las la-compass', 'text-2xl']"></i>
        </button>

        <div
            id="guest-mobile-subnav"
            class="fixed inset-x-0 bottom-0 z-40 rounded-t-3xl border-t border-white/10 bg-gradient-to-b from-card/98 to-bg/98 shadow-2xl shadow-black/60 ring-1 ring-white/5 backdrop-blur-xl transition-all duration-300 md:bottom-auto md:end-4 md:start-auto md:top-24 md:w-40 md:translate-y-0 md:rounded-xl md:border md:bg-card/95 md:bg-none md:opacity-100 md:shadow-xl"
            role="navigation"
            :aria-label="$t('guest.subnav.quick_navigation')"
            :class="[
                mobileSubnavOpen ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-[110%] opacity-0 md:pointer-events-auto',
            ]"
        >
            <div class="px-4 pb-2 pt-3 md:hidden">
                <div class="mx-auto mb-3 h-1 w-12 rounded-full bg-white/25"></div>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-wide text-buttonPrimary">{{ brandName }}</p>
                        <p class="text-sm font-black text-primary">{{ $t('guest.subnav.quick_navigation') }}</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-border bg-bg text-primary shadow-sm transition hover:bg-muted"
                        :aria-label="$t('guest.subnav.close')"
                        @click="mobileSubnavOpen = false"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3 px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-1 text-xs text-primary md:flex md:w-full md:grid-cols-none md:flex-col md:items-stretch md:justify-start md:gap-1 md:overflow-visible md:p-2 md:text-secondary">
                <Link
                    v-for="[icon, label, href, options] in items"
                    :key="label"
                    :href="href || '#'"
                    :class="[
                        'flex min-h-[4.7rem] flex-col items-center justify-center rounded-2xl border border-border/80 bg-bg/90 px-2 py-2 text-center font-bold shadow-lg shadow-black/20 transition hover:border-buttonPrimary hover:bg-muted hover:text-primary md:min-h-[3.15rem] md:min-w-0 md:w-full md:flex-row md:justify-start md:gap-2 md:rounded-lg md:border-0 md:bg-transparent md:px-2.5 md:py-2 md:text-start md:shadow-none',
                        options?.hideOnMobile ? 'hidden md:flex' : ''
                    ]"
                    @click="mobileSubnavOpen = false"
                >
                    <span class="mb-1 flex h-8 w-8 items-center justify-center rounded-xl bg-buttonPrimary/12 text-buttonPrimary md:mb-0 md:h-8 md:w-8 md:shrink-0 md:bg-buttonPrimary/10 md:text-buttonPrimary">
                        <i :class="[icon, 'text-xl md:text-2xl']"></i>
                    </span>
                    <span class="block max-w-full text-[10px] font-black leading-tight [hyphens:auto] [overflow-wrap:anywhere] md:mt-0 md:min-w-0 md:flex-1 md:whitespace-normal md:text-[11px] md:[hyphens:none] md:[overflow-wrap:normal] md:[word-break:normal]">{{ $t(label) }}</span>
                </Link>
            </div>
        </div>
    </template>

    <div
        v-else
        class="fixed inset-x-0 bottom-0 z-40 mx-auto h-16 max-w-7xl border-y border-border bg-card/90 backdrop-blur transition-all duration-300 sm:px-6 md:top-16"
        role="navigation"
        :aria-label="$t('guest.subnav.quick_navigation')"
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

        <div
            class="custom-scrollbar flex w-full justify-start gap-3 overflow-x-auto bg-card/95 px-3 py-2 text-xs text-secondary md:hidden"
            :dir="isRtl ? 'rtl' : 'ltr'"
        >
            <Link
                v-for="[icon, label, href, options] in items"
                :key="label"
                :href="href || '#'"
                :class="[
                    'min-w-[4.5rem] shrink-0 rounded-full px-1 py-1 text-center transition hover:text-primary',
                    options?.hideOnMobile ? 'hidden' : ''
                ]"
            >
                <p><i :class="[icon, 'la-2x']"></i></p>
                {{ $t(label) }}
            </Link>
        </div>
    </div>
</template>
