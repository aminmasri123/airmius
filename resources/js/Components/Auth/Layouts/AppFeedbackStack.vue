<script setup>
defineProps({
    messages: { type: Array, default: () => [] },
})

const emit = defineEmits(['remove'])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="messages.length"
            class="pointer-events-none fixed inset-x-0 bottom-4 z-[90] flex flex-col gap-2 px-3 sm:bottom-auto sm:left-auto sm:right-4 sm:top-4 sm:w-[min(24rem,calc(100vw-2rem))] sm:px-0"
        >
            <TransitionGroup name="airmius-feedback" tag="div" class="space-y-2">
                <article
                    v-for="feedback in messages"
                    :key="feedback.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-card/95 p-3 shadow-2xl backdrop-blur"
                    :class="{
                        'border-success/40': feedback.type === 'success',
                        'border-danger/40': feedback.type === 'error',
                        'border-air-blue/40': feedback.type === 'info',
                    }"
                >
                    <span
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="{
                            'bg-success/15 text-success': feedback.type === 'success',
                            'bg-danger/15 text-danger': feedback.type === 'error',
                            'bg-air-blue/15 text-air-blue': feedback.type === 'info',
                        }"
                    >
                        <i
                            :class="[
                                feedback.type === 'success' ? 'las la-check' : (feedback.type === 'error' ? 'las la-exclamation-circle' : 'las la-info-circle'),
                                'text-xl'
                            ]"
                        ></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-primary">
                            {{ feedback.type === 'success' ? 'Gespeichert' : (feedback.type === 'error' ? 'Hinweis' : 'Info') }}
                        </p>
                        <p class="mt-0.5 text-sm leading-5 text-secondary">{{ feedback.message }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-secondary hover:bg-muted hover:text-primary"
                        aria-label="Meldung schließen"
                        @click="emit('remove', feedback.id)"
                    >
                        <i class="las la-times text-lg"></i>
                    </button>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.airmius-feedback-enter-active,
.airmius-feedback-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.airmius-feedback-enter-from,
.airmius-feedback-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>

