<script setup>
defineProps({
    actors: { type: Array, default: () => [] },
    actorLabels: { type: Object, required: true },
    actorIcons: { type: Object, required: true },
    selectedActor: { type: String, required: true },
})

defineEmits(['update:selectedActor'])
</script>

<template>
    <section class="surface-card p-3">
        <div class="flex gap-2 overflow-x-auto">
            <button
                v-for="actor in actors"
                :key="actor"
                type="button"
                class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="selectedActor === actor ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-muted text-secondary hover:text-primary'"
                @click="$emit('update:selectedActor', actor)"
            >
                <i :class="actorIcons[actor]"></i>
                {{ actorLabels[actor] }}
            </button>
        </div>
    </section>
</template>
