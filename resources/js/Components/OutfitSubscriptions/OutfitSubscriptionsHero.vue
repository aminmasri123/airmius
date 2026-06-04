<script setup>
defineProps({
    heroImageUrl: { type: String, required: true },
    activeSubscriptions: { type: Array, default: () => [] },
    nextDelivery: { type: String, default: null },
    hasStyleProfile: { type: Boolean, default: false },
    formatDate: { type: Function, required: true },
    scrollToPlans: { type: Function, required: true },
})
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
        <div class="grid min-h-[28rem] lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_27rem]">
            <div class="relative flex min-h-[24rem] items-end overflow-hidden bg-black">
                <img
                    :src="heroImageUrl"
                    alt="Airmius Outfit-Abo"
                    class="absolute inset-0 h-full w-full object-cover"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/10"></div>

                <div class="relative z-10 max-w-3xl p-5 sm:p-7 lg:p-8">
                    <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Sportkleidung monatlich</p>
                    <h1 class="mt-3 max-w-2xl text-3xl font-black leading-tight text-white sm:text-5xl">
                        Outfit-Abo für deinen Style
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-white/85 sm:text-base">
                        Wähle einen Plan, pflege dein Style-Profil und erhalte regelmäßig Sport-Outfits passend zu Sportart, Größe, Farben und Markenstil.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-white/90" @click="scrollToPlans">
                            Plan wählen
                        </button>
                        <a href="#style-profile" class="rounded-lg border border-white/35 bg-white/10 px-4 py-2 text-sm font-bold text-white backdrop-blur hover:bg-white/20">
                            Style-Profil pflegen
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid content-between gap-4 border-t border-border bg-card p-5 lg:border-l lg:border-t-0 lg:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">Abo-Zentrale</p>
                    <h2 class="mt-2 text-xl font-bold text-primary">Alles auf einen Blick</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Aktive Abos, nächste Lieferung und Style-Daten bleiben hier schnell erreichbar.
                    </p>
                </div>

                <div class="grid gap-3">
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Aktive Abos</p>
                        <p class="mt-2 text-3xl font-black text-primary">{{ activeSubscriptions.length }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Nächste Lieferung</p>
                        <p class="mt-2 text-lg font-bold text-primary">{{ formatDate(nextDelivery) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs uppercase text-secondary">Style-Profil</p>
                        <p class="mt-2 text-lg font-bold" :class="hasStyleProfile ? 'text-success' : 'text-warning'">
                            {{ hasStyleProfile ? 'Bereit' : 'Noch offen' }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-accent/30 bg-accent/10 p-4">
                    <p class="text-sm font-semibold text-primary">Tipp</p>
                    <p class="mt-1 text-sm text-secondary">
                        Je genauer Größen, Farben und No-Gos sind, desto besser passt die monatliche Box.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

