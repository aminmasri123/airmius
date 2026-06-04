<script setup>
defineEmits([
    'center',
    'remove-last',
    'reset',
    'show-location',
    'zoom-in',
    'zoom-out',
])
</script>

<template>
    <div
        class="pointer-events-auto absolute left-3 top-3 z-[80] flex flex-col overflow-hidden rounded-xl border border-white/70 bg-white/95 shadow-2xl ring-1 ring-slate-950/20 backdrop-blur"
        @click.stop
        @pointerdown.stop
        @pointermove.stop
        @pointerup.stop
        @wheel.stop
    >
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center text-lg font-black text-slate-950 transition hover:bg-air-blue hover:text-white"
            :aria-label="$t('sport_map.map_status.zoom_in')"
            @click.stop.prevent="$emit('zoom-in')"
        >
            +
        </button>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-lg font-black text-slate-950 transition hover:bg-air-blue hover:text-white"
            :aria-label="$t('sport_map.map_status.zoom_out')"
            @click.stop.prevent="$emit('zoom-out')"
        >
            -
        </button>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-slate-800 transition hover:bg-slate-900 hover:text-white"
            :aria-label="$t('sport_map.map_status.center_map')"
            @click.stop.prevent="$emit('center')"
        >
            <i class="las la-crosshairs"></i>
        </button>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-blue-600 transition hover:bg-blue-600 hover:text-white"
            :aria-label="$t('sport_map.map_status.show_location')"
            :title="$t('sport_map.map_status.my_location')"
            @click.stop.prevent="$emit('show-location')"
        >
            <i class="las la-location-arrow"></i>
        </button>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-slate-700 transition hover:bg-slate-900 hover:text-white"
            :aria-label="$t('sport_map.map_status.remove_last_point')"
            @click.stop.prevent="$emit('remove-last')"
        >
            <i class="las la-undo-alt"></i>
        </button>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center border-t border-slate-300 text-base text-red-600 transition hover:bg-red-600 hover:text-white"
            :aria-label="$t('sport_map.map_status.reset_points')"
            @click.stop.prevent="$emit('reset')"
        >
            <i class="las la-trash"></i>
        </button>
    </div>
</template>
