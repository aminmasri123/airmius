<script setup>
import { Link } from '@inertiajs/vue3'
import { useClubWorkspaceNavigation } from '@/composables/useClubWorkspaceNavigation'

const props = defineProps({
    active: {
        type: String,
        required: true,
        validator: (value) => ['structure', 'memberships', 'inventory'].includes(value),
    },
    description: {
        type: String,
        required: true,
    },
})

const { items: tabs } = useClubWorkspaceNavigation()
</script>

<template>
    <section v-if="tabs.length" class="surface-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 px-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    Vereinsbereich
                </p>
                <p class="mt-1 text-sm text-secondary">
                    {{ description }}
                </p>
            </div>

            <div class="flex gap-2 overflow-x-auto">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :aria-current="active === tab.key ? 'page' : undefined"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                    :class="active === tab.key
                        ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                        : 'border-border bg-card text-secondary hover:bg-inputBg hover:text-primary'"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                </Link>
            </div>
        </div>
    </section>
</template>
