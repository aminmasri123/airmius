<script setup>
defineProps({
    sponsors: {
        type: Object,
        required: true,
    },
})

defineEmits(['visit'])
</script>

<template>
    <div v-if="sponsors?.links?.length > 3" class="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-secondary">
            Zeige {{ sponsors.from }} bis {{ sponsors.to }} von {{ sponsors.total }} Sponsoren.
        </p>
        <div class="flex flex-wrap gap-1">
            <button
                v-for="link in sponsors.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="link.active ? 'bg-primary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-secondary/20'"
                @click="$emit('visit', link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
