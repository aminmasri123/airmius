<script setup>
import { useI18n } from 'vue-i18n'

const props = defineProps({
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
    benefitCards: { type: Object, required: true },
    switchTab: { type: Function, required: true },
    onTabKeydown: { type: Function, required: true },
})

const { t } = useI18n()

const accentByTab = {
    sportler: 'text-air-blue bg-air-blue/15',
    trainer: 'text-air-green bg-air-green/15',
    vereine: 'text-air-orange bg-air-orange/15',
    marketplace: 'text-air-blue bg-air-blue/15',
}

const gridByTab = {
    sportler: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5',
    trainer: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    vereine: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    marketplace: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
}

function cardsFor(tab) {
    return props.benefitCards[tab] || []
}
</script>

<template>
    <section id="vorteile" class="border-t border-white/5 px-4 py-16 sm:py-24">
        <div class="mx-auto max-w-6xl">
            <div class="mb-8 px-2 text-center sm:mb-10">
                <span class="text-xs font-semibold uppercase tracking-wider text-air-blue sm:text-sm">{{ t('guest.nav.benefits') }}</span>
                <h2 class="mt-2 font-heading text-2xl font-800 leading-tight sm:text-3xl lg:text-4xl">
                    {{ t('guest.welcome.benefits.title_before') }}
                    <span class="bg-gradient-to-r from-air-blue to-air-green bg-clip-text text-transparent">{{ t('guest.welcome.benefits.title_highlight') }}</span>
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-gray-400 sm:text-base">{{ t('guest.welcome.benefits.subtitle') }}</p>
            </div>

            <div class="mb-8 flex justify-center px-4 sm:mb-10">
                <div
                    class="inline-flex flex-wrap justify-center gap-1 rounded-full bg-white/5 p-1"
                    role="tablist"
                    :aria-label="t('guest.welcome.benefits.audience_label')"
                >
                    <button
                        v-for="tab in tabs"
                        :id="`tab-${tab.key}`"
                        :key="tab.key"
                        type="button"
                        :aria-controls="`tabpanel-${tab.key}`"
                        :aria-selected="activeTab === tab.key"
                        :tabindex="activeTab === tab.key ? 0 : -1"
                        :class="[
                            'flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-2.5 text-sm font-semibold transition-all sm:px-5',
                            activeTab === tab.key ? 'tab-active' : 'text-gray-400 hover:text-white',
                        ]"
                        @click="switchTab(tab.key)"
                        @keydown="onTabKeydown"
                    >
                        {{ t(tab.labelKey) }}
                    </button>
                </div>
            </div>

            <div
                :id="`tabpanel-${activeTab}`"
                role="tabpanel"
                :aria-labelledby="`tab-${activeTab}`"
                :class="['grid gap-3 sm:gap-4', gridByTab[activeTab] || 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3']"
            >
                <div
                    v-for="card in cardsFor(activeTab)"
                    :key="card[1]"
                    :class="card[3] || ''"
                    class="benefit-card grad-card rounded-xl p-4 sm:rounded-2xl sm:p-5"
                >
                    <div :class="['mb-2 flex h-8 w-8 items-center justify-center rounded-lg sm:mb-3 sm:h-10 sm:w-10', accentByTab[activeTab] || accentByTab.sportler]">
                        <i :class="[card[0], 'text-lg sm:text-xl']"></i>
                    </div>
                    <h4 class="mb-1 font-heading text-sm font-600 leading-tight">{{ t(card[1]) }}</h4>
                    <p class="text-xs leading-snug text-gray-400">{{ t(card[2]) }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
