<script setup>
import { computed } from 'vue'

const props = defineProps({
    currentFolder: { type: Object, default: null },
    totalFolders: { type: Number, required: true },
    totalFiles: { type: Number, required: true },
    showMobileFilters: { type: Boolean, default: false },
    fileSearch: { type: String, default: '' },
    filesPerPage: { type: Number, required: true },
    foldersPerPage: { type: Number, required: true },
    folderSort: { type: String, required: true },
    fileSort: { type: String, required: true },
    pageSizeOptions: { type: Array, default: () => [] },
    folderSortOptions: { type: Array, default: () => [] },
    fileSortOptions: { type: Array, default: () => [] },
    isFiltering: { type: Boolean, default: false },
    activeFolders: { type: Array, default: () => [] },
    activeFiles: { type: Array, default: () => [] },
    emptyStateText: { type: String, required: true },
    fileRangeStart: { type: Number, required: true },
    fileRangeEnd: { type: Number, required: true },
    folderRangeStart: { type: Number, required: true },
    folderRangeEnd: { type: Number, required: true },
    filterStatusText: { type: String, required: true },
    currentFilesPage: { type: Number, required: true },
    currentFoldersPage: { type: Number, required: true },
    lastFilesPage: { type: Number, required: true },
    lastFoldersPage: { type: Number, required: true },
    clearSearch: { type: Function, required: true },
    scheduleFilterRefresh: { type: Function, required: true },
    applyFilters: { type: Function, required: true },
    openFolder: { type: Function, required: true },
    openShare: { type: Function, required: true },
    openRename: { type: Function, required: true },
    confirmDeleteFolder: { type: Function, required: true },
    fileName: { type: Function, required: true },
    contextLabel: { type: Function, required: true },
    formatSize: { type: Function, required: true },
    deleteFile: { type: Function, required: true },
    goToFoldersPage: { type: Function, required: true },
    goToFilesPage: { type: Function, required: true },
})

const emit = defineEmits([
    'update:fileSearch',
    'update:filesPerPage',
    'update:foldersPerPage',
    'update:folderSort',
    'update:fileSort',
    'update:showMobileFilters',
])

const fileSearchModel = computed({
    get: () => props.fileSearch,
    set: (value) => emit('update:fileSearch', value),
})
const filesPerPageModel = computed({
    get: () => props.filesPerPage,
    set: (value) => emit('update:filesPerPage', value),
})
const foldersPerPageModel = computed({
    get: () => props.foldersPerPage,
    set: (value) => emit('update:foldersPerPage', value),
})
const folderSortModel = computed({
    get: () => props.folderSort,
    set: (value) => emit('update:folderSort', value),
})
const fileSortModel = computed({
    get: () => props.fileSort,
    set: (value) => emit('update:fileSort', value),
})
const showMobileFiltersModel = computed({
    get: () => props.showMobileFilters,
    set: (value) => emit('update:showMobileFilters', value),
})
</script>

<template>
    <section class="min-w-0 rounded-lg border border-border bg-card">
        <div class="flex flex-col gap-2 border-b border-border p-3">
            <div class="flex items-start justify-between gap-3 sm:items-center">
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold text-primary">{{ currentFolder?.name || 'Dateimanager' }}</h1>
                    <p class="text-sm text-secondary">{{ totalFolders }} Ordner · {{ totalFiles }} Dateien</p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-primary hover:bg-inputBg md:hidden"
                        :aria-expanded="showMobileFiltersModel"
                        @click="showMobileFiltersModel = !showMobileFiltersModel"
                    >
                        <i class="las la-sliders-h text-lg"></i>
                        Suchfilter
                    </button>
                </div>
            </div>

            <div
                :class="[
                    showMobileFiltersModel ? 'grid' : 'hidden',
                    'w-full grid-cols-1 gap-2 md:grid md:grid-cols-2 xl:flex xl:flex-wrap xl:items-center xl:justify-end',
                ]"
            >
                <div class="relative sm:col-span-2 xl:col-span-1">
                    <input
                        v-model="fileSearchModel"
                        class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                        placeholder="Suchen..."
                        :disabled="isFiltering"
                        aria-label="Dateien und Ordner durchsuchen"
                        @keyup.enter="scheduleFilterRefresh(true)"
                    />
                    <button v-if="fileSearchModel" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-secondary hover:text-primary" type="button" @click="clearSearch" aria-label="Suche löschen">x</button>
                </div>
                <select
                    v-model="filesPerPageModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-36"
                    aria-label="Dateien pro Seite"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="pageSize in pageSizeOptions" :key="`page-size-${pageSize}`" :value="pageSize">
                        Dateien: {{ pageSize }} / Seite
                    </option>
                </select>
                <select
                    v-model="foldersPerPageModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-36"
                    aria-label="Ordner pro Seite"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="pageSize in pageSizeOptions" :key="`folder-page-size-${pageSize}`" :value="pageSize">
                        Ordner: {{ pageSize }} / Seite
                    </option>
                </select>
                <select
                    v-model="folderSortModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                    aria-label="Ordner sortieren"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="option in folderSortOptions" :key="`folder-${option.value}`" :value="option.value">
                        Ordner: {{ option.label }}
                    </option>
                </select>
                <select
                    v-model="fileSortModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                    aria-label="Dateien sortieren"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="option in fileSortOptions" :key="`file-${option.value}`" :value="option.value">
                        Dateien: {{ option.label }}
                    </option>
                </select>
            </div>
            <button
                v-if="currentFolder"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-sm text-primary hover:bg-inputBg"
                type="button"
                :disabled="isFiltering"
                @click="openFolder(currentFolder.parent)"
                aria-label="In das übergeordnete Verzeichnis gehen"
            >
                <i class="las la-arrow-left"></i>
                Zurück
            </button>
        </div>

        <div class="grid grid-cols-1 gap-2 p-3 lg:grid-cols-2 2xl:grid-cols-3">
            <button
                v-for="folder in activeFolders"
                :key="folder.id"
                type="button"
                class="group flex items-center gap-3 rounded-lg border border-transparent p-3 text-left hover:border-border hover:bg-inputBg"
                @click="openFolder(folder)"
            >
                <i class="las la-folder text-3xl text-yellow-500"></i>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-primary">{{ folder.name }}</p>
                    <p class="truncate whitespace-nowrap text-xs text-secondary">{{ folder.files_count || 0 }} Dateien</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="openShare(folder, 'folder')"
                    title="Freigeben"
                    aria-label="Ordner freigeben"
                >
                    <i class="las la-share-alt"></i>
                </button>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="openRename(folder, 'folder')"
                    title="Umbenennen"
                    aria-label="Ordner umbenennen"
                >
                    <i class="las la-pen"></i>
                </button>
                <button
                    type="button"
                    class="rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="confirmDeleteFolder(folder)"
                    title="Löschen"
                    aria-label="Ordner löschen"
                >
                    <i class="las la-trash"></i>
                </button>
            </button>

            <div v-for="file in activeFiles" :key="file.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                <i class="las la-file-alt text-3xl text-secondary"></i>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-primary">{{ fileName(file) }}</p>
                    <p class="truncate text-xs text-secondary">{{ contextLabel(file) }} · {{ file.type }} · {{ formatSize(file.size) }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-secondary hover:bg-inputBg"
                    :disabled="isFiltering"
                    @click="openShare(file)"
                    title="Freigeben"
                    aria-label="Datei freigeben"
                >
                    <i class="las la-share-alt"></i>
                </button>
                <button
                    type="button"
                    class="rounded-lg p-2 text-secondary hover:bg-inputBg"
                    :disabled="isFiltering"
                    @click="openRename(file)"
                    title="Umbenennen"
                    aria-label="Datei umbenennen"
                >
                    <i class="las la-pen"></i>
                </button>
                <a :href="route('auth.files.download', file.id)" class="rounded-lg p-2 text-secondary hover:bg-inputBg" title="Herunterladen" aria-label="Datei herunterladen">
                    <i class="las la-download"></i>
                </a>
                <button
                    type="button"
                    class="rounded-lg p-2 text-secondary hover:bg-inputBg"
                    :disabled="isFiltering"
                    @click="deleteFile(file)"
                    title="Löschen"
                    aria-label="Datei löschen"
                >
                    <i class="las la-trash"></i>
                </button>
            </div>

            <div v-if="!activeFiles.length && !activeFolders.length" class="col-span-full flex min-h-40 items-center justify-center p-4 text-center text-sm text-secondary">
                {{ emptyStateText }}
            </div>
            <p v-if="activeFiles.length" class="col-span-full text-xs text-secondary">
                Dateien {{ fileRangeStart }} - {{ fileRangeEnd }} von {{ totalFiles }}
            </p>
            <p v-if="activeFolders.length" class="col-span-full text-xs text-secondary">
                Ordner {{ folderRangeStart }} - {{ folderRangeEnd }} von {{ totalFolders }}
            </p>
            <p class="col-span-full text-xs text-secondary" role="status" aria-live="polite">{{ filterStatusText }}</p>

            <div v-if="lastFoldersPage > 1" class="col-span-full flex items-center justify-between gap-3 border-t border-border pt-2">
                <span class="text-xs text-secondary">Ordnerseite {{ currentFoldersPage }} / {{ lastFoldersPage }}</span>
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFoldersPage <= 1"
                        type="button"
                        @click="goToFoldersPage(currentFoldersPage - 1)"
                    >
                        Zurück
                    </button>
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFoldersPage >= lastFoldersPage"
                        type="button"
                        @click="goToFoldersPage(currentFoldersPage + 1)"
                    >
                        Weiter
                    </button>
                </div>
            </div>

            <div v-if="lastFilesPage > 1" class="col-span-full flex items-center justify-between gap-3 border-t border-border pt-2">
                <span class="text-xs text-secondary">Dateiseite {{ currentFilesPage }} / {{ lastFilesPage }}</span>
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFilesPage <= 1"
                        type="button"
                        @click="goToFilesPage(currentFilesPage - 1)"
                    >
                        Zurück
                    </button>
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFilesPage >= lastFilesPage"
                        type="button"
                        @click="goToFilesPage(currentFilesPage + 1)"
                    >
                        Weiter
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>


