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
    <nav class="sticky top-0 z-30 rounded-lg border border-border bg-card p-2 shadow-lg" :aria-label="$t('adminCommerce.navigation.aria')">
        <label class="sr-only" for="commerce-admin-area">{{ $t('adminCommerce.navigation.selectLabel') }}</label>
        <select
            id="commerce-admin-area"
            class="min-h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm font-semibold text-primary lg:hidden"
            :value="activeTab"
            @change="emit('update:activeTab', $event.target.value)"
        >
            <option v-for="tab in tabs" :key="`mobile-${tab.key}`" :value="tab.key">
                {{ tab.label }} ({{ tab.count }})
            </option>
        </select>

        <div class="hidden gap-2 lg:grid lg:grid-cols-3 2xl:grid-cols-5">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex min-h-11 min-w-0 items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-muted hover:text-primary'"
                @click="emit('update:activeTab', tab.key)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <i v-if="tab.icon" :class="[tab.icon, 'shrink-0 text-lg']"></i>
                    <span class="min-w-0 text-start leading-5">{{ tab.label }}</span>
                </span>
                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs"
                    :class="activeTab === tab.key ? 'bg-white/20 text-buttonTextPrimary' : 'bg-muted text-secondary'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>
    </nav>
</template>
