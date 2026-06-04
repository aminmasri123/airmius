<script setup>
defineProps({
    actions: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['select'])
</script>

<template>
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <button
            v-for="action in actions"
            :key="action.key"
            type="button"
            class="group rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-xl"
            :class="action.color"
            @click="emit('select', action.target || action.key)"
        >
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl">
                <i :class="action.icon"></i>
            </div>
            <h3 class="mt-4 text-base font-bold text-primary group-hover:text-white">
                {{ $t(action.titleKey) }}
            </h3>
            <p class="mt-2 text-sm leading-6 text-secondary group-hover:text-white/80">
                {{ $t(action.descriptionKey) }}
            </p>
            <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold">
                {{ $t('sport_map.landing.start_action') }}
                <i class="las la-arrow-right"></i>
            </span>
        </button>
    </section>
</template>
