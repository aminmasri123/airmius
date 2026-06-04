<script setup>
defineProps({
    access: {
        type: Object,
        default: () => ({ available: false }),
    },
    actualSummary: {
        type: Object,
        default: null,
    },
    cuePreview: {
        type: Array,
        default: () => [],
    },
    cueText: {
        type: Function,
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
    status: {
        type: String,
        default: '',
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

const emit = defineEmits(['apply', 'generate', 'remove-point'])
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,0.85fr),minmax(0,1.15fr)]">
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.generator.proposal_title') }}</h3>
                <p class="mt-1 text-sm leading-6 text-secondary">{{ $t('sport_map.generator.proposal_description') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-inputBg p-3">
                    <p class="text-xs font-semibold text-secondary">{{ summary.distanceLabel }}</p>
                    <p class="mt-1 font-bold text-primary">{{ summary.distance }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg p-3">
                    <p class="text-xs font-semibold text-secondary">{{ summary.durationLabel }}</p>
                    <p class="mt-1 font-bold text-primary">{{ summary.duration }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.surface') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ summary.surface }}</p>
                </div>
                <div class="rounded-xl border border-border bg-inputBg p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.pace') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ summary.pace }}</p>
                </div>
            </div>

            <div v-if="actualSummary" class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.calculated') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ actualSummary.distance }}</p>
                </div>
                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.time') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ actualSummary.duration }}</p>
                </div>
                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.routing') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ actualSummary.status }}</p>
                </div>
                <div class="rounded-xl border p-3" :class="qualityBadgeClass(actualSummary.qualityScore)">
                    <p class="text-xs font-semibold opacity-80">{{ $t('sport_map.generator.quality') }}</p>
                    <p class="mt-1 font-bold">{{ actualSummary.qualityScore ?? '-' }}%</p>
                </div>
                <div class="rounded-xl border border-air-blue/40 bg-air-blue/10 p-3">
                    <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.waypoints') }}</p>
                    <p class="mt-1 font-bold text-primary">{{ actualSummary.geometryPoints }}</p>
                </div>
            </div>

            <div v-if="actualSummary" class="rounded-xl border border-border bg-inputBg p-3 text-sm">
                <div class="grid gap-2 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.target_delta') }}</p>
                        <p class="mt-1 font-bold text-primary">{{ actualSummary.targetDelta }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.backtrack') }}</p>
                        <p class="mt-1 font-bold text-primary">{{ formatPercent(actualSummary.backtrackPercent) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.shape') }}</p>
                        <p class="mt-1 font-bold" :class="actualSummary.shapeAcceptable ? 'text-emerald-400' : 'text-amber-400'">
                            {{ actualSummary.shapeAcceptable ? $t('sport_map.generator.shape_ok') : $t('sport_map.generator.shape_review') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGenerating || !access.available" @click="emit('generate')">
                    <i class="las la-magic"></i>
                    {{ isGenerating ? $t('sport_map.generator.calculating') : $t('sport_map.generator.generate_proposal') }}
                </button>
                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60" :disabled="points.length < 2 || isGenerating" @click="emit('apply')">
                    <i class="las la-route"></i>
                    {{ $t('sport_map.generator.apply_to_planner') }}
                </button>
            </div>

            <p v-if="status" class="rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                {{ status }}
            </p>
        </div>

        <div class="rounded-xl border border-border bg-inputBg p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-primary">{{ title }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ summary.environment }} - {{ summary.elevation }} - {{ summary.difficulty }}</p>
                </div>
                <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-primary">{{ $t('sport_map.generator.ready') }}</span>
            </div>

            <div class="mt-4 space-y-2">
                <div
                    v-for="(point, index) in points"
                    :key="`${point.latitude}-${point.longitude}-${index}`"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-sm"
                >
                    <div>
                        <p class="font-semibold text-primary">{{ point.name }}</p>
                        <p class="text-xs text-secondary">{{ Number(point.latitude).toFixed(5) }}, {{ Number(point.longitude).toFixed(5) }}</p>
                    </div>
                    <button v-if="point.removable" type="button" class="text-red-400 hover:text-red-300" :aria-label="$t('sport_map.generator.remove_point_label')" @click="emit('remove-point', index)">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <p v-if="!points.length" class="rounded-lg border border-dashed border-border px-3 py-6 text-center text-sm text-secondary">
                    {{ $t('sport_map.generator.empty_proposal') }}
                </p>
            </div>

            <div v-if="cuePreview.length" class="mt-5 rounded-xl border border-border bg-card p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-primary">{{ $t('sport_map.generator.cue_title') }}</p>
                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">{{ navigationCues.length }}</span>
                </div>
                <ol class="mt-3 space-y-2">
                    <li
                        v-for="(cue, index) in cuePreview"
                        :key="`${cue.type || cue.maneuver_type}-${index}`"
                        class="flex gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-xs text-secondary"
                    >
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-air-blue/15 text-[10px] font-bold text-air-blue">{{ index + 1 }}</span>
                        <span>{{ cueText(cue, index) }}</span>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>
