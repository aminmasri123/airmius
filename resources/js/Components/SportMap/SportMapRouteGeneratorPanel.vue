<script setup>
import SportMapRouteGeneratorBaseStep from '@/Components/SportMap/SportMapRouteGeneratorBaseStep.vue'
import SportMapRouteGeneratorProposalStep from '@/Components/SportMap/SportMapRouteGeneratorProposalStep.vue'
import SportMapRouteGeneratorStyleStep from '@/Components/SportMap/SportMapRouteGeneratorStyleStep.vue'

defineProps({
    access: {
        type: Object,
        default: () => ({ available: false }),
    },
    actualSummary: {
        type: Object,
        default: null,
    },
    catalogLabel: {
        type: Function,
        required: true,
    },
    cuePreview: {
        type: Array,
        default: () => [],
    },
    cueText: {
        type: Function,
        required: true,
    },
    destinationPoint: {
        type: Object,
        default: null,
    },
    difficultyOptions: {
        type: Array,
        default: () => [],
    },
    elevationOptions: {
        type: Array,
        default: () => [],
    },
    environmentOptions: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
    formatPercent: {
        type: Function,
        required: true,
    },
    isGenerating: {
        type: Boolean,
        default: false,
    },
    limitLabel: {
        type: String,
        default: '',
    },
    mapTarget: {
        type: String,
        default: 'start',
    },
    navigationCues: {
        type: Array,
        default: () => [],
    },
    points: {
        type: Array,
        default: () => [],
    },
    qualityBadgeClass: {
        type: Function,
        required: true,
    },
    routeTypeLabel: {
        type: String,
        default: '-',
    },
    routeTypes: {
        type: Array,
        default: () => [],
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
    startModes: {
        type: Array,
        default: () => [],
    },
    status: {
        type: String,
        default: '',
    },
    step: {
        type: Number,
        default: 1,
    },
    steps: {
        type: Array,
        default: () => [],
    },
    surfaceOptions: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
})

defineEmits([
    'apply',
    'clear-destination',
    'generate',
    'remove-point',
    'reset',
    'set-destination-from-map-center',
    'set-map-target',
    'set-route-type',
    'set-start-mode',
    'set-step',
    'use-current-location',
])
</script>

<template>
    <div class="space-y-5 p-4">
        <div class="grid gap-2 sm:grid-cols-3">
            <button
                v-for="item in steps"
                :key="item.step"
                type="button"
                class="rounded-xl border px-4 py-3 text-left transition"
                :class="step === item.step ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                @click="$emit('set-step', item.step)"
            >
                <span class="text-xs font-bold uppercase text-air-blue">{{ $t('sport_map.generator.step_label', { step: item.step }) }}</span>
                <p class="mt-1 text-sm font-bold">{{ item.labelKey ? $t(item.labelKey) : item.label }}</p>
            </button>
        </div>

        <p v-if="status && step !== 3" class="rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
            {{ status }}
        </p>
        <p class="rounded-lg border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-secondary">
            {{ limitLabel }}
        </p>

        <SportMapRouteGeneratorBaseStep
            v-if="step === 1"
            :catalog-label="catalogLabel"
            :destination-point="destinationPoint"
            :form="form"
            :map-target="mapTarget"
            :route-type-label="routeTypeLabel"
            :route-types="routeTypes"
            :sport-types="sportTypes"
            :start-modes="startModes"
            :summary="summary"
            @clear-destination="$emit('clear-destination')"
            @set-destination-from-map-center="$emit('set-destination-from-map-center')"
            @set-map-target="$emit('set-map-target', $event)"
            @set-route-type="$emit('set-route-type', $event)"
            @set-start-mode="$emit('set-start-mode', $event)"
            @use-current-location="$emit('use-current-location')"
        />

        <SportMapRouteGeneratorStyleStep
            v-else-if="step === 2"
            :difficulty-options="difficultyOptions"
            :elevation-options="elevationOptions"
            :environment-options="environmentOptions"
            :form="form"
            :surface-options="surfaceOptions"
        />

        <SportMapRouteGeneratorProposalStep
            v-else
            :access="access"
            :actual-summary="actualSummary"
            :cue-preview="cuePreview"
            :cue-text="cueText"
            :format-percent="formatPercent"
            :is-generating="isGenerating"
            :navigation-cues="navigationCues"
            :points="points"
            :quality-badge-class="qualityBadgeClass"
            :status="status"
            :summary="summary"
            :title="title"
            @apply="$emit('apply')"
            @generate="$emit('generate')"
            @remove-point="$emit('remove-point', $event)"
        />

        <div class="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
            <button
                type="button"
                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"
                :disabled="step === 1"
                @click="$emit('set-step', step - 1)"
            >
                {{ $t('sport_map.generator.back') }}
            </button>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="$emit('reset')">
                    {{ $t('sport_map.generator.reset') }}
                </button>
                <button v-if="step < 3" type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="$emit('set-step', step + 1)">
                    {{ $t('sport_map.generator.next') }}
                </button>
                <button v-else type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGenerating || !access.available" @click="$emit('generate')">
                    {{ isGenerating ? $t('sport_map.generator.calculating') : $t('sport_map.generator.regenerate') }}
                </button>
            </div>
        </div>
    </div>
</template>
