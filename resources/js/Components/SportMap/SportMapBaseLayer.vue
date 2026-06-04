<script setup>
defineProps({
    fallbackMapLabelStyle: {
        type: Function,
        required: true,
    },
    fallbackMapLabels: {
        type: Array,
        default: () => [],
    },
    mapHasRealTiles: {
        type: Boolean,
        default: false,
    },
    mapOverlayTiles: {
        type: Array,
        default: () => [],
    },
    mapTiles: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['tile-error', 'tile-load'])
</script>

<template>
    <div class="absolute inset-0 z-0" style="background-color: #efe6d1;"></div>
    <div class="pointer-events-none absolute inset-0 z-0 opacity-80"
        style="background-image: linear-gradient(90deg, rgba(120,113,108,.16) 1px, transparent 1px), linear-gradient(0deg, rgba(120,113,108,.14) 1px, transparent 1px); background-size: 56px 56px;">
    </div>
    <div class="pointer-events-none absolute -left-[8%] top-[12%] z-0 h-[34%] w-[52%] rotate-[-8deg] rounded-[45%] bg-emerald-200/65 blur-[1px]"></div>
    <div class="pointer-events-none absolute right-[5%] top-[6%] z-0 h-[30%] w-[32%] rotate-[16deg] rounded-[42%] bg-lime-200/70 blur-[1px]"></div>
    <div class="pointer-events-none absolute bottom-[5%] left-[16%] z-0 h-[26%] w-[42%] rotate-[10deg] rounded-[44%] bg-green-200/55 blur-[1px]"></div>
    <div class="pointer-events-none absolute bottom-[12%] right-[10%] z-0 h-[18%] w-[28%] rotate-[-14deg] rounded-[42%] bg-amber-100/80 blur-[1px]"></div>
    <svg class="pointer-events-none absolute inset-0 z-[1] h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
        <path d="M-5 70 C 18 64, 35 62, 52 55 S 83 39, 105 35" fill="none" stroke="rgba(255,255,255,.82)" stroke-width="3.8" />
        <path d="M-5 70 C 18 64, 35 62, 52 55 S 83 39, 105 35" fill="none" stroke="rgba(234,179,8,.72)" stroke-width="1.8" />
        <path d="M7 12 C 25 22, 34 38, 48 50 S 72 70, 95 86" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="2.6" />
        <path d="M7 12 C 25 22, 34 38, 48 50 S 72 70, 95 86" fill="none" stroke="rgba(120,113,108,.52)" stroke-width="1.1" stroke-dasharray="2 1.4" />
        <path d="M-3 34 C 20 36, 35 31, 55 33 S 80 43, 103 45" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="2.1" />
        <path d="M-3 34 C 20 36, 35 31, 55 33 S 80 43, 103 45" fill="none" stroke="rgba(120,113,108,.42)" stroke-width=".9" />
        <path d="M30 -5 C 34 17, 41 34, 39 52 S 36 77, 45 105" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="1.8" />
        <path d="M30 -5 C 34 17, 41 34, 39 52 S 36 77, 45 105" fill="none" stroke="rgba(120,113,108,.36)" stroke-width=".8" />
    </svg>
    <div class="pointer-events-none absolute inset-0 z-[2] bg-gradient-to-b from-white/20 via-transparent to-amber-950/5"></div>

    <img
        v-for="tile in mapTiles"
        :key="tile.key"
        :src="tile.url"
        :style="tile.style"
        class="pointer-events-none absolute z-[6] max-w-none select-none"
        alt=""
        aria-hidden="true"
        decoding="async"
        loading="eager"
        draggable="false"
        @dragstart.prevent
        @load="$emit('tile-load')"
        @error="$emit('tile-error')"
    >
    <img
        v-for="tile in mapOverlayTiles"
        :key="tile.key"
        :src="tile.url"
        :style="tile.style"
        class="pointer-events-none absolute z-[7] max-w-none select-none"
        alt=""
        aria-hidden="true"
        decoding="async"
        loading="eager"
        draggable="false"
        @dragstart.prevent
    >

    <template v-if="!mapHasRealTiles">
        <span
            v-for="label in fallbackMapLabels"
            :key="label.name"
            class="pointer-events-none absolute z-[8] -translate-x-1/2 -translate-y-1/2 rounded bg-white/80 px-2 py-0.5 text-[11px] font-bold text-stone-700 shadow-sm"
            :style="fallbackMapLabelStyle(label)"
        >
            {{ label.name }}
        </span>
    </template>
</template>
