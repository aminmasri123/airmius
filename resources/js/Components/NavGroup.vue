<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    label: String,
    icon: String,
    initialOpen: {
        type: Boolean,
        default: false,
    },
})

const open = ref(props.initialOpen)

watch(() => props.initialOpen, (value) => {
    if (value) open.value = true
})
</script>

<template>
    <div>
        <button
            type="button"
            :aria-expanded="open"
            @click="open = !open"
            class="flex min-h-11 w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-primary transition hover:bg-muted">

            <div class="flex items-center gap-2">
                <i :class="icon"></i>
                <span>{{ $t(label) }}</span>
            </div>

            <i :class="open ? 'las la-angle-down' : 'las la-angle-right'"></i>
        </button>

        <div v-if="open" class="mt-1 space-y-1 ps-5">
            <slot />
        </div>
    </div>
</template>
