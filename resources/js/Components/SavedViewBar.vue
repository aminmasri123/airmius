<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    views: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

const emit = defineEmits(['apply', 'save', 'remove'])
const { t } = useI18n()
</script>

<template>
    <div class="mt-3 rounded-xl border border-border bg-bg/60 p-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-secondary">{{ t('search.saved_views') }}</span>
            <span v-if="loading" class="text-xs text-secondary">{{ t('search.loading') }}</span>
            <button
                v-for="view in views"
                :key="view.id"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2 py-1 text-xs font-semibold text-primary hover:border-air-blue"
                @click="emit('apply', view)"
            >
                <i class="las la-star text-amber-500" aria-hidden="true"></i>
                {{ view.name }}
            </button>
            <button type="button" class="rounded-lg border border-dashed border-air-blue px-2 py-1 text-xs font-bold text-air-blue" @click="emit('save')">
                <i class="las la-bookmark" aria-hidden="true"></i> {{ t('search.save_view') }}
            </button>
        </div>
        <div v-if="views.length" class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="view in views"
                :key="`remove-${view.id}`"
                type="button"
                class="text-[11px] font-semibold text-secondary hover:text-error"
                @click="emit('remove', view)"
            >
                × {{ t('search.delete_saved_view', { name: view.name }) }}
            </button>
        </div>
        <p v-if="error" class="mt-2 text-xs font-semibold text-error" role="alert">{{ error }}</p>
    </div>
</template>
