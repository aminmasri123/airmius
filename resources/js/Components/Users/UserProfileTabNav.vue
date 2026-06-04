<script setup>
defineProps({
    tabs: { type: Array, default: () => [] },
    activeProfileTab: { type: String, default: 'overview' },
    selectProfileTab: { type: Function, required: true },
})
</script>

<template>
    <nav class="rounded-xl border border-border bg-card p-2 shadow-sm">
        <div class="grid grid-cols-2 gap-2 sm:flex sm:min-w-max">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition sm:gap-2 sm:px-4 sm:text-sm',
                    activeProfileTab === tab.key
                        ? 'bg-buttonPrimary text-buttonTextPrimary'
                        : 'text-secondary hover:bg-inputBg hover:text-primary',
                ]"
                @click="selectProfileTab(tab.key)"
            >
                <i :class="[tab.icon, 'text-lg']"></i>
                {{ tab.label }}
            </button>
        </div>
    </nav>
</template>
