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
</script>

<template>
    <footer class="border-t border-border bg-card/40 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <div class="grid grid-cols-1 gap-8 min-[420px]:grid-cols-2 lg:gap-10 xl:grid-cols-4">
                <div class="min-[420px]:col-span-2 xl:col-span-1">
                    <button @click="scrollTo('hero')" class="inline-flex items-center gap-3 font-heading text-xl font-900 tracking-tight text-left sm:block">
                        <ApplicationLogo class="h-12 w-12 shrink-0 sm:h-16 sm:w-16" />
                        <span class="font-[--ubuntu] text-primary">AIRMIUS</span>
                    </button>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-secondary xl:max-w-none">
                        {{ $t('guest.footer.description') }}
                    </p>
                </div>

                <div>
                    <h4 class="mb-3 font-heading text-sm font-600 text-primary">{{ $t('guest.footer.product') }}</h4>
                    <div class="grid grid-cols-1 gap-1 text-sm text-secondary">
                        <button @click="scrollTo('funktionen')" class="min-h-9 text-left transition hover:text-primary">{{ $t('guest.nav.features') }}</button>
                        <button @click="scrollTo('vorteile')" class="min-h-9 text-left transition hover:text-primary">{{ $t('guest.nav.benefits') }}</button>
                        <button @click="scrollTo('sportarten')" class="min-h-9 text-left transition hover:text-primary">{{ $t('guest.nav.sports') }}</button>
                        <Link :href="route('guest.werbeagentur')" class="flex min-h-9 items-center transition hover:text-primary">{{ $t('guest.footer.agency_for_clubs') }}</Link>
                        <Link :href="route('guest.marketplace')" class="flex min-h-9 items-center transition hover:text-primary">{{ $t('Marketplace') }}</Link>
                    </div>
                </div>

                <div>
                    <h4 class="mb-3 font-heading text-sm font-600 text-primary">{{ $t('guest.footer.company') }}</h4>
                    <div class="grid grid-cols-1 gap-1 text-sm text-secondary">
                        <button @click="scrollTo('ueber')" class="min-h-9 text-left transition hover:text-primary">{{ $t('guest.nav.about') }}</button>
                        <Link :href="route('guest.blog.index')" class="flex min-h-9 items-center transition hover:text-primary">{{ $t('Blog') }}</Link>
                        <button @click="scrollTo('kontakt')" class="min-h-9 text-left transition hover:text-primary">{{ $t('guest.nav.contact') }}</button>
                        <Link :href="route('guest.jobs')" class="flex min-h-9 items-center transition hover:text-primary">{{ $t('Jobs') }}</Link>
                    </div>
                </div>

                <div class="min-[420px]:col-span-2 xl:col-span-1">
                    <h4 class="mb-3 font-heading text-sm font-600 text-primary">{{ $t('guest.footer.legal') }}</h4>
                    <div class="grid grid-cols-1 gap-x-4 gap-y-1 text-sm text-secondary min-[420px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-1">
                        <Link :href="route('legal.imprint')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.imprint') }}</Link>
                        <Link :href="route('policy.show')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.privacy') }}</Link>
                        <Link :href="route('terms.show')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.terms') }}</Link>
                        <Link :href="route('legal.community')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.community') }}</Link>
                        <Link :href="route('legal.minors')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.minors') }}</Link>
                        <Link :href="route('guardian-access.create')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.guardian') }}</Link>
                        <Link :href="route('legal.cookies')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.cookies') }}</Link>
                        <Link :href="route('legal.withdrawal')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.withdrawal') }}</Link>
                        <Link :href="route('legal.reporting')" class="flex min-h-9 items-center break-words transition hover:text-primary">{{ $t('guest.footer.reporting') }}</Link>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-col items-start justify-between gap-3 border-t border-border pt-6 text-xs leading-5 text-secondary sm:flex-row sm:items-center">
                <span class="max-w-full break-words">{{ $t('guest.footer.copyright') }}</span>
                <span class="max-w-full break-words sm:text-right">{{ $t('guest.footer.made') }}</span>
            </div>
        </div>
    </footer>
</template>
