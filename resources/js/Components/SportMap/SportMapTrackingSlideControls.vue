<script setup>
defineProps({
    activeSlide: {
        type: Number,
        default: 0,
    },
    activeDotClass: {
        type: String,
        default: 'w-8 bg-air-blue',
    },
    buttonClass: {
        type: [String, Array, Object],
        default: 'inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-primary disabled:opacity-40',
    },
    count: {
        type: Number,
        default: 3,
    },
    dotClass: {
        type: String,
        default: 'h-2.5 rounded-full transition-all',
    },
    inactiveDotClass: {
        type: String,
        default: 'w-2.5 bg-secondary/40',
    },
    wrapperClass: {
        type: [String, Array, Object],
        default: 'mt-4 flex items-center justify-between gap-3',
    },
})

defineEmits(['next', 'previous', 'select'])
</script>

<template>
    <div :class="wrapperClass">
        <button
            type="button"
            :aria-label="$t('sport_map.tracks.previous_slide')"
            :class="buttonClass"
            @click="$emit('previous')"
        >
            <i class="las la-arrow-left"></i>
        </button>
        <div class="flex items-center gap-2">
            <button
                v-for="index in count"
                :key="index"
                type="button"
                :aria-label="$t('sport_map.tracks.go_to_slide', { number: index })"
                :class="[dotClass, activeSlide === index - 1 ? activeDotClass : inactiveDotClass]"
                @click="$emit('select', index - 1)"
            ></button>
        </div>
        <button
            type="button"
            :aria-label="$t('sport_map.tracks.next_slide')"
            :class="buttonClass"
            @click="$emit('next')"
        >
            <i class="las la-arrow-right"></i>
        </button>
    </div>
</template>
