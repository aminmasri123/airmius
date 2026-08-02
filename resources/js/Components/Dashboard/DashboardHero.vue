<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    userName: { type: String, default: '' },
    quickActions: { type: Array, default: () => [] },
    widgets: { type: Array, default: () => [] },
    showCustomize: { type: Boolean, default: false },
    isWidgetVisible: { type: Function, required: true },
})

const emit = defineEmits(['update:showCustomize', 'toggle-widget', 'show-all'])

const customizeOpen = computed({
    get: () => props.showCustomize,
    set: (value) => emit('update:showCustomize', value),
})
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="relative isolate p-4 sm:p-6">
            <div class="absolute inset-x-0 top-0 -z-10 h-28 bg-gradient-to-r from-air-blue/25 via-emerald-400/15 to-fuchsia-500/20"></div>
            <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('Dashboard') }}</p>
                    <h1 class="mt-1 truncate text-2xl font-black text-primary sm:text-3xl">{{ $t('Hallo') }} {{ userName }}</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-secondary">
                        {{ $t('Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.') }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold text-primary transition hover:border-air-blue hover:text-air-blue sm:w-auto"
                    @click="customizeOpen = !customizeOpen"
                >
                    <i class="las la-sliders-h text-lg"></i>
                    {{ $t('Dashboard anpassen') }}
                </button>
            </div>

            <div
                v-if="customizeOpen"
                class="mt-4 rounded-2xl border border-border bg-inputBg/70 p-3 shadow-inner shadow-black/10 sm:p-4"
            >
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-primary">{{ $t('Widgets') }}</p>
                        <p class="text-xs text-secondary">{{ $t('Wähle aus, was auf deinem Dashboard sichtbar ist.') }}</p>
                    </div>
                    <button type="button" class="w-full rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted sm:w-auto" @click="$emit('show-all')">
                        {{ $t('Alles zeigen') }}
                    </button>
                </div>
                <div class="mt-4 flex min-w-0 flex-wrap gap-2">
                    <button
                        v-for="widget in widgets"
                        :key="widget.key"
                        type="button"
                        class="inline-flex min-w-0 max-w-full items-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition"
                        :class="isWidgetVisible(widget.key) ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border bg-card text-secondary'"
                        @click="$emit('toggle-widget', widget.key)"
                    >
                        <i :class="[widget.icon, 'shrink-0 text-base']"></i>
                        <span class="truncate">{{ $t(widget.label) }}</span>
                    </button>
                </div>
            </div>

            <div class="mt-5 grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="action in quickActions"
                    :key="action.title"
                    :href="action.href"
                    class="min-w-0 rounded-2xl border p-3 transition hover:-translate-y-0.5 hover:border-air-blue sm:p-4"
                    :class="action.tone"
                >
                        <i :class="[action.icon, 'text-xl text-air-blue sm:text-2xl']"></i>
                        <p class="mt-2 truncate text-sm font-black text-primary sm:mt-3">{{ action.title }}</p>
                        <p class="truncate text-xs font-semibold text-secondary">{{ action.subtitle }}</p>
                </Link>
            </div>
        </div>
    </section>
</template>
