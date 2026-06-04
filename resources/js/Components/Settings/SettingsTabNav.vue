<script setup>
defineProps({
    activeTab: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['select'])
</script>

<template>
    <nav class="surface-card p-2" aria-label="Einstellungsbereiche">
        <div class="flex gap-2 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('select', tab.key)"
            >
                <i v-if="tab.icon" :class="[tab.icon, 'text-lg']"></i>
                <span>{{ tab.label }}</span>
            </button>
        </div>
    </nav>
</template>
