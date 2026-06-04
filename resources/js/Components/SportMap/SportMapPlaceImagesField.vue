<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    previewImages: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['update:modelValue', 'upload'])
</script>

<template>
    <div class="rounded-xl border border-border bg-inputBg/60 p-3">
        <p class="text-sm font-semibold text-primary">{{ $t('sport_map.places.images.title') }}</p>
        <p class="mt-1 text-xs leading-5 text-secondary">{{ $t('sport_map.places.images.description') }}</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.places.images.upload_label') }}</span>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm"
                    @change="emit('upload', $event)"
                >
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.places.images.urls_label') }}</span>
                <input
                    :value="modelValue"
                    class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm"
                    placeholder="https://... , https://..."
                    @input="emit('update:modelValue', $event.target.value)"
                >
            </label>
        </div>

        <div v-if="previewImages.length" class="mt-3 grid grid-cols-4 gap-2">
            <img
                v-for="image in previewImages"
                :key="image"
                :src="image"
                alt=""
                class="aspect-video rounded-lg border border-border object-cover"
            >
        </div>
    </div>
</template>
