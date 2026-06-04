<script setup>
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

defineProps({
    locations: { type: Array, default: () => [] },
})

const { t } = useI18n()

const locationTypeLabel = (type) => ({
    pickup: t('Abholstation'),
    boutique: t('Boutique'),
    branch: t('Filiale'),
    warehouse: t('Lager'),
    partner: t('Partnerstandort'),
}[type] || t('Standort'))
</script>

<template>
    <section v-if="locations.length" class="mx-auto mt-4 max-w-7xl px-3 sm:px-4">
        <div class="rounded border border-border bg-card shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-buttonPrimary">{{ $t('Standorte') }}</p>
                    <h2 class="text-lg font-black text-primary">{{ $t('Filialen, Boutiquen und Abholstationen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ $t('Öffentliche Anbieterstandorte, die du besuchen oder für Abholung nutzen kannst.') }}</p>
                </div>
                <span class="rounded-full bg-muted px-3 py-1 text-xs font-bold text-secondary">
                    {{ $t('{count} Standorte', { count: locations.length }) }}
                </span>
            </div>

            <div class="flex gap-3 overflow-x-auto p-3 [scrollbar-width:thin] md:grid md:grid-cols-2 md:overflow-visible lg:grid-cols-3">
                <article
                    v-for="location in locations"
                    :key="`marketplace-location-${location.id}`"
                    class="min-w-[18rem] rounded border border-border bg-bg p-4 md:min-w-0"
                >
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-buttonPrimary/10 text-sm font-black text-buttonPrimary">
                            <img v-if="location.image_url || location.provider?.logo_url" :src="location.image_url || location.provider.logo_url" :alt="location.name" class="h-full w-full object-cover" />
                            <span v-else>{{ location.provider?.initials || 'AM' }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ locationTypeLabel(location.type) }}</span>
                                <span v-if="location.pickup_enabled" class="rounded-full bg-success/10 px-2 py-1 text-[11px] font-black text-success">{{ $t('Abholung') }}</span>
                                <span v-if="location.returns_enabled" class="rounded-full bg-warning/10 px-2 py-1 text-[11px] font-black text-warning">{{ $t('Rückgabe') }}</span>
                            </div>
                            <h3 class="mt-2 line-clamp-2 text-base font-black text-primary">{{ location.name }}</h3>
                            <p class="mt-1 text-sm font-semibold text-secondary">{{ location.address }}</p>
                        </div>
                    </div>

                    <div class="mt-3 space-y-2 text-xs text-secondary">
                        <p v-if="location.opening_hours" class="flex gap-2">
                            <i class="las la-clock mt-0.5 text-base text-buttonPrimary"></i>
                            <span>{{ location.opening_hours }}</span>
                        </p>
                        <p v-if="location.note" class="flex gap-2">
                            <i class="las la-info-circle mt-0.5 text-base text-buttonPrimary"></i>
                            <span>{{ location.note }}</span>
                        </p>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-primary">{{ location.provider?.name }}</p>
                            <p class="truncate text-xs text-secondary">{{ location.provider?.type }}</p>
                        </div>
                        <Link
                            v-if="location.provider?.url"
                            :href="location.provider.url"
                            class="shrink-0 rounded border border-border px-3 py-2 text-xs font-black text-primary transition hover:border-buttonPrimary hover:text-buttonPrimary"
                        >
                            {{ $t('Ansehen') }}
                        </Link>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

