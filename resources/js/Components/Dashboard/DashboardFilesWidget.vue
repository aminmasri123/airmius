<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    storage: { type: Object, default: null },
    files: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
    formatBytes: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-violet-300">{{ $t('Dateien') }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ $t('Speicher') }}</h2>
            </div>
            <Link :href="route('auth.files.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Dateien') }}
            </Link>
        </div>

        <div v-if="storage" class="mt-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-2xl font-black text-primary">{{ formatBytes(storage.remaining_bytes) }}</p>
                <span class="rounded-full border border-border px-3 py-1 text-xs font-bold text-primary">{{ storage.plan_name }}</span>
            </div>
            <p class="mt-1 text-sm text-secondary">{{ $t('dashboard.free_of_gb', { limit: storage.limit_gb }) }}</p>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-inputBg">
                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-400" :style="{ width: `${storage.used_percent}%` }"></div>
            </div>
            <p class="mt-2 text-xs text-secondary">{{ $t('dashboard.storage_used_percent', { used: formatBytes(storage.used_bytes), percent: storage.used_percent }) }}</p>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-border p-4">
            <p class="text-2xl font-black text-primary">{{ formatBytes(files.bytes) }}</p>
            <p class="mt-1 text-sm text-secondary">{{ $t('dashboard.files_saved_count', { count: formatNumber(files.count) }) }}</p>
        </div>
    </div>
</template>
