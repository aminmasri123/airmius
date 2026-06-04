<script setup>
defineProps({
    notice: { type: Object, default: null },
})
</script>

<template>
    <div
        v-if="notice"
        role="status"
        aria-live="polite"
        class="fixed inset-x-3 top-4 z-[90] mx-auto flex max-w-sm items-center gap-3 rounded-xl border px-4 py-3 text-sm font-semibold shadow-xl backdrop-blur"
        :class="notice.type === 'success'
            ? 'border-success/30 bg-success/95 text-white'
            : notice.type === 'error'
                ? 'border-error/30 bg-error/95 text-white'
                : 'border-border bg-card/95 text-primary'"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15">
            <i
                :class="[
                    notice.type === 'success'
                        ? 'las la-check'
                        : notice.type === 'error'
                            ? 'las la-exclamation-triangle'
                            : 'las la-spinner la-spin',
                    'text-lg',
                ]"
            ></i>
        </span>
        <span>{{ notice.message }}</span>
    </div>
</template>
