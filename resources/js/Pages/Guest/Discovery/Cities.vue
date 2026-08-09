<script setup>
import Footer from '@/Components/Guest/Footer.vue'
import Nav from '@/Components/Guest/Nav.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    cities: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
})

const { t } = useI18n()
</script>

<template>
    <SeoHead
        :title="seo.title || t('public_discovery.cities.meta_title')"
        :description="seo.description || t('public_discovery.cities.meta_description')"
        :canonical="seo.canonical"
        :schema="seo.schema"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pb-16 pt-36 md:pt-44" tabindex="-1">
            <section class="mx-auto max-w-6xl overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-card via-card to-buttonPrimary/10 p-6 md:p-10">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-buttonPrimary">{{ t('public_discovery.cities.eyebrow') }}</p>
                <h1 class="mt-3 max-w-4xl font-heading text-4xl font-black leading-tight sm:text-5xl">{{ t('public_discovery.cities.title') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-secondary">{{ t('public_discovery.cities.subtitle') }}</p>
            </section>

            <section class="mx-auto mt-8 grid max-w-6xl gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="city in cities"
                    :key="`${city.country_slug}-${city.slug}`"
                    :href="city.detail_url"
                    class="surface-card group p-5 transition hover:-translate-y-0.5 hover:border-buttonPrimary/50"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ city.country || t('public_discovery.common.country_open') }}</p>
                            <h2 class="mt-1 text-2xl font-black text-primary group-hover:text-buttonPrimary">{{ city.name }}</h2>
                        </div>
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-buttonPrimary/10 text-buttonPrimary">
                            <i class="las la-map-marker-alt text-2xl" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl border border-border bg-bg p-3">
                            <p class="text-xs text-secondary">{{ t('public_discovery.common.clubs') }}</p>
                            <p class="mt-1 text-xl font-black">{{ city.clubs_count }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-bg p-3">
                            <p class="text-xs text-secondary">{{ t('public_discovery.common.events') }}</p>
                            <p class="mt-1 text-xl font-black">{{ city.events_count }}</p>
                        </div>
                    </div>
                    <p class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-buttonPrimary">
                        {{ t('public_discovery.common.view_details') }}
                        <i class="las la-arrow-right rtl:rotate-180" aria-hidden="true"></i>
                    </p>
                </Link>

                <div v-if="!cities.length" class="surface-card p-8 text-center text-secondary sm:col-span-2 xl:col-span-3">
                    {{ t('public_discovery.cities.empty') }}
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
