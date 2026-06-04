<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    heroCopy: { type: Object, required: true },
    heroPrimaryCta: { type: String, required: true },
    heroPrimaryCtaLabel: { type: String, required: true },
    heroTrustItems: { type: Array, default: () => [] },
    mockupCards: { type: Array, default: () => [] },
    mockupStats: { type: Array, default: () => [] },
    onPrimaryCtaClick: { type: Function, required: true },
    onSecondaryCtaClick: { type: Function, required: true },
})

const { t } = useI18n()
</script>

<template>
    <section id="hero" class="flex min-h-[calc(100dvh-5rem)] items-center px-4 pb-14 pt-16 sm:min-h-[calc(100dvh-7rem)] sm:pb-20 sm:pt-36">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-12 lg:flex-row lg:gap-32">
            <div class="flex-1 text-center lg:text-left">
                <div
                    class="anim-fade mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-medium text-air-green"
                >
                    <span class="pulse-dot bg-air-green"></span>
                    {{ heroCopy.badge }}
                </div>
                <h1
                    id="hero-title"
                    class="anim-fade-d1 font-heading text-4xl font-900 leading-tight tracking-tight sm:text-5xl lg:text-6xl"
                >
                    {{ heroCopy.title }} <br>
                    <span class="bg-gradient-to-r from-air-blue via-air-green to-air-orange bg-clip-text text-transparent">{{ heroCopy.highlight }}</span>
                </h1>
                <p
                    id="hero-subtitle"
                    class="anim-fade-d2 mx-auto mt-5 max-w-xl text-lg leading-relaxed text-gray-400 sm:text-xl lg:mx-0"
                >
                    {{ heroCopy.subtitle }}
                </p>
                <div class="anim-fade-d3 mt-8 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                    <a
                        :href="heroPrimaryCta"
                        :aria-label="heroPrimaryCtaLabel"
                        class="glow-blue rounded-full bg-air-blue px-8 py-3.5 text-center font-bold text-white transition hover:bg-blue-600"
                        @click="onPrimaryCtaClick"
                    >
                        {{ heroPrimaryCtaLabel }}
                    </a>
                    <button
                        type="button"
                        class="rounded-full border border-white/15 px-8 py-3.5 text-center font-semibold text-white transition hover:border-white/30"
                        @click="onSecondaryCtaClick"
                    >
                        {{ heroCopy.secondaryCta }}
                    </button>
                </div>
                <div class="anim-fade-d4 mt-8 flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500 lg:justify-start">
                    <span v-for="item in heroTrustItems" :key="item.key" class="flex items-center gap-1.5">
                        <i class="las la-check-circle text-air-green" aria-hidden="true"></i>{{ t(item.titleKey) }}
                    </span>
                </div>
                <div class="anim-fade-d4 mx-auto mt-6 hidden max-w-2xl gap-3 sm:grid sm:grid-cols-3 lg:mx-0">
                    <div
                        v-for="item in heroTrustItems"
                        :key="item.key"
                        class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-left"
                    >
                        <i :class="[item.icon, 'text-air-green text-xl mb-2']" aria-hidden="true"></i>
                        <div class="font-heading text-sm font-700 text-white">{{ t(item.titleKey) }}</div>
                        <p class="mt-1 text-xs leading-snug text-gray-500">{{ t(item.textKey) }}</p>
                    </div>
                </div>
            </div>

            <div class="hidden w-full max-w-lg flex-1 lg:block">
                <div class="mockup-screen float-loop">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                        <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                        <span class="h-3 w-3 rounded-full bg-green-500/80"></span>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(card, index) in mockupCards"
                            :key="card.key"
                            :class="[`card-item item-${index + 1}`, card.bgClass, card.borderClass]"
                            class="flex items-center gap-3 rounded-xl border p-3"
                        >
                            <div :class="card.iconBgClass" class="flex h-10 w-10 items-center justify-center rounded-lg">
                                <i :class="[card.icon, card.iconTextClass]"></i>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">{{ t(card.titleKey) }}</div>
                                <div class="text-sm font-medium">{{ t(card.textKey) }}</div>
                            </div>
                        </div>

                        <div class="card-item item-4 mt-2 flex gap-2">
                            <div v-for="(stat, index) in mockupStats" :key="stat.key" class="flex-1 rounded-lg bg-white/5 p-2 text-center">
                                <div :class="['font-heading text-2xl font-bold', index === 0 ? 'text-air-blue' : index === 1 ? 'text-air-green' : 'text-air-orange']">{{ stat.value }}</div>
                                <div class="text-[10px] text-gray-500">{{ t(stat.labelKey) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes floatLoop {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-12px);
    }
}

.float-loop {
    animation: floatLoop 6s ease-in-out infinite;
}

@keyframes listLoop {
    0%,
    60% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    65% {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }

    70% {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.card-item {
    animation: listLoop 10s ease-in-out infinite;
}

.item-1 {
    animation-delay: 0s;
}

.item-2 {
    animation-delay: 0.15s;
}

.item-3 {
    animation-delay: 0.3s;
}

.item-4 {
    animation-delay: 0.45s;
}

@media (prefers-reduced-motion: reduce) {
    .float-loop,
    .card-item {
        animation: none;
    }
}
</style>
