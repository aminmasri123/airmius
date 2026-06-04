<script setup>
import { computed } from 'vue'

const props = defineProps({
    deleteButtonClass: {
        type: [String, Array, Object],
        default: 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-bold text-secondary hover:bg-muted',
    },
    deleteLabel: {
        type: String,
        default: '',
    },
    deleteOnlyWhenPaused: {
        type: Boolean,
        default: false,
    },
    deleteVisible: {
        type: Boolean,
        default: true,
    },
    hasPoints: {
        type: Boolean,
        default: false,
    },
    iconClass: {
        type: String,
        default: 'text-lg',
    },
    isProcessing: {
        type: Boolean,
        default: false,
    },
    isTracking: {
        type: Boolean,
        default: false,
    },
    pauseButtonClass: {
        type: [String, Array, Object],
        default: 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-bold text-amber-200',
    },
    saveButtonClass: {
        type: [String, Array, Object],
        default: 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-sm font-bold text-emerald-200 disabled:opacity-50',
    },
    secondaryGridClass: {
        type: [String, Array, Object],
        default: 'grid grid-cols-2 gap-3',
    },
    startButtonClass: {
        type: [String, Array, Object],
        default: 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary shadow-sm',
    },
    startLabel: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'inline',
        validator: (value) => ['inline', 'stacked'].includes(value),
    },
    wrapperClass: {
        type: [String, Array, Object],
        default: 'grid gap-2',
    },
    wrapperEmptyClass: {
        type: [String, Array, Object],
        default: 'grid-cols-1',
    },
    wrapperHasPointsClass: {
        type: [String, Array, Object],
        default: 'grid-cols-2',
    },
})

defineEmits(['delete', 'pause', 'save', 'start'])

const showDeleteButton = computed(() => props.deleteVisible && props.hasPoints && (!props.deleteOnlyWhenPaused || !props.isTracking))
const wrapperClasses = computed(() => [
    props.wrapperClass,
    props.variant === 'inline'
        ? (props.hasPoints ? props.wrapperHasPointsClass : props.wrapperEmptyClass)
        : null,
])
</script>

<template>
    <div :class="wrapperClasses">
        <button
            v-if="!isTracking"
            type="button"
            :class="startButtonClass"
            @click="$emit('start')"
        >
            <i :class="['las la-play', iconClass]"></i>
            {{ startLabel }}
        </button>
        <button
            v-else
            type="button"
            :class="pauseButtonClass"
            @click="$emit('pause')"
        >
            <i :class="['las la-pause', iconClass]"></i>
            {{ $t('sport_map.tracks.pause') }}
        </button>

        <template v-if="variant === 'stacked'">
            <div v-if="hasPoints" :class="secondaryGridClass">
                <button
                    type="button"
                    :class="saveButtonClass"
                    :disabled="isProcessing"
                    @click="$emit('save')"
                >
                    <i class="las la-save"></i>
                    {{ $t('sport_map.tracks.save_short') }}
                </button>
                <button
                    v-if="showDeleteButton"
                    type="button"
                    :class="deleteButtonClass"
                    @click="$emit('delete')"
                >
                    <i class="las la-trash"></i>
                    {{ deleteLabel || $t('sport_map.tracks.delete_draft_short') }}
                </button>
            </div>
        </template>

        <template v-else>
            <button
                v-if="hasPoints"
                type="button"
                :class="saveButtonClass"
                :disabled="isProcessing"
                @click="$emit('save')"
            >
                <i :class="['las la-save', iconClass]"></i>
                {{ $t('sport_map.tracks.save_short') }}
            </button>
            <button
                v-if="showDeleteButton"
                type="button"
                :class="deleteButtonClass"
                @click="$emit('delete')"
            >
                <i class="las la-trash"></i>
                {{ deleteLabel || $t('sport_map.tracks.delete_draft') }}
            </button>
        </template>
    </div>
</template>
