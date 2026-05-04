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
    <footer class="border-t border-border py-12 px-4 bg-card/40">
        <div class="max-w-6xl mx-auto">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
                <div>
                    <button @click="scrollTo('hero')" class="font-heading font-900 text-xl tracking-tight text-left">
                        <ApplicationLogo class="w-16 h-16" />
                        <span class="text-primary font-[--ubuntu]">AIRMIUS</span>
                    </button>
                    <p class="text-sm text-secondary mt-3 leading-relaxed">
                        {{ $t('guest.footer.description') }}
                    </p>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">{{ $t('guest.footer.product') }}</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <button @click="scrollTo('funktionen')" class="block hover:text-primary transition">{{ $t('guest.nav.features') }}</button>
                        <button @click="scrollTo('vorteile')" class="block hover:text-primary transition">{{ $t('guest.nav.benefits') }}</button>
                        <button @click="scrollTo('sportarten')" class="block hover:text-primary transition">{{ $t('guest.nav.sports') }}</button>
                        <Link :href="route('guest.werbeagentur')" class="block hover:text-primary transition">{{ $t('guest.footer.agency_for_clubs') }}</Link>
                        <Link :href="route('guest.marketplace')" class="block hover:text-primary transition">{{ $t('Marketplace') }}</Link>
                    </div>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">{{ $t('guest.footer.company') }}</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <button @click="scrollTo('ueber')" class="block hover:text-primary transition">{{ $t('guest.nav.about') }}</button>
                        <Link :href="route('guest.blog.index')" class="block hover:text-primary transition">{{ $t('Blog') }}</Link>
                        <button @click="scrollTo('kontakt')" class="block hover:text-primary transition">{{ $t('guest.nav.contact') }}</button>
                        <Link :href="route('guest.jobs')" class="block hover:text-primary transition">{{ $t('Jobs') }}</Link>
                    </div>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">{{ $t('guest.footer.legal') }}</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <Link :href="route('legal.imprint')" class="block hover:text-primary transition">{{ $t('guest.footer.imprint') }}</Link>
                        <Link :href="route('policy.show')" class="block hover:text-primary transition">{{ $t('guest.footer.privacy') }}</Link>
                        <Link :href="route('terms.show')" class="block hover:text-primary transition">{{ $t('guest.footer.terms') }}</Link>
                        <Link :href="route('legal.community')" class="block hover:text-primary transition">{{ $t('guest.footer.community') }}</Link>
                        <Link :href="route('legal.minors')" class="block hover:text-primary transition">{{ $t('guest.footer.minors') }}</Link>
                        <Link :href="route('guardian-access.create')" class="block hover:text-primary transition">{{ $t('guest.footer.guardian') }}</Link>
                        <Link :href="route('legal.cookies')" class="block hover:text-primary transition">{{ $t('guest.footer.cookies') }}</Link>
                        <Link :href="route('legal.withdrawal')" class="block hover:text-primary transition">{{ $t('guest.footer.withdrawal') }}</Link>
                        <Link :href="route('legal.reporting')" class="block hover:text-primary transition">{{ $t('guest.footer.reporting') }}</Link>
                    </div>
                </div>
            </div>

            <div class="border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-secondary">
                <span>{{ $t('guest.footer.copyright') }}</span>
                <span>{{ $t('guest.footer.made') }}</span>
            </div>
        </div>
    </footer>
</template>
