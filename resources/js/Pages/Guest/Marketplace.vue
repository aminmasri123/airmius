<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    products: { type: Array, default: () => [] },
    featuredProducts: { type: Array, default: () => [] },
    outfitPlans: { type: Array, default: () => [] },
    sportCategories: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const form = ref({
    search: props.filters.search || '',
    category: props.filters.category || '',
})

const categoryLabels = {
    product: 'Produkt',
    course: 'Kurs',
    camp: 'Camp',
    service: 'Service',
    outfit_subscription: 'Outfit-Abo',
}

const heroProduct = computed(() => props.featuredProducts[0] || props.products[0] || null)
const dealProducts = computed(() => (props.featuredProducts.length ? props.featuredProducts : props.products).slice(0, 4))

const formatPrice = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: currency || 'EUR',
}).format((cents || 0) / 100)

const shortDescription = (text, length = 105) => {
    if (!text) return 'Sportangebot aus dem Airmius Marketplace.'
    if (text.length <= length) return text

    return `${text.slice(0, length).trim()}...`
}

const search = () => {
    router.get(route('guest.marketplace'), {
        search: form.value.search || undefined,
        category: form.value.category || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

const reset = () => {
    form.value.search = ''
    form.value.category = ''
    search()
}

const searchCategory = (category) => {
    form.value.search = category.query
    form.value.category = ''
    search()
}
</script>

<template>
    <SeoHead
        title="Airmius Sport Marketplace"
        description="Sportfokussierter Marketplace fuer Produkte, Kurse, Camps und Services. Gaeste koennen direkt ohne Konto bestellen."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="pb-24 pt-32 md:pb-12 md:pt-40">
            <section class="border-b border-border bg-card/70 px-4 py-4">
                <form class="mx-auto grid max-w-7xl gap-3 md:grid-cols-[13rem_1fr_9rem_7rem]" @submit.prevent="search">
                    <select v-model="form.category" class="h-12 rounded-lg border-border bg-inputBg text-primary">
                        <option v-for="category in categories" :key="category.value" :value="category.value">
                            {{ category.label }}
                        </option>
                    </select>
                    <input
                        v-model="form.search"
                        class="h-12 rounded-lg border-border bg-inputBg text-primary"
                        placeholder="Suche nach Laufschuhen, Trainingsplaenen, Camps, Trikots..."
                    />
                    <button class="h-12 rounded-lg bg-buttonPrimary px-4 font-semibold text-buttonTextPrimary">
                        Suchen
                    </button>
                    <button type="button" class="h-12 rounded-lg border border-border px-4 font-semibold text-primary hover:bg-muted" @click="reset">
                        Reset
                    </button>
                </form>
            </section>

            <section class="mx-auto grid max-w-7xl gap-4 px-4 py-5 lg:grid-cols-[16rem_1fr_18rem]">
                <aside class="surface-card p-3">
                    <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-secondary">Sportkategorien</p>
                    <button
                        v-for="category in sportCategories"
                        :key="category.label"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-primary transition hover:bg-muted"
                        @click="searchCategory(category)"
                    >
                        <i :class="[category.icon, 'text-xl text-air-blue']"></i>
                        <span>{{ category.label }}</span>
                    </button>
                </aside>

                <section class="overflow-hidden rounded-xl border border-border bg-card">
                    <div class="grid min-h-[24rem] gap-0 lg:grid-cols-[1fr_18rem]">
                        <div class="flex flex-col justify-between bg-gradient-to-br from-[#082f49] via-[#0f766e] to-[#111827] p-6 text-white md:p-8">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wide text-cyan-100">Sport Marketplace</p>
                                <h1 class="mt-3 max-w-xl font-heading text-4xl font-900 leading-tight md:text-5xl">
                                    Alles fuer Training, Team und Wettkampf.
                                </h1>
                                <p class="mt-4 max-w-xl text-sm leading-7 text-cyan-50">
                                    Sportprodukte, Kurse, Camps und Services. Gaeste koennen direkt bestellen, Mitglieder koennen spaeter selbst Angebote einstellen.
                                </p>
                            </div>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <Link :href="heroProduct?.show_url || route('guest.marketplace')" class="rounded-lg bg-white px-4 py-3 text-sm font-bold text-slate-950">
                                    Top-Angebot ansehen
                                </Link>
                                <Link :href="route('login')" class="rounded-lg border border-white/40 px-4 py-3 text-sm font-bold text-white">
                                    Anbieter werden
                                </Link>
                            </div>
                        </div>

                        <Link
                            v-if="heroProduct"
                            :href="heroProduct.show_url"
                            class="flex flex-col justify-between border-l border-white/10 bg-black/20 p-5 text-white transition hover:bg-black/30"
                        >
                            <div class="flex h-28 items-center justify-center rounded-xl bg-white/10">
                                <i :class="[heroProduct.visual_icon, 'text-6xl text-white']"></i>
                            </div>
                            <div>
                                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-cyan-100">{{ heroProduct.badge }}</p>
                                <h2 class="mt-1 text-xl font-bold">{{ heroProduct.title }}</h2>
                                <p class="mt-2 text-sm text-cyan-50">{{ shortDescription(heroProduct.description, 78) }}</p>
                                <p class="mt-4 text-2xl font-black">{{ formatPrice(heroProduct.price_cents, heroProduct.currency) }}</p>
                            </div>
                        </Link>
                    </div>
                </section>

                <aside class="grid gap-4">
                    <div class="surface-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Heute beliebt</p>
                        <div class="mt-3 space-y-3">
                            <Link
                                v-for="product in dealProducts.slice(0, 3)"
                                :key="product.id"
                                :href="product.show_url"
                                class="flex gap-3 rounded-lg p-2 transition hover:bg-muted"
                            >
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-inputBg">
                                    <i :class="[product.visual_icon, 'text-2xl text-air-blue']"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-primary">{{ product.title }}</p>
                                    <p class="text-xs text-secondary">{{ formatPrice(product.price_cents, product.currency) }}</p>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                        <p class="text-sm font-bold text-primary">Gastbestellung aktiv</p>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Kaufen funktioniert ohne Konto. Fuer Anbieter, Favoriten und Bestellhistorie lohnt sich ein Login.
                        </p>
                    </div>
                </aside>
            </section>

            <section class="mx-auto max-w-7xl px-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">Flash Deals</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">Sportangebote fuer den Beta-Test</h2>
                    </div>
                    <Link :href="route('login')" class="hidden rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted sm:inline-flex">
                        Angebot einstellen
                    </Link>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="product in dealProducts"
                        :key="product.id"
                        :href="product.show_url"
                        class="rounded-xl border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-air-blue/60"
                    >
                        <div class="flex h-24 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[product.visual_icon, 'text-5xl text-air-blue']"></i>
                        </div>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-air-blue">{{ product.badge }}</p>
                        <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-bold text-primary">{{ product.title }}</h3>
                        <div class="mt-2 flex items-end gap-2">
                            <span class="text-lg font-black text-primary">{{ formatPrice(product.price_cents, product.currency) }}</span>
                            <span v-if="product.old_price_cents" class="text-xs text-secondary line-through">{{ formatPrice(product.old_price_cents, product.currency) }}</span>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="mx-auto mt-10 max-w-7xl px-4">
                <div v-if="outfitPlans.length" class="mb-10">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">Outfit-Abos</p>
                            <h2 class="mt-1 text-2xl font-bold text-primary">Monatliche Sportkleidung passend zu deinem Stil</h2>
                        </div>
                        <Link :href="route('login')" class="hidden rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted sm:inline-flex">
                            Style-Profil starten
                        </Link>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="plan in outfitPlans"
                            :key="plan.id"
                            :href="route('login')"
                            class="rounded-xl border border-air-blue/30 bg-card p-4 transition hover:-translate-y-0.5 hover:border-air-blue"
                        >
                            <div class="flex h-24 items-center justify-center rounded-lg bg-air-blue/10">
                                <i class="las la-tshirt text-5xl text-air-blue"></i>
                            </div>
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-air-blue">{{ plan.badge }}</p>
                            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-bold text-primary">{{ plan.title }}</h3>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-secondary">{{ shortDescription(plan.description, 88) }}</p>
                            <div class="mt-3 flex items-end gap-2">
                                <span class="text-lg font-black text-primary">{{ formatPrice(plan.price_cents, plan.currency) }}</span>
                                <span v-if="plan.old_price_cents" class="text-xs text-secondary line-through">{{ formatPrice(plan.old_price_cents, plan.currency) }}</span>
                            </div>
                            <p class="mt-2 text-xs text-secondary">{{ plan.provider_name }}</p>
                        </Link>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">Alle Angebote</p>
                        <h2 class="mt-1 text-2xl font-bold text-primary">Sportfokussierter Marketplace</h2>
                    </div>
                    <p class="hidden text-sm text-secondary sm:block">{{ products.length }} Angebote</p>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    <article
                        v-for="product in products"
                        :key="product.id"
                        class="group overflow-hidden rounded-xl border border-border bg-card transition hover:-translate-y-0.5 hover:border-air-blue/60"
                    >
                        <Link :href="product.show_url" class="block">
                            <div class="relative flex aspect-square items-center justify-center bg-inputBg">
                                <i :class="[product.visual_icon, 'text-6xl text-air-blue']"></i>
                                <span class="absolute left-2 top-2 rounded-full bg-card/90 px-2 py-1 text-[11px] font-bold text-air-blue">
                                    {{ product.badge }}
                                </span>
                            </div>

                            <div class="p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                                    {{ categoryLabels[product.category] || product.category }}
                                </p>
                                <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-bold text-primary group-hover:text-air-blue">
                                    {{ product.title }}
                                </h3>
                                <p class="mt-1 hidden text-xs leading-5 text-secondary sm:line-clamp-2">
                                    {{ shortDescription(product.description, 82) }}
                                </p>

                                <div class="mt-3">
                                    <p class="text-lg font-black text-primary">{{ formatPrice(product.price_cents, product.currency) }}</p>
                                    <p v-if="product.old_price_cents" class="text-xs text-secondary line-through">{{ formatPrice(product.old_price_cents, product.currency) }}</p>
                                </div>

                                <div class="mt-3 flex items-center justify-between text-xs text-secondary">
                                    <span>{{ product.rating }} / 5</span>
                                    <span>{{ product.sold_count }} verkauft</span>
                                </div>

                                <p class="mt-2 truncate text-xs text-secondary">
                                    {{ product.provider_name || 'Airmius Marketplace' }}
                                </p>
                            </div>
                        </Link>
                    </article>

                    <div v-if="!products.length" class="surface-card col-span-2 p-8 text-center md:col-span-3 xl:col-span-5">
                        <p class="text-lg font-semibold text-primary">Noch keine passenden Marketplace-Angebote.</p>
                        <p class="mt-2 text-sm text-secondary">
                            Sobald Produkte, Kurse oder Services freigegeben sind, erscheinen sie hier fuer Gaeste und Mitglieder.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
