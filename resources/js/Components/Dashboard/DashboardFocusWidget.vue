<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    focusItems: { type: Array, default: () => [] },
    translatedText: { type: Function, required: true },
    translatedFocusBody: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5 xl:col-span-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('Heute wichtig') }}</p>
                <h2 class="mt-1 text-xl font-black text-primary">{{ $t('Nächste Schritte') }}</h2>
            </div>
            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-bold text-air-blue">{{ focusItems.length }}</span>
        </div>

        <div v-if="focusItems.length" class="mt-5 divide-y divide-border">
            <Link
                v-for="item in focusItems"
                :key="`${item.title}-${item.body}`"
                :href="item.href"
                class="flex items-center gap-3 py-3 transition hover:text-air-blue"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-inputBg text-air-blue">
                    <i :class="[item.icon, 'text-xl']"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-black text-primary">{{ translatedText(item.title) }}</span>
                    <span class="block truncate text-xs text-secondary">{{ translatedFocusBody(item) }}</span>
                </span>
                <span class="text-right text-xs font-semibold text-secondary">{{ translatedText(item.meta) }}</span>
            </Link>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-secondary">
            {{ $t('Alles ruhig. Du hast gerade keine offenen Punkte.') }}
        </div>
    </div>
</template>

