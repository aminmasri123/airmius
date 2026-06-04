<script setup>
defineProps({
    filteredSports: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: '',
    },
    selectedSport: {
        type: Object,
        default: null,
    },
    statusFilter: {
        type: String,
        default: 'all',
    },
})

defineEmits(['select', 'update:search', 'update:statusFilter'])

const filterOptions = [
    { key: 'all', label: 'Alle' },
    { key: 'active', label: 'Aktiv' },
    { key: 'inactive', label: 'Inaktiv' },
]

const usageLabel = (sport) => [
    `${sport.teams_count} Teams`,
    `${sport.clubs_count} Vereine`,
    `${sport.profiles_count} Profile`,
    `${sport.posts_count} Beiträge`,
].join(' · ')
</script>

<template>
    <section class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-border bg-card">
        <div class="shrink-0 space-y-3 border-b border-border p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-primary">
                    Alle Sportarten
                </h2>

                <span class="rounded bg-inputBg px-2 py-1 text-xs text-secondary">
                    {{ filteredSports.length }}
                </span>
            </div>

            <label class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2">
                <i class="las la-search text-lg text-secondary"></i>

                <input
                    :value="search"
                    type="search"
                    placeholder="Name, Slug oder Kategorie"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0"
                    @input="$emit('update:search', $event.target.value)"
                >
            </label>

            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="filter in filterOptions"
                    :key="filter.key"
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium"
                    :class="statusFilter === filter.key
                        ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                        : 'border-border text-secondary hover:bg-inputBg hover:text-primary'"
                    @click="$emit('update:statusFilter', filter.key)"
                >
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <div class="h-80 flex flex-col overflow-y-auto p-2 custom-scrollbar">
            <button
                v-for="sport in filteredSports"
                :key="sport.id"
                type="button"
                class="mb-1 flex w-full items-start justify-between gap-3 rounded-lg p-3 text-left transition hover:bg-muted"
                :class="selectedSport?.id === sport.id ? 'bg-inputBg ring-1 ring-borderHover' : ''"
                @click="$emit('select', sport.id)"
            >
                <span class="min-w-0">
                    <span class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold text-primary">
                            {{ sport.name }}
                        </span>

                        <span
                            class="shrink-0 rounded px-2 py-0.5 text-xs"
                            :class="sport.is_active ? 'bg-success/10 text-success' : 'bg-error/10 text-error'"
                        >
                            {{ sport.is_active ? 'aktiv' : 'inaktiv' }}
                        </span>
                    </span>

                    <span class="mt-1 block truncate text-xs text-secondary">
                        {{ sport.slug }} · {{ sport.category || 'ohne Kategorie' }}
                    </span>

                    <span class="mt-1 block truncate text-xs text-secondary">
                        {{ usageLabel(sport) }}
                    </span>
                </span>

                <span class="shrink-0 rounded border border-border px-2 py-1 text-xs text-secondary">
                    {{ sport.teams_count }}
                </span>
            </button>

            <div v-if="filteredSports.length === 0" class="p-8 text-center text-sm text-secondary">
                Keine Sportarten gefunden.
            </div>
        </div>
    </section>
</template>


