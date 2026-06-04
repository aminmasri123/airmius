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
    <section class="surface-card p-2">
        <div class="flex gap-2 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('update:activeTab', tab.key)"
            >
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.count"
                    class="rounded-full px-2 py-0.5 text-xs"
                    :class="activeTab === tab.key ? 'bg-white/20 text-buttonTextPrimary' : 'bg-bg text-secondary'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>
    </section>
</template>
