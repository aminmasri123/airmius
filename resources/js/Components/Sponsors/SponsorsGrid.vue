<script setup>
const props = defineProps({
    sponsors: {
        type: Array,
        required: true,
    },
    activeScope: {
        type: String,
        required: true,
    },
    isDark: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['create', 'delete', 'edit'])

const sponsorScope = (sponsor) => sponsor.scope || (sponsor.club_id ? 'club' : 'platform')

const scopeLabel = (sponsor) => ({
    platform: 'Airmius Plattform',
    outfit_subscription: 'Outfit-Abo',
    club: sponsor.club?.name || 'Verein',
}[sponsorScope(sponsor)] || 'Airmius Plattform')

const scopeBadgeClass = (sponsor) => ({
    platform: 'bg-air-blue/10 text-air-blue',
    outfit_subscription: 'bg-accent/10 text-accent',
    club: 'bg-success/10 text-success',
}[sponsorScope(sponsor)] || 'bg-air-blue/10 text-air-blue')

const sponsorLogoUrl = (sponsor) => props.isDark
    ? (sponsor.logo_dark_url || sponsor.logo_light_url || sponsor.logo_url || sponsor.logo)
    : (sponsor.logo_light_url || sponsor.logo_dark_url || sponsor.logo_url || sponsor.logo)

const formatAmount = (amount) => {
    if (amount === null || amount === undefined || amount === '') return '-'

    return `${Number(amount).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} EUR`
}
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-3">
        <article
            v-for="sponsor in sponsors"
            :key="sponsor.id"
            class="flex min-h-56 flex-col justify-between rounded-lg border border-border bg-card p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-border bg-inputBg p-2">
                        <img
                            v-if="sponsorLogoUrl(sponsor)"
                            :src="sponsorLogoUrl(sponsor)"
                            :alt="sponsor.name"
                            class="max-h-full max-w-full object-contain"
                        >
                        <span v-else class="text-sm font-bold text-primary">{{ sponsor.name?.slice(0, 2)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-primary">{{ sponsor.name }}</h2>
                        <p class="truncate text-sm text-secondary">{{ sponsor.contact_name || sponsor.email || 'Kein Kontakt hinterlegt' }}</p>
                    </div>
                </div>

                <span :class="['shrink-0 rounded-full px-2 py-1 text-xs font-semibold', scopeBadgeClass(sponsor)]">
                    {{ scopeLabel(sponsor) }}
                </span>
            </div>

            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Budget</p>
                    <p class="mt-1 font-semibold text-primary">{{ formatAmount(sponsor.amount) }}</p>
                </div>
                <div class="rounded-lg bg-inputBg p-3">
                    <p class="text-xs uppercase text-secondary">Laufzeit</p>
                    <p class="mt-1 font-semibold text-primary">{{ sponsor.starts_at || '-' }} bis {{ sponsor.ends_at || '-' }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <a
                    v-if="sponsor.website"
                    :href="sponsor.website"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-secondary/10"
                >
                    <i class="las la-external-link-alt"></i>
                    Website
                </a>
                <button class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-secondary/10" @click="$emit('edit', sponsor)">
                    Bearbeiten
                </button>
                <button class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error hover:bg-error/10" @click="$emit('delete', sponsor)">
                    Löschen
                </button>
            </div>
        </article>

        <div v-if="sponsors.length === 0" class="rounded-lg border border-dashed border-border bg-card p-8 text-center xl:col-span-3">
            <p class="text-lg font-semibold text-primary">Keine Sponsoren in diesem Bereich</p>
            <p class="mt-2 text-sm text-secondary">Lege den ersten Sponsor direkt im passenden Tab an.</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary"
                @click="$emit('create', activeScope)"
            >
                Sponsor anlegen
            </button>
        </div>
    </section>
</template>
