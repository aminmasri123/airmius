<script setup>
defineProps({
    scopes: {
        type: Array,
        required: true,
    },
    activeScope: {
        type: String,
        required: true,
    },
    sponsorStats: {
        type: Object,
        required: true,
    },
})

defineEmits(['change'])
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-3">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="scope in scopes"
                :key="scope.key"
                type="button"
                class="rounded-md px-4 py-2 text-left text-sm font-semibold transition"
                :class="activeScope === scope.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-secondary/10 hover:text-primary'"
                @click="$emit('change', scope.key)"
            >
                {{ scope.label }}
                <span class="ml-2 rounded-full bg-secondary/20 px-2 py-0.5 text-xs">
                    {{ scope.key === 'all' ? sponsorStats.total : sponsorStats[scope.key] }}
                </span>
            </button>
        </div>
    </section>
</template>
