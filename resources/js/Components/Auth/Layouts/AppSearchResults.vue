<script setup>
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    avatarClass: { type: String, default: 'h-10 w-10' },
    containerClass: { type: String, default: 'max-h-96 overflow-y-auto' },
    searchLoading: { type: Boolean, default: false },
    searchResults: { type: Array, default: () => [] },
    searchTerm: { type: String, default: '' },
    activeIndex: { type: Number, default: -1 },
})

const emit = defineEmits(['close', 'request-join', 'update:activeIndex'])
const { t, te } = useI18n()

const initialsFor = (value) => String(value || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase() || '?'

const iconFor = (type) => ({
    user: 'las la-user',
    club: 'las la-shield-alt',
    team: 'las la-users',
    event: 'las la-calendar-check',
    course: 'las la-graduation-cap',
    product: 'las la-shopping-bag',
    file: 'las la-file-alt',
    module: 'las la-bolt',
}[type] || 'las la-search')

const typeLabel = (result) => result.type_label
    || (te(`search.types.${result.type}`) ? t(`search.types.${result.type}`) : result.type)
const imageFor = (result) => result.avatar_url || result.image_url || null
</script>

<template>
    <div :class="containerClass" role="listbox" :aria-label="t('search.results_label')">
        <div v-if="searchTerm.trim().length < 2" class="p-6 text-center text-sm text-secondary" role="status" aria-live="polite">
            <i class="las la-keyboard mb-2 block text-3xl text-air-blue" aria-hidden="true"></i>
            {{ $t('search.min_chars') }}
        </div>

        <div v-else-if="searchLoading" class="space-y-2 p-3" role="status" aria-live="polite" :aria-label="$t('search.loading')" aria-busy="true">
            <div v-for="index in 4" :key="index" class="flex animate-pulse items-center gap-3 rounded-xl p-2">
                <div :class="['shrink-0 rounded-xl bg-inputBg', avatarClass]"></div>
                <div class="flex-1 space-y-2"><div class="h-3 w-2/3 rounded bg-inputBg"></div><div class="h-2.5 w-1/2 rounded bg-inputBg"></div></div>
            </div>
        </div>

        <div v-else-if="searchResults.length">
            <article
                v-for="(result, index) in searchResults"
                :key="`${result.type}-${result.id}`"
                :id="`airmius-search-result-${index}`"
                class="flex items-center gap-3 border-b border-border px-3 py-3 transition last:border-b-0"
                :class="index === activeIndex ? 'bg-air-blue/10 ring-1 ring-inset ring-air-blue/30' : 'hover:bg-muted/70'"
                role="option"
                :aria-selected="index === activeIndex"
                @mouseenter="emit('update:activeIndex', index)"
            >
                <div :class="['flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-inputBg text-xs font-bold text-primary', avatarClass]" aria-hidden="true">
                    <img
                        v-if="imageFor(result)"
                        :src="imageFor(result)"
                        alt=""
                        class="h-full w-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                    <span v-else-if="result.type === 'user'">{{ initialsFor(result.title) }}</span>
                    <i v-else :class="[result.icon || iconFor(result.type), 'text-lg text-secondary']"></i>
                </div>
                <Link
                    :href="result.url"
                    class="min-w-0 flex-1 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-air-blue"
                    :aria-label="t('search.open_result', { title: result.title, type: typeLabel(result) })"
                    @click="emit('close')"
                >
                    <p class="truncate text-sm font-semibold text-primary">
                        {{ result.title }}
                    </p>
                    <p class="truncate text-xs text-secondary">
                        {{ result.subtitle }}
                    </p>
                </Link>

                <span class="hidden shrink-0 rounded-full bg-inputBg px-2 py-1 text-[10px] font-black uppercase tracking-wide text-secondary sm:inline">
                    {{ typeLabel(result) }}
                </span>

                <button
                    v-if="result.join_url"
                    type="button"
                    class="shrink-0 rounded-lg border border-border px-2 py-2 text-xs font-bold text-primary hover:bg-inputBg focus-visible:ring-2 focus-visible:ring-air-blue"
                    :aria-label="t('search.join_team', { title: result.title })"
                    @click="emit('request-join', result)"
                >
                    {{ $t('actions.join') }}
                </button>
            </article>
        </div>

        <div v-else class="p-8 text-center text-sm text-secondary" role="status" aria-live="polite">
            <i class="las la-search-minus mb-2 block text-3xl" aria-hidden="true"></i>
            {{ $t('search.no_results') }}
        </div>
    </div>
</template>
