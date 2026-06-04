<script setup>
defineProps({
    form: { type: Object, required: true },
    mobileFilterOpen: { type: Boolean, default: false },
    localizedCategories: { type: Array, default: () => [] },
    localizedSegments: { type: Array, default: () => [] },
    localizedAvailabilityOptions: { type: Array, default: () => [] },
    localizedSortOptions: { type: Array, default: () => [] },
    localizedSportCategories: { type: Array, default: () => [] },
    pricingCountries: { type: Array, default: () => [] },
    quickTiles: { type: Array, default: () => [] },
    totalProducts: { type: Number, default: 0 },
    activeCategoryLabel: { type: String, default: '' },
    activeSegmentLabel: { type: String, default: '' },
    activeAvailabilityLabel: { type: String, default: '' },
    search: { type: Function, required: true },
    reset: { type: Function, required: true },
    searchCategory: { type: Function, required: true },
    selectQuickTile: { type: Function, required: true },
    selectSegment: { type: Function, required: true },
    setMobileFilterOpen: { type: Function, required: true },
})
</script>

<template>
    <section class="bg-card/70 px-3 py-3 shadow-sm backdrop-blur sm:px-4">
        <form class="mx-auto grid max-w-7xl grid-cols-[minmax(0,1fr)_auto_auto] gap-2 rounded-xl border border-border bg-bg/80 p-2 shadow-sm sm:p-3 lg:grid-cols-[minmax(0,25rem)_minmax(18rem,1fr)_auto] lg:items-center lg:gap-3" @submit.prevent="search">
            <div class="relative order-1 min-w-0 lg:order-2 lg:min-w-[22rem] xl:min-w-[30rem]">
                <i class="las la-search absolute left-4 top-1/2 -translate-y-1/2 text-2xl text-buttonPrimary"></i>
                <input
                    v-model="form.search"
                    class="h-12 w-full rounded-xl border-border bg-inputBg py-3 pl-12 pr-12 text-sm font-semibold text-primary outline-none transition placeholder:text-secondary/70 focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25"
                    :placeholder="$t('Was suchst du?')"
                />
                <button
                    v-if="form.search"
                    type="button"
                    class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-muted text-secondary transition hover:bg-buttonPrimary hover:text-buttonTextPrimary"
                    :aria-label="$t('Suche leeren')"
                    @click="form.search = ''"
                >
                    <i class="las la-times"></i>
                </button>
            </div>

            <button
                type="button"
                class="order-2 flex h-12 items-center justify-center rounded-xl border border-border bg-card px-3 text-sm font-black text-primary transition hover:border-buttonPrimary hover:text-buttonPrimary lg:hidden"
                :aria-expanded="mobileFilterOpen"
                aria-controls="marketplace-mobile-filter"
                @click="setMobileFilterOpen(!mobileFilterOpen)"
            >
                <i class="las la-sliders-h text-xl"></i>
                <span class="sr-only">{{ $t("Filter") }}</span>
            </button>

            <div class="hidden gap-3 lg:order-1 lg:grid lg:grid-cols-3">
                <label class="relative hidden">
                    <span class="sr-only">{{ $t("Kategorie") }}</span>
                    <select v-model="form.category" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                        <option v-for="category in localizedCategories" :key="category.value" :value="category.value">
                            {{ category.label }}
                        </option>
                    </select>
                </label>
                <label class="relative hidden">
                    <span class="sr-only">{{ $t("Bereich") }}</span>
                    <select v-model="form.segment" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                        <option v-for="segment in localizedSegments" :key="segment.value || 'all'" :value="segment.value">
                            {{ segment.label }}
                        </option>
                    </select>
                </label>
                <label class="relative block">
                    <span class="sr-only">{{ $t("Verfügbarkeit") }}</span>
                    <select v-model="form.availability" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                        <option v-for="option in localizedAvailabilityOptions" :key="option.value || 'all'" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label class="relative block">
                    <span class="sr-only">{{ $t("Sortierung") }}</span>
                    <select v-model="form.sort" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                        <option v-for="option in localizedSortOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label class="relative block">
                    <span class="sr-only">{{ $t("Land") }}</span>
                    <select v-model="form.country" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25" @change="search">
                        <option value="">{{ $t("Land automatisch") }}</option>
                        <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                            {{ country.label }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="order-3 grid grid-cols-[auto] gap-2 lg:order-3 lg:w-auto lg:shrink-0 lg:grid-cols-[1fr_auto]">
                <button class="flex h-12 w-12 items-center justify-center rounded-xl bg-buttonPrimary text-sm font-black text-buttonTextPrimary shadow-sm transition hover:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-buttonPrimary/30 lg:w-auto lg:px-6">
                    <i class="las la-search text-xl lg:hidden"></i>
                    <span class="hidden lg:inline">{{ $t("Suchen") }}</span>
                </button>
                <button type="button" class="hidden h-12 rounded border border-border bg-card px-4 text-sm font-bold text-primary transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary/20 sm:block" @click="reset">
                    {{ $t("Reset") }}
                </button>
            </div>

            <div
                v-if="mobileFilterOpen"
                id="marketplace-mobile-filter"
                class="order-4 col-span-3 grid max-h-[70vh] gap-3 overflow-y-auto rounded-xl border border-border bg-card p-3 shadow-2xl shadow-black/20 lg:hidden"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-black text-primary">{{ $t("Filter & Kategorien") }}</p>
                    <span class="rounded-full bg-muted px-2 py-1 text-[11px] font-bold text-secondary">{{ $t('{count} Treffer', { count: totalProducts }) }}</span>
                </div>

                <div class="grid gap-2">
                    <label class="relative block">
                        <span class="sr-only">{{ $t("Kategorie") }}</span>
                        <select v-model="form.category" class="h-11 w-full rounded-xl border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                            <option v-for="category in localizedCategories" :key="category.value" :value="category.value">
                                {{ category.label }}
                            </option>
                        </select>
                    </label>
                    <label class="relative block">
                        <span class="sr-only">{{ $t("Bereich") }}</span>
                        <select v-model="form.segment" class="h-11 w-full rounded-xl border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                            <option v-for="segment in localizedSegments" :key="segment.value || 'all'" :value="segment.value">
                                {{ segment.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <section>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-xs font-black uppercase text-secondary">{{ $t("Schnellwahl") }}</p>
                        <button type="button" class="text-xs font-bold text-buttonPrimary" @click="reset(); setMobileFilterOpen(false)">{{ $t("Reset") }}</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="tile in quickTiles"
                            :key="`mobile-filter-${tile.label}`"
                            type="button"
                            class="flex items-center gap-2 rounded-xl border border-border bg-inputBg p-2 text-left text-xs font-black text-primary"
                            @click="selectQuickTile(tile); setMobileFilterOpen(false)"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                                <i :class="[tile.icon, 'text-lg']"></i>
                            </span>
                            <span class="truncate">{{ tile.label }}</span>
                        </button>
                    </div>
                </section>

                <section>
                    <p class="mb-2 text-xs font-black uppercase text-secondary">{{ $t("Sportarten") }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="category in localizedSportCategories"
                            :key="`mobile-filter-sport-${category.label}`"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-inputBg px-3 py-2 text-xs font-black text-primary"
                            @click="searchCategory(category); setMobileFilterOpen(false)"
                        >
                            <i :class="[category.icon, 'text-base text-buttonPrimary']"></i>
                            {{ category.label }}
                        </button>
                    </div>
                </section>

                <section>
                    <p class="mb-2 text-xs font-black uppercase text-secondary">{{ $t("Produktbereiche") }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="segment in localizedSegments"
                            :key="`mobile-filter-segment-${segment.value || 'all'}`"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-black transition"
                            :class="form.segment === segment.value ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-primary'"
                            @click="selectSegment(segment); setMobileFilterOpen(false)"
                        >
                            <i :class="[segment.icon, 'text-base']"></i>
                            {{ segment.label }}
                        </button>
                    </div>
                </section>

                <div class="grid grid-cols-[1fr_auto] gap-2">
                    <button class="h-11 rounded-xl bg-buttonPrimary px-4 text-sm font-black text-buttonTextPrimary shadow-sm transition hover:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-buttonPrimary/30" @click="setMobileFilterOpen(false)">
                        {{ $t("Anwenden") }}
                    </button>
                    <button type="button" class="h-11 rounded-xl border border-border bg-card px-4 text-sm font-bold text-primary transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary/20" @click="reset(); setMobileFilterOpen(false)">
                        {{ $t("Reset") }}
                    </button>
                </div>
            </div>
        </form>

        <div class="mx-auto mt-2 flex max-w-7xl touch-pan-x gap-2 overflow-x-auto overscroll-x-contain pb-1 text-xs font-semibold text-secondary sm:mt-3 sm:flex-wrap">
            <span class="rounded bg-muted px-2 py-1">{{ $t('Kategorie:') }} {{ activeCategoryLabel }}</span>
            <span class="rounded bg-muted px-2 py-1">{{ $t('Bereich:') }} {{ activeSegmentLabel }}</span>
            <span class="hidden rounded bg-muted px-2 py-1 sm:inline">{{ $t('Status:') }} {{ activeAvailabilityLabel }}</span>
            <span class="shrink-0 rounded bg-buttonPrimary/15 px-2 py-1 text-buttonPrimary">{{ $t('{count} Treffer', { count: totalProducts }) }}</span>
        </div>
    </section>
</template>

