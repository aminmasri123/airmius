<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppSearchResults from '@/Components/Auth/Layouts/AppSearchResults.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    searchTerm: { type: String, default: '' },
    searchLoading: { type: Boolean, default: false },
    searchResults: { type: Array, default: () => [] },
    searchError: { type: String, default: '' },
})

const emit = defineEmits(['update:searchTerm', 'close', 'request-join', 'retry'])
const { t } = useI18n()
const dialog = ref(null)
const input = ref(null)
const activeIndex = ref(-1)
const selectedType = ref('all')
const savedViews = ref([])
const savedViewsLoading = ref(false)
const savedViewsError = ref('')
const saveFormOpen = ref(false)
const saveName = ref('')
const savingView = ref(false)
let previousFocus = null

const searchTypes = ['all', 'user', 'club', 'team', 'event', 'course', 'product', 'file', 'invoice', 'module']
const visibleResults = computed(() => selectedType.value === 'all'
    ? props.searchResults
    : props.searchResults.filter((result) => result.type === selectedType.value))

const searchTermModel = computed({
    get: () => props.searchTerm,
    set: (value) => emit('update:searchTerm', value),
})

const focusableElements = () => Array.from(dialog.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
) || [])

const scrollActiveIntoView = () => nextTick(() => {
    document.getElementById(`airmius-search-result-${activeIndex.value}`)?.scrollIntoView({ block: 'nearest' })
})

const moveActive = (direction) => {
    if (!visibleResults.value.length) return
    activeIndex.value = (activeIndex.value + direction + visibleResults.value.length) % visibleResults.value.length
    scrollActiveIntoView()
}

const openActive = () => {
    const result = visibleResults.value[activeIndex.value]
    if (!result?.url) return
    emit('close')
    router.visit(result.url)
}

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault()
        emit('close')
        return
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        moveActive(1)
        return
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault()
        moveActive(-1)
        return
    }
    if (event.key === 'Enter' && event.target === input.value && activeIndex.value >= 0) {
        event.preventDefault()
        openActive()
        return
    }
    if (event.key !== 'Tab') return

    const focusable = focusableElements()
    if (!focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(() => props.open, (open) => {
    if (open) {
        previousFocus = document.activeElement
        activeIndex.value = visibleResults.value.length ? 0 : -1
        void loadSavedViews()
        void nextTick(() => input.value?.focus())
    } else if (previousFocus instanceof HTMLElement) {
        previousFocus.focus()
        previousFocus = null
    }
})

watch(visibleResults, (results) => {
    activeIndex.value = results.length ? 0 : -1
})

const loadSavedViews = async () => {
    savedViewsLoading.value = true
    savedViewsError.value = ''
    try {
        const response = await window.axios.get(route('auth.saved-views.index'), {
            params: { workspace: 'global_search' },
        })
        savedViews.value = response.data.data || []
    } catch {
        savedViewsError.value = t('search.saved_views_error')
    } finally {
        savedViewsLoading.value = false
    }
}

const applySavedView = (view) => {
    const types = view.configuration?.types || []
    selectedType.value = types.length === 1 ? types[0] : 'all'
    searchTermModel.value = view.configuration?.query || ''
}

const storeSavedView = async () => {
    const name = saveName.value.trim()
    if (!name || savingView.value) return
    savingView.value = true
    savedViewsError.value = ''
    try {
        const response = await window.axios.post(route('auth.saved-views.store'), {
            workspace: 'global_search',
            name,
            configuration: {
                query: props.searchTerm.trim(),
                types: selectedType.value === 'all' ? [] : [selectedType.value],
            },
            is_favorite: true,
        })
        savedViews.value = [response.data.data, ...savedViews.value]
        saveName.value = ''
        saveFormOpen.value = false
    } catch (error) {
        savedViewsError.value = error?.response?.data?.message || t('search.saved_views_error')
    } finally {
        savingView.value = false
    }
}

const removeSavedView = async (view) => {
    savedViewsError.value = ''
    try {
        await window.axios.delete(route('auth.saved-views.destroy', view.id))
        savedViews.value = savedViews.value.filter((candidate) => candidate.id !== view.id)
    } catch {
        savedViewsError.value = t('search.saved_views_error')
    }
}

onBeforeUnmount(() => {
    if (previousFocus instanceof HTMLElement) previousFocus.focus()
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[80] bg-black/65 p-3 backdrop-blur-sm sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="airmius-command-title"
            @mousedown.self="emit('close')"
        >
            <div ref="dialog" class="mx-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-2xl overflow-hidden rounded-3xl border border-border bg-card shadow-2xl sm:max-h-[min(46rem,calc(100dvh-4rem))]" @keydown="handleKeydown">
                <div class="border-b border-border bg-gradient-to-r from-air-blue/10 via-card to-emerald-400/10 p-4 sm:p-5">
                    <div class="mb-3 flex items-start justify-between gap-4">
                        <div>
                            <h2 id="airmius-command-title" class="text-base font-black text-primary sm:text-lg">{{ t('search.command_title') }}</h2>
                            <p class="mt-0.5 text-xs text-secondary">{{ t('search.command_hint') }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-secondary hover:text-primary focus-visible:ring-2 focus-visible:ring-air-blue" :aria-label="t('search.close')" @click="emit('close')">
                            <i class="las la-times text-xl" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative min-w-0 flex-1">
                            <i class="las la-search absolute start-3 top-1/2 -translate-y-1/2 text-secondary" aria-hidden="true"></i>

                            <input
                                ref="input"
                                v-model="searchTermModel"
                                class="min-h-12 w-full rounded-xl border border-border bg-inputBg py-3 pe-3 ps-10 text-sm text-primary focus:border-air-blue focus:ring-air-blue"
                                type="search"
                                autocomplete="off"
                                :placeholder="$t('search.placeholder')"
                                :aria-label="t('search.placeholder')"
                                :aria-activedescendant="activeIndex >= 0 ? `airmius-search-result-${activeIndex}` : undefined"
                                aria-controls="airmius-command-results"
                            >
                        </div>
                        <kbd class="hidden rounded-lg border border-border bg-card px-2 py-1 text-[11px] font-bold text-secondary sm:inline">{{ t('search.escape_key') }}</kbd>
                    </div>
                </div>

                <div v-if="searchError" class="flex items-center justify-between gap-3 border-b border-error/25 bg-error/10 px-4 py-3 text-sm text-error" role="alert" aria-live="assertive">
                    <span>{{ searchError }}</span>
                    <button type="button" class="shrink-0 font-bold underline" @click="emit('retry')">{{ t('search.retry') }}</button>
                </div>

                <div class="border-b border-border px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="type in searchTypes"
                            :key="type"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs font-bold transition"
                            :class="selectedType === type ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                            @click="selectedType = type"
                        >
                            {{ type === 'all' ? t('search.all_types') : t(`search.types.${type}`) }}
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-secondary">{{ t('search.saved_views') }}</span>
                        <span v-if="savedViewsLoading" class="text-xs text-secondary">{{ t('search.loading') }}</span>
                        <div
                            v-for="view in savedViews"
                            :key="view.id"
                            class="group inline-flex items-center gap-1 rounded-lg border border-border bg-inputBg px-2 py-1 text-xs font-semibold text-primary"
                        >
                            <button type="button" class="inline-flex items-center gap-1" @click="applySavedView(view)">
                                <i class="las la-star text-amber-500" aria-hidden="true"></i>
                                <span>{{ view.name }}</span>
                            </button>
                            <button
                                type="button"
                                class="ms-1 text-secondary hover:text-error"
                                :aria-label="t('search.delete_saved_view', { name: view.name })"
                                @click="removeSavedView(view)"
                            >×</button>
                        </div>
                        <button type="button" class="rounded-lg border border-dashed border-air-blue px-2 py-1 text-xs font-bold text-air-blue" @click="saveFormOpen = !saveFormOpen">
                            <i class="las la-bookmark" aria-hidden="true"></i> {{ t('search.save_view') }}
                        </button>
                    </div>

                    <form v-if="saveFormOpen" class="mt-3 flex gap-2" @submit.prevent="storeSavedView">
                        <input v-model="saveName" class="min-h-10 min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 text-sm text-primary" maxlength="80" :placeholder="t('search.saved_view_name')">
                        <button type="submit" class="rounded-lg bg-air-blue px-3 text-sm font-bold text-white disabled:opacity-50" :disabled="!saveName.trim() || savingView">{{ t('search.save') }}</button>
                    </form>
                    <p v-if="savedViewsError" class="mt-2 text-xs text-error" role="alert">{{ savedViewsError }}</p>
                </div>

                <div id="airmius-command-results">
                    <AppSearchResults
                        avatar-class="h-11 w-11"
                        container-class="max-h-[calc(100dvh-13rem)] overflow-y-auto sm:max-h-[34rem]"
                        :active-index="activeIndex"
                        :search-loading="searchLoading"
                        :search-results="visibleResults"
                        :search-term="searchTermModel"
                        @close="emit('close')"
                        @request-join="emit('request-join', $event)"
                        @update:active-index="activeIndex = $event"
                    />
                </div>

                <div class="hidden items-center justify-between border-t border-border px-4 py-2 text-[11px] text-secondary sm:flex">
                    <span>{{ t('search.keyboard_hint') }}</span>
                    <span>{{ t('search.privacy_hint') }}</span>
                </div>
            </div>
        </div>
    </Teleport>
</template>
