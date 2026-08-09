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
let previousFocus = null

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
    if (!props.searchResults.length) return
    activeIndex.value = (activeIndex.value + direction + props.searchResults.length) % props.searchResults.length
    scrollActiveIntoView()
}

const openActive = () => {
    const result = props.searchResults[activeIndex.value]
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
        activeIndex.value = props.searchResults.length ? 0 : -1
        void nextTick(() => input.value?.focus())
    } else if (previousFocus instanceof HTMLElement) {
        previousFocus.focus()
        previousFocus = null
    }
})

watch(() => props.searchResults, (results) => {
    activeIndex.value = results.length ? 0 : -1
})

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

                <div id="airmius-command-results">
                    <AppSearchResults
                        avatar-class="h-11 w-11"
                        container-class="max-h-[calc(100dvh-13rem)] overflow-y-auto sm:max-h-[34rem]"
                        :active-index="activeIndex"
                        :search-loading="searchLoading"
                        :search-results="searchResults"
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
