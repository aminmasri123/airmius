<script setup>
import { Link, router } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

const scrollTo = (id) => {
    const el = document.getElementById(id)

    if (!el) {
        router.visit(`${route('welcome')}#${id}`)
        return
    }

    const offset = 80
    const elementPosition = el.getBoundingClientRect().top + window.pageYOffset

    window.scrollTo({
        top: elementPosition - offset,
        behavior: 'smooth',
    })
}

const safeRoute = (name, fallback, params) => {
    try {
        return route().has(name) ? route(name, params) : fallback
    } catch (error) {
        return fallback
    }
}
</script>

<template>
    <footer class="border-t border-border bg-card/40 px-4 pb-24 pt-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <div class="grid grid-cols-1 gap-4 sm:gap-8 min-[420px]:grid-cols-2 lg:gap-10 xl:grid-cols-4">
                <div class="rounded-2xl border border-border bg-bg/60 p-4 shadow-sm min-[420px]:col-span-2 sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none xl:col-span-1">
                    <button @click="scrollTo('hero')" class="inline-flex items-center gap-3 text-left font-heading text-xl font-900 tracking-tight sm:block">
                        <ApplicationLogo class="h-10 w-10 shrink-0 sm:h-16 sm:w-16" />
                        <span class="font-[--ubuntu] text-primary">AIRMIUS</span>
                    </button>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-secondary sm:mt-4 xl:max-w-none">
                        {{ $t('guest.footer.description') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border bg-bg/60 p-4 shadow-sm sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                    <h4 class="mb-3 flex items-center gap-2 font-heading text-sm font-700 text-primary">
                        <i class="las la-layer-group text-lg text-buttonPrimary sm:hidden"></i>
                        {{ $t('guest.footer.product') }}
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm text-secondary sm:grid-cols-1 sm:gap-1">
                        <button @click="scrollTo('funktionen')" class="flex min-h-10 items-center rounded-xl bg-card px-3 text-left transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.features') }}</button>
                        <button @click="scrollTo('vorteile')" class="flex min-h-10 items-center rounded-xl bg-card px-3 text-left transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.benefits') }}</button>
                        <button @click="scrollTo('sportarten')" class="flex min-h-10 items-center rounded-xl bg-card px-3 text-left transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.sports') }}</button>
                        <Link :href="safeRoute('guest.werbeagentur', '/werbeagentur-für-vereine')" class="flex min-h-10 items-center rounded-xl bg-card px-3 transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.agency_for_clubs') }}</Link>
                        <Link :href="safeRoute('guest.marketplace', '/marketplace')" class="col-span-2 flex min-h-10 items-center rounded-xl bg-card px-3 transition hover:text-primary sm:col-span-1 sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.shop') }}</Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-bg/60 p-4 shadow-sm sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                    <h4 class="mb-3 flex items-center gap-2 font-heading text-sm font-700 text-primary">
                        <i class="las la-building text-lg text-buttonPrimary sm:hidden"></i>
                        {{ $t('guest.footer.company') }}
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm text-secondary sm:grid-cols-1 sm:gap-1">
                        <button @click="scrollTo('über')" class="flex min-h-10 items-center rounded-xl bg-card px-3 text-left transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.about') }}</button>
                        <Link :href="safeRoute('guest.blog.index', '/blog')" class="flex min-h-10 items-center rounded-xl bg-card px-3 transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.blog') }}</Link>
                        <Link :href="safeRoute('guest.sponsors', '/sponsoren')" class="flex min-h-10 items-center rounded-xl bg-card px-3 transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.sponsors') }}</Link>
                        <button @click="scrollTo('kontakt')" class="flex min-h-10 items-center rounded-xl bg-card px-3 text-left transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.nav.contact') }}</button>
                        <Link :href="safeRoute('guest.jobs', '/jobs')" class="col-span-2 flex min-h-10 items-center rounded-xl bg-card px-3 transition hover:text-primary sm:col-span-1 sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.subnav.jobs') }}</Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-bg/60 p-4 shadow-sm min-[420px]:col-span-2 sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none xl:col-span-1">
                    <h4 class="mb-3 flex items-center gap-2 font-heading text-sm font-700 text-primary">
                        <i class="las la-shield-alt text-lg text-buttonPrimary sm:hidden"></i>
                        {{ $t('guest.footer.legal') }}
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm text-secondary md:grid-cols-3 xl:grid-cols-1 xl:gap-1">
                        <Link :href="safeRoute('legal.imprint', '/impressum')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.imprint') }}</Link>
                        <Link :href="safeRoute('policy.show', '/datenschutz')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.privacy') }}</Link>
                        <Link :href="safeRoute('terms.show', '/agb')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.terms') }}</Link>
                        <Link :href="safeRoute('legal.community', '/community-richtlinien')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.community') }}</Link>
                        <Link :href="safeRoute('legal.minors', '/jugendschutz')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.minors') }}</Link>
                        <Link :href="safeRoute('guardian-access.create', '/eltern')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.guardian') }}</Link>
                        <Link :href="safeRoute('legal.cookies', '/cookies')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.cookies') }}</Link>
                        <Link :href="safeRoute('legal.withdrawal', '/widerruf')" class="flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.withdrawal') }}</Link>
                        <Link :href="safeRoute('legal.reporting', '/kontakt-und-melden')" class="col-span-2 flex min-h-10 items-center rounded-xl bg-card px-3 break-words transition hover:text-primary md:col-span-1 sm:min-h-9 sm:rounded-none sm:bg-transparent sm:px-0">{{ $t('guest.footer.reporting') }}</Link>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-col items-center justify-between gap-2 border-t border-border pt-5 text-center text-xs leading-5 text-secondary sm:mt-8 sm:flex-row sm:gap-3 sm:pt-6 sm:text-left">
                <span class="max-w-full break-words">{{ $t('guest.footer.copyright') }}</span>
                <span class="max-w-full break-words sm:text-right">{{ $t('guest.footer.made') }}</span>
            </div>
        </div>
    </footer>
</template>

