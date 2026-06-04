<script setup>
defineProps({
    alerts: { type: Array, default: () => [] },
    tokenSeverityClass: { type: Function, required: true },
    tokenExpiryLabel: { type: Function, required: true },
})
</script>

<template>
    <div v-if="alerts.length" class="border-b border-border bg-bg px-5 py-4">
        <div
            v-for="alert in alerts"
            :key="alert.key"
            class="rounded-lg border p-4"
            :class="tokenSeverityClass(alert.severity)"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide">KI-Anbieter Warnung</p>
                    <h2 class="mt-1 text-base font-semibold">{{ alert.label }}: {{ alert.status_label }}</h2>
                    <p class="mt-1 text-sm">{{ alert.message }}</p>
                </div>
                <span class="rounded-full border border-current/30 px-3 py-1 text-xs font-semibold">
                    Ablauf: {{ tokenExpiryLabel(alert) }}
                </span>
            </div>
        </div>
    </div>
</template>
