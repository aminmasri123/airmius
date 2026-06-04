<script setup>
defineProps({
    aiTrainingPlanAvailable: { type: Boolean, default: false },
})

const emit = defineEmits(['open-ai', 'open-log', 'open-plan', 'open-week'])
</script>

<template>
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 px-4 py-3 shadow-2xl shadow-black/30 backdrop-blur sm:hidden">
        <div class="mx-auto grid max-w-md grid-cols-[1fr_1fr_1fr_auto] gap-2">
            <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-3 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-plan')">
                <i class="las la-plus-circle text-lg"></i>
                Plan
            </button>
            <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-air-blue/50 bg-air-blue/10 px-3 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="emit('open-ai')">
                <i class="las la-magic text-lg"></i>
                KI
            </button>
            <button type="button" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-semibold text-primary" @click="emit('open-log')">
                <i class="las la-pen-alt text-lg"></i>
                Log
            </button>
            <button type="button" class="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-card text-primary" aria-label="Zur Woche" @click="emit('open-week')">
                <i class="las la-calendar-week text-xl"></i>
            </button>
        </div>
    </div>
</template>
