<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    tAuto: { type: Function, required: true },
    formatWater: { type: Function, required: true },
    drinkTips: { type: Array, default: () => [] },
    waterConsumedMl: { type: Number, required: true },
    waterTargetMl: { type: Number, required: true },
    waterLeftMl: { type: Number, required: true },
    waterProgress: { type: Number, required: true },
    setShowDrinkTips: { type: Function, required: true },
})
</script>

<template>
    <div class="fixed inset-0 z-[90] flex items-end justify-center bg-black/65 p-0 sm:items-center sm:p-4" @click.self="setShowDrinkTips(false)">
        <section class="flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl border border-border bg-card shadow-2xl sm:rounded-2xl">
            <header class="flex items-start justify-between gap-3 border-b border-border p-4 sm:p-5">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('Trinken') }}</p>
                    <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">{{ tAuto('Tipps rund ums Trinken') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-secondary">
                        {{ tAuto('Einfache Orientierung für Alltag, Training und Regeneration.') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-primary hover:bg-muted"
                    :aria-label="tAuto('Schließen')"
                    @click="setShowDrinkTips(false)"
                >
                    <i class="las la-times text-xl"></i>
                </button>
            </header>

            <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                <div class="grid gap-3">
                    <article v-for="tip in drinkTips" :key="tip.title" class="rounded-2xl border border-border bg-inputBg p-4">
                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-400/15 text-cyan-200">
                                <i :class="[tip.icon, 'text-xl']"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-primary">{{ tAuto(tip.title) }}</h3>
                                <p class="mt-1 text-sm leading-6 text-secondary">{{ tAuto(tip.body) }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                    <p class="text-sm font-bold text-primary">{{ tAuto('Dein aktueller Stand') }}</p>
                    <p class="mt-1 text-sm leading-6 text-secondary">
                        {{ tAuto(`Heute: ${formatWater(waterConsumedMl)} von ${formatWater(waterTargetMl)}. Noch ${formatWater(waterLeftMl)} offen.`) }}
                    </p>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-card">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
                    </div>
                </div>
            </div>

            <footer class="grid gap-2 border-t border-border p-4 sm:grid-cols-[1fr_auto] sm:p-5">
                <Link
                    :href="route('guest.blog.index', { search: 'Trinken' })"
                    class="rounded-xl border border-border px-4 py-3 text-center text-sm font-bold text-primary hover:border-cyan-300 hover:bg-cyan-400/10"
                    @click="setShowDrinkTips(false)"
                >
                    {{ tAuto('Mehr im Blog lesen') }}
                </Link>
                <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary" @click="setShowDrinkTips(false)">
                    {{ tAuto('Verstanden') }}
                </button>
            </footer>
        </section>
    </div>
</template>

