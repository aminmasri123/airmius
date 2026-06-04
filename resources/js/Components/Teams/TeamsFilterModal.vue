<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    show: { type: Boolean, default: false },
    filtersForm: { type: Object, required: true },
    sports: { type: Array, default: () => [] },
    applyFilters: { type: Function, required: true },
    resetFilters: { type: Function, required: true },
})

const emit = defineEmits(['update:show'])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="emit('update:show', false)"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">
                                Filter
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Suche nach Vereinen, Sportart oder Ort.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="emit('update:show', false)"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Verein suchen
                        </label>

                        <input
                            v-model="filtersForm.search"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Verein suchen')"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Sportart
                        </label>

                        <SearchableSelect
                            v-model="filtersForm.sport_type"
                            class="mt-1"
                            :options="sports"
                            value-key="slug"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            :placeholder="$t('Sportart suchen')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-primary">
                            Ort
                        </label>

                        <input
                            v-model="filtersForm.location"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('Ort, Stadt, PLZ oder Land')"
                        >
                    </div>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary"
                            @click="resetFilters"
                        >
                            Zurücksetzen
                        </button>

                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="applyFilters"
                        >
                            Anwenden
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>


