<script setup>
defineProps({
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
})

const emit = defineEmits(['update:activeTab'])
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-2">
        <div class="grid gap-2 md:grid-cols-4">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="flex items-center justify-between gap-3 rounded-lg px-4 py-3 text-left transition"
                :class="activeTab === tab.id ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-inputBg hover:text-primary'"
                @click="emit('update:activeTab', tab.id)"
            >
                <span class="inline-flex items-center gap-3">
                    <i :class="[tab.icon, 'text-xl']"></i>
                    <span class="font-semibold">{{ tab.label }}</span>
                </span>
                <span
                    v-if="tab.count !== null"
                    class="rounded-full px-2 py-0.5 text-xs font-bold"
                    :class="activeTab === tab.id ? 'bg-white/20 text-buttonTextPrimary' : 'bg-inputBg text-secondary'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>
    </section>
</template>
