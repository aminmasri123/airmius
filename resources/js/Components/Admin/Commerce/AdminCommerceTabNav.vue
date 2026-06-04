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

const emit = defineEmits(['update:activeTab'])
</script>

<template>
    <nav class="sticky top-0 z-30 overflow-x-auto rounded-lg border border-border bg-card p-2 shadow-lg" aria-label="Commerce Admin Bereiche">
        <div class="flex min-w-max gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('update:activeTab', tab.key)"
            >
                <i v-if="tab.icon" :class="[tab.icon, 'text-lg']"></i>
                <span>{{ tab.label }}</span>
                <span
                    class="rounded-full px-2 py-0.5 text-xs"
                    :class="activeTab === tab.key ? 'bg-white/20 text-buttonTextPrimary' : 'bg-muted text-secondary'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>
    </nav>
</template>
