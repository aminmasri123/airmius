<script setup>
defineProps({
    activeFilter: { type: String, default: 'all' },
    tabs: { type: Array, default: () => [] },
})

const emit = defineEmits(['change'])
</script>

<template>
    <div class="surface-card overflow-hidden p-2">
        <div class="custom-scrollbar flex gap-2 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="activeFilter === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('change', tab.key)"
            >
                <i :class="tab.icon"></i>
                {{ tab.label }}
            </button>
        </div>
    </div>
</template>
