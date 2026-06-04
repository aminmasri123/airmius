<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSearchResults from '@/Components/Auth/Layouts/AppSearchResults.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    searchLoading: { type: Boolean, default: false },
    searchResults: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:searchTerm', 'close', 'request-join'])
const { t } = useI18n()

const searchTermModel = computed({
    get: () => props.searchTerm,
    set: (value) => emit('update:searchTerm', value),
})
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[70] bg-black/60 p-3 sm:hidden">
            <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xl">
                <div class="flex items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-0 flex-1">
                        <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>

                        <input
                            v-model="searchTermModel"
                            class="w-full rounded-lg border border-border bg-inputBg py-3 pl-9 pr-3 text-sm text-primary"
                            :placeholder="$t('search.short')"
                            autofocus
                        >
                    </div>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm text-secondary hover:bg-muted hover:text-primary"
                        @click="emit('close')"
                    >
                        {{ t('Schließen') }}
                    </button>
                </div>

                <AppSearchResults
                    avatar-class="h-11 w-11"
                    container-class="max-h-[70vh] overflow-y-auto"
                    :search-loading="searchLoading"
                    :search-results="searchResults"
                    :search-term="searchTermModel"
                    @close="emit('close')"
                    @request-join="emit('request-join', $event)"
                />
            </div>
        </div>
    </Teleport>
</template>

