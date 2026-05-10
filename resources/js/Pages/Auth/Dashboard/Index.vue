<!-- Pages/Dashboard.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
defineOptions({ layout: AppLayout })

const page = usePage()
const storage = computed(() => page.props.auth?.user?.storage_usage || null)

const formatBytes = (bytes) => {
    const value = Number(bytes || 0)

    if (value < 1024) return `${value} B`
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}
</script>

<template>

    <Head :title="$t('Dashboard')" />
    <div class="space-y-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="surface-card p-5">
                <div class="text-xl font-bold">4</div>
                <div class="text-secondary text-sm">Trainings</div>
            </div>

            <div class="surface-card p-5">
                <div class="text-xl font-bold">87</div>
                <div class="text-secondary text-sm">Fitness</div>
            </div>

            <div v-if="storage" class="surface-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold uppercase text-secondary">Speicher</div>
                        <div class="mt-2 text-xl font-bold">{{ formatBytes(storage.remaining_bytes) }}</div>
                        <div class="text-secondary text-sm">noch frei von {{ storage.limit_gb }} GB</div>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-primary">
                        {{ storage.plan_name }}
                    </span>
                </div>
                <div class="mt-4 h-2 rounded-full bg-inputBg">
                    <div
                        class="h-2 rounded-full bg-buttonPrimary"
                        :style="{ width: `${storage.used_percent}%` }"
                    ></div>
                </div>
                <div class="mt-2 text-xs text-secondary">
                    {{ formatBytes(storage.used_bytes) }} genutzt · {{ storage.used_percent }}%
                </div>
            </div>
        </div>
    </div>
</template>
