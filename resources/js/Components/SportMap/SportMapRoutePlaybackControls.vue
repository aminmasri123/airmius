<script setup>
defineProps({
    canStart: {
        type: Boolean,
        default: false,
    },
    elapsedSeconds: {
        type: Number,
        default: 0,
    },
    formatDistance: {
        type: Function,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    progressMeters: {
        type: Number,
        default: 0,
    },
    progressPercent: {
        type: Number,
        default: 0,
    },
    remainingSeconds: {
        type: Number,
        default: 0,
    },
    speed: {
        type: Number,
        default: 1,
    },
    speedOptions: {
        type: Array,
        default: () => [],
    },
    state: {
        type: String,
        default: 'idle',
    },
    status: {
        type: String,
        default: '',
    },
    totalDistance: {
        type: Number,
        default: 0,
    },
})

const emit = defineEmits(['reset', 'toggle', 'update:speed'])
</script>

<template>
    <div
        v-if="canStart"
        class="pointer-events-auto absolute bottom-4 left-4 right-4 z-[70] rounded-xl border border-border bg-card/95 p-3 shadow-xl backdrop-blur"
        @click.stop
        @pointerdown.stop
        @pointermove.stop
        @pointerup.stop
        @wheel.stop
    >
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-primary">{{ $t('sport_map.playback.title') }}</p>
                    <p class="shrink-0 text-xs font-semibold text-secondary">
                        {{ formatDistance(progressMeters) }} / {{ formatDistance(totalDistance) }}
                    </p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                    <div class="h-full rounded-full bg-orange-400 transition-[width]" :style="{ width: `${progressPercent}%` }"></div>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ $t('sport_map.playback.elapsed_remaining', {
                        elapsed: formatDuration(elapsedSeconds),
                        remaining: formatDuration(remainingSeconds),
                    }) }}
                    <span v-if="status"> · {{ status }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="emit('toggle')"
                >
                    <i :class="state === 'playing' ? 'las la-pause' : 'las la-play'"></i>
                    {{ state === 'playing' ? $t('sport_map.playback.pause') : state === 'paused' ? $t('sport_map.playback.continue') : $t('sport_map.playback.start') }}
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('reset')"
                >
                    <i class="las la-redo-alt"></i>
                    {{ $t('sport_map.playback.reset') }}
                </button>
                <div class="flex overflow-hidden rounded-lg border border-border">
                    <button
                        v-for="option in speedOptions"
                        :key="option"
                        type="button"
                        class="min-h-10 px-3 text-xs font-bold"
                        :class="speed === option ? 'bg-orange-400 text-slate-950' : 'bg-inputBg text-secondary hover:text-primary'"
                        @click="emit('update:speed', option)"
                    >
                        {{ option }}x
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

