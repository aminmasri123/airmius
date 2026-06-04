<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    avatarClass: { type: String, default: 'h-10 w-10' },
    containerClass: { type: String, default: 'max-h-96 overflow-y-auto' },
    searchLoading: { type: Boolean, default: false },
    searchResults: { type: Array, default: () => [] },
    searchTerm: { type: String, default: '' },
})

const emit = defineEmits(['close', 'request-join'])

const initialsFor = (value) => String(value || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase() || '?'
</script>

<template>
    <div :class="containerClass">
        <div v-if="searchTerm.trim().length < 2" class="p-4 text-sm text-secondary">
            {{ $t('search.min_chars') }}
        </div>

        <div v-else-if="searchLoading" class="p-4 text-sm text-secondary">
            {{ $t('search.loading') }}
        </div>

        <div v-else-if="searchResults.length">
            <div
                v-for="result in searchResults"
                :key="`${result.type}-${result.id}`"
                class="flex items-center gap-3 border-b border-border px-3 py-3 last:border-b-0"
            >
                <Link
                    :href="result.url"
                    :class="['flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-inputBg text-xs font-bold text-primary', avatarClass]"
                    @click="emit('close')"
                >
                    <img
                        v-if="result.avatar_url"
                        :src="result.avatar_url"
                        :alt="result.title"
                        class="h-full w-full object-cover"
                    />
                    <span v-else-if="result.type === 'user'">{{ initialsFor(result.title) }}</span>
                    <i v-else :class="[result.type === 'club' ? 'las la-shield-alt' : 'las la-users', 'text-lg text-secondary']"></i>
                </Link>
                <Link :href="result.url" class="min-w-0 flex-1" @click="emit('close')">
                    <p class="truncate text-sm font-semibold text-primary">
                        {{ result.title }}
                    </p>
                    <p class="truncate text-xs text-secondary">
                        {{ result.subtitle }}
                    </p>
                </Link>

                <button
                    v-if="result.join_url"
                    type="button"
                    class="shrink-0 rounded-lg border border-border px-2 py-2 text-xs hover:bg-inputBg"
                    @click="emit('request-join', result)"
                >
                    {{ $t('Beitreten') }}
                </button>
            </div>
        </div>

        <div v-else class="p-4 text-center text-sm text-secondary">
            {{ $t('search.no_results') }}
        </div>
    </div>
</template>
