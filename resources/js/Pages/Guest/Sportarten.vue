<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    sports: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const form = ref({
    search: props.filters.search || '',
    category: props.filters.category || '',
})

const { t } = useI18n()

const applyFilters = () => {
    router.get(route('guest.sports'), {
        search: form.value.search || undefined,
        category: form.value.category || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <SeoHead
        :title="t('guest.sports.meta_title')"
        :description="t('guest.sports.meta_description')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ t('guest.sports.eyebrow') }}</p>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <h1 class="max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ t('guest.sports.title') }}
                    </h1>
                    <Link
                        :href="route('guest.vereine')"
                        class="inline-flex w-fit items-center justify-center rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        {{ t('guest.sports.find_clubs') }}
                    </Link>
                </div>

                <form class="mt-8 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFilters">
                    <input v-model="form.search" class="rounded-lg border-border bg-inputBg text-primary" :placeholder="t('guest.sports.search_placeholder')" :aria-label="t('guest.sports.search_label')" />
                    <select v-model="form.category" class="rounded-lg border-border bg-inputBg text-primary" :aria-label="t('guest.sports.category_label')">
                        <option value="">{{ t('guest.sports.all_categories') }}</option>
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>
                    <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary">{{ t('guest.sports.filter') }}</button>
                </form>
            </section>

            <section class="mx-auto mt-10 grid max-w-6xl gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="sport in sports" :key="sport.id" class="surface-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ sport.category || t('guest.sports.sport') }}</p>
                            <h2 class="mt-2 text-xl font-bold text-primary">{{ sport.name }}</h2>
                        </div>
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-buttonPrimary/10 text-buttonPrimary">
                            <i class="las la-running text-2xl"></i>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs text-secondary">{{ t('guest.sports.clubs') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ sport.clubs_count }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-bg p-3">
                            <p class="text-xs text-secondary">{{ t('guest.sports.teams') }}</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ sport.teams_count }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <Link :href="route('guest.vereine', { sport_type: sport.slug })" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ t('guest.sports.show_clubs') }}
                        </Link>
                        <Link :href="route('guest.events', { search: sport.name })" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            {{ t('guest.sports.events') }}
                        </Link>
                    </div>
                </article>

                <div v-if="!sports.length" class="surface-card p-8 text-center text-sm text-secondary sm:col-span-2 xl:col-span-3">
                    {{ t('guest.sports.empty') }}
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
