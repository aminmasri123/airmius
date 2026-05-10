<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    sports: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const selectedSportId = ref(props.sports[0]?.id || null)
const search = ref('')
const statusFilter = ref('all')
const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const sportBeingDeleted = ref(null)

const selectedSport = computed(() => {
    return props.sports.find((sport) => sport.id === selectedSportId.value) || props.sports[0] || null
})

const deleteModalMessage = computed(() => {
    const name = sportBeingDeleted.value?.name || ''

    return `Möchtest du die Sportart "${name}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`
})

const filteredSports = computed(() => {
    const term = search.value.trim().toLowerCase()

    return props.sports.filter((sport) => {
        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && sport.is_active)
            || (statusFilter.value === 'inactive' && !sport.is_active)

        if (!matchesStatus) return false
        if (!term) return true

        return [sport.name, sport.slug, sport.category]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(term)
    })
})

const form = useForm({
    name: selectedSport.value?.name || '',
    slug: selectedSport.value?.slug || '',
    category: selectedSport.value?.category || '',
    sort_order: selectedSport.value?.sort_order || 0,
    is_active: selectedSport.value?.is_active ?? true,
})

const createForm = useForm({
    name: '',
    slug: '',
    category: '',
    sort_order: 0,
    is_active: true,
})

watch(selectedSport, (sport) => {
    form.name = sport?.name || ''
    form.slug = sport?.slug || ''
    form.category = sport?.category || ''
    form.sort_order = sport?.sort_order || 0
    form.is_active = sport?.is_active ?? true
})

const openCreateModal = () => {
    createForm.clearErrors()
    showCreateModal.value = true
}

const closeCreateModal = () => {
    showCreateModal.value = false
    createForm.clearErrors()
}

const save = () => {
    if (!selectedSport.value) return

    form.put(route('admin.sports.update', selectedSport.value.id), {
        preserveScroll: true,
    })
}

const createSport = () => {
    createForm.post(route('admin.sports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset()
            closeCreateModal()
        },
    })
}

const deleteSport = () => {
    if (!selectedSport.value || selectedSport.value.usage_count > 0) return

    sportBeingDeleted.value = selectedSport.value
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    sportBeingDeleted.value = null
}

const confirmDeleteSport = () => {
    if (!sportBeingDeleted.value) return

    router.delete(route('admin.sports.destroy', sportBeingDeleted.value.id), {
        preserveScroll: true,
        data: {
            confirmation: 'delete',
        },
        onSuccess: closeDeleteModal,
    })
}

const usageLabel = (sport) => {
    const parts = [
        `${sport.teams_count} Teams`,
        `${sport.clubs_count} Vereine`,
        `${sport.profiles_count} Profile`,
        `${sport.posts_count} Beiträge`,
    ]

    return parts.join(' · ')
}
</script>

<template>
    <AppLayout>

        <Head title="Sportarten verwalten" />

        <div class="space-y-6 ">
            <!-- HEADER -->
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">
                        System Admin
                    </p>

                    <h1 class="mt-1 text-2xl font-bold text-primary">
                        Sportarten verwalten
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                        Pflege zentrale Sportarten, Aktivstatus und Sortierung. Die Nutzungszahlen zeigen dir,
                        wie viele Teams, Vereine, Profile und Beiträge je Sportart existieren.
                    </p>
                </div>

                <!-- Mobile Plus -->
                <button type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                    @click="openCreateModal" aria-label="Sportart erstellen">
                    <i class="las la-plus text-2xl"></i>
                </button>

                <!-- Desktop Button -->
                <button type="button"
                    class="hidden rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex"
                    @click="openCreateModal">
                    + Sportart erstellen
                </button>
            </div>

            <!-- SUMMARY -->
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">
                        Sportarten
                    </p>
                    <p class="mt-2 text-2xl font-bold text-primary">
                        {{ summary.sports_count || 0 }}
                    </p>
                </div>

                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">
                        Aktiv
                    </p>
                    <p class="mt-2 text-2xl font-bold text-primary">
                        {{ summary.active_count || 0 }}
                    </p>
                </div>

                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">
                        Teams
                    </p>
                    <p class="mt-2 text-2xl font-bold text-primary">
                        {{ summary.teams_count || 0 }}
                    </p>
                </div>

                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">
                        Vereine
                    </p>
                    <p class="mt-2 text-2xl font-bold text-primary">
                        {{ summary.clubs_count || 0 }}
                    </p>
                </div>
            </div>

            <!-- MAIN GRID -->
            <div
                class="grid min-h-0 min-w-0 flex-1 gap-6 overflow-hidden xl:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
                <section class="flex  min-w-0 flex-col overflow-hidden rounded-lg border border-border bg-card">
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

                            <input v-model="search" type="search" placeholder="Name, Slug oder Kategorie"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-primary placeholder-secondary focus:ring-0">
                        </label>

                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="filter in ['all', 'active', 'inactive']" :key="filter" type="button"
                                class="rounded-lg border px-3 py-2 text-sm font-medium" :class="statusFilter === filter
                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                    : 'border-border text-secondary hover:bg-inputBg hover:text-primary'"
                                @click="statusFilter = filter">
                                {{ filter === 'all' ? 'Alle' : filter === 'active' ? 'Aktiv' : 'Inaktiv' }}
                            </button>
                        </div>
                    </div>

                    <div class="h-80 flex flex-col overflow-y-auto p-2 custom-scrollbar">
                        <button v-for="sport in filteredSports" :key="sport.id" type="button"
                            class="mb-1 flex w-full items-start justify-between gap-3 rounded-lg p-3 text-left transition hover:bg-muted"
                            :class="selectedSport?.id === sport.id ? 'bg-inputBg ring-1 ring-borderHover' : ''"
                            @click="selectedSportId = sport.id">
                            <span class="min-w-0">
                                <span class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-primary">
                                        {{ sport.name }}
                                    </span>

                                    <span class="shrink-0 rounded px-2 py-0.5 text-xs"
                                        :class="sport.is_active ? 'bg-success/10 text-success' : 'bg-error/10 text-error'">
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

                <!-- EDIT SPORT -->
                <div class="min-w-0 overflow-y-auto space-y-6">
                    <section v-if="selectedSport" class="rounded-lg border border-border bg-card">
                        <div  class="flex flex-col gap-3 border-b border-border p-4 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-primary">
                                    {{ selectedSport.name }}
                                </h2>

                                <p class="mt-1 text-sm text-secondary">
                                    {{ usageLabel(selectedSport) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                                    :disabled="form.processing" @click="save">
                                    Speichern
                                </button>

                                <button type="button"
                                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="selectedSport.usage_count > 0"
                                    :title="selectedSport.usage_count > 0 ? 'Genutzte Sportarten bitte deaktivieren statt löschen.' : 'Sportart löschen'"
                                    @click="deleteSport">
                                    Löschen
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 md:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">
                                    Name
                                </span>

                                <input v-model="form.name" type="text"
                                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                                <span v-if="form.errors.name" class="text-sm text-error">
                                    {{ form.errors.name }}
                                </span>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">
                                    Slug
                                </span>

                                <input v-model="form.slug" type="text" placeholder="wird aus Name erzeugt"
                                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                                <span v-if="form.errors.slug" class="text-sm text-error">
                                    {{ form.errors.slug }}
                                </span>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">
                                    Kategorie
                                </span>

                                <input v-model="form.category" list="sport-categories" type="text"
                                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                                <span v-if="form.errors.category" class="text-sm text-error">
                                    {{ form.errors.category }}
                                </span>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">
                                    Sortierung
                                </span>

                                <input v-model.number="form.sort_order" type="number" min="0"
                                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                                <span v-if="form.errors.sort_order" class="text-sm text-error">
                                    {{ form.errors.sort_order }}
                                </span>
                            </label>

                            <label
                                class="flex items-center gap-3 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <input v-model="form.is_active" type="checkbox"
                                    class="rounded border-border text-buttonPrimary focus:ring-buttonPrimary">

                                Aktiv in Auswahlfeldern anzeigen
                            </label>

                            <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                                <p>
                                    <strong class="text-primary">
                                        {{ selectedSport.skills_count }}
                                    </strong>
                                    Skills hinterlegt
                                </p>

                                <p class="mt-1">
                                    <strong class="text-primary">
                                        {{ selectedSport.usage_count }}
                                    </strong>
                                    gesamte Nutzungen
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <datalist id="sport-categories">
                <option v-for="category in categories" :key="category" :value="category" />
            </datalist>
        </div>

        <!-- CREATE SPORT MODAL -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
                @click.self="closeCreateModal">
                <div
                    class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                    <!-- Header -->
                    <div class="shrink-0 border-b border-border bg-card p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-primary">
                                    Neue Sportart
                                </h2>

                                <p class="mt-1 text-sm text-secondary">
                                    Slug kann leer bleiben und wird automatisch erzeugt.
                                </p>
                            </div>

                            <button type="button"
                                class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                                @click="closeCreateModal">
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <form class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" @submit.prevent="createSport">
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold uppercase text-secondary">
                                Name
                            </span>

                            <input v-model="createForm.name" type="text"
                                class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                placeholder="z. B. Fußball">

                            <span v-if="createForm.errors.name" class="text-sm text-error">
                                {{ createForm.errors.name }}
                            </span>
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-semibold uppercase text-secondary">
                                Slug
                            </span>

                            <input v-model="createForm.slug" type="text" placeholder="optional"
                                class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">

                            <span v-if="createForm.errors.slug" class="text-sm text-error">
                                {{ createForm.errors.slug }}
                            </span>
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-semibold uppercase text-secondary">
                                Kategorie
                            </span>

                            <input v-model="createForm.category" list="sport-categories" type="text"
                                class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                placeholder="z. B. Ballsport">
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-semibold uppercase text-secondary">
                                Sortierung
                            </span>

                            <input v-model.number="createForm.sort_order" type="number" min="0"
                                class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                        </label>

                        <label
                            class="flex items-center gap-3 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                            <input v-model="createForm.is_active" type="checkbox"
                                class="rounded border-border text-buttonPrimary focus:ring-buttonPrimary">

                            Aktiv in Auswahlfeldern anzeigen
                        </label>
                    </form>

                    <!-- Footer -->
                    <div class="shrink-0 border-t border-border bg-card p-4">
                        <div class="flex gap-3">
                            <button type="button"
                                class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary"
                                @click="closeCreateModal">
                                Abbrechen
                            </button>

                            <button type="button"
                                class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-50"
                                :disabled="createForm.processing" @click="createSport">
                                {{ createForm.processing ? 'Speichern...' : 'Erstellen' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Sportart löschen"
            :message="deleteModalMessage"
            confirm-text="delete"
            cancel-text="Abbrechen"
            @cancel="closeDeleteModal"
            @confirm="confirmDeleteSport"
        />
    </AppLayout>
</template>
