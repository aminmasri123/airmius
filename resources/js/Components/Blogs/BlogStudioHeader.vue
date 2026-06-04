<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    can: { type: Object, required: true },
    filterStatus: { type: String, required: true },
    search: { type: String, required: true },
})

const emit = defineEmits(['apply-filters', 'update:filterStatus', 'update:search'])

const updateSearch = (event) => {
    emit('update:search', event.target.value)
}

const updateStatus = (event) => {
    emit('update:filterStatus', event.target.value)
    emit('apply-filters')
}
</script>

<template>
    <div class="surface-card overflow-hidden">
        <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Website CMS</p>
                <h1 class="mt-1 text-3xl font-bold text-primary">Blog Studio</h1>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                    Schreibe Beiträge mit Überschriften, Listen, Markierungen, Links, Zitaten und sauberer öffentlicher Darstellung.
                </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-[auto_160px_auto_auto]">
                <Link
                    v-if="can.manageCategories"
                    :href="route('blog-categories.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                >
                    Kategorien
                </Link>
                <input
                    :value="search"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="Suchen..."
                    @input="updateSearch"
                    @keydown.enter.prevent="$emit('apply-filters')"
                />
                <select :value="filterStatus" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="updateStatus">
                    <option value="all">Alle Status</option>
                    <option value="draft">Entwurf</option>
                    <option value="review">Review</option>
                    <option value="published">Veröffentlicht</option>
                    <option value="archived">Archiviert</option>
                </select>
                <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="$emit('apply-filters')">
                    Filtern
                </button>
            </div>
        </div>
    </div>
</template>

