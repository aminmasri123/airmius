<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

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
        <div class="flex flex-col gap-3 border-b border-border p-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold text-primary">{{ currentFolder?.name || tx('files.manager') }}</h1>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-secondary">
                        <span class="rounded-full border border-border px-2 py-1">{{ totalFolders }} {{ tx('files.folders') }}</span>
                        <span class="rounded-full border border-border px-2 py-1">{{ totalFiles }} {{ tx('files.files') }}</span>
                        <span v-if="currentFolder" class="max-w-full truncate rounded-full border border-border px-2 py-1">{{ currentFolder.name }}</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-primary hover:bg-inputBg sm:w-auto md:hidden"
                        :aria-expanded="showMobileFiltersModel"
                        @click="showMobileFiltersModel = !showMobileFiltersModel"
                    >
                        <i class="las la-sliders-h text-lg"></i>
                        {{ tx('files.search_sort') }}
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
                        :placeholder="tx('files.search_placeholder')"
                        :disabled="isFiltering"
                        :aria-label="tx('files.search_aria')"
                        @keyup.enter="scheduleFilterRefresh(true)"
                    />
                    <button v-if="fileSearchModel" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-secondary hover:text-primary" type="button" @click="clearSearch" :aria-label="tx('files.search_clear')">×</button>
                </div>
                <select
                    v-model="filesPerPageModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-36"
                    :aria-label="tx('files.per_page')"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="pageSize in pageSizeOptions" :key="`page-size-${pageSize}`" :value="pageSize">
                        {{ tx('files.per_page_option', { count: `${tx('files.files')}: ${pageSize}` }) }}
                    </option>
                </select>
                <select
                    v-model="foldersPerPageModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-36"
                    :aria-label="tx('files.per_page')"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="pageSize in pageSizeOptions" :key="`folder-page-size-${pageSize}`" :value="pageSize">
                        {{ tx('files.per_page_option', { count: `${tx('files.folders')}: ${pageSize}` }) }}
                    </option>
                </select>
                <select
                    v-model="folderSortModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                    :aria-label="tx('files.sort_aria')"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="option in folderSortOptions" :key="`folder-${option.value}`" :value="option.value">
                        {{ tx('files.sort_option', { value: `${tx('files.folders')}: ${option.label}` }) }}
                    </option>
                </select>
                <select
                    v-model="fileSortModel"
                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                    :aria-label="tx('files.sort_aria')"
                    :disabled="isFiltering"
                    @change="applyFilters"
                >
                    <option v-for="option in fileSortOptions" :key="`file-${option.value}`" :value="option.value">
                        {{ tx('files.sort_option', { value: `${tx('files.files')}: ${option.label}` }) }}
                    </option>
                </select>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <button
                    v-if="currentFolder"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-primary hover:bg-inputBg sm:h-9 sm:justify-start"
                    type="button"
                    :disabled="isFiltering"
                    @click="openFolder(currentFolder.parent)"
                    :aria-label="tx('files.parent_folder')"
                >
                    <i class="las la-arrow-left"></i>
                    {{ tx('files.back') }}
                </button>
                <p class="text-xs text-secondary" role="status" aria-live="polite">{{ filterStatusText }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2 p-3 lg:grid-cols-2 2xl:grid-cols-3">
            <div
                v-for="folder in activeFolders"
                :key="folder.id"
                class="group flex items-center gap-2 rounded-lg border border-border bg-inputBg/30 p-2 sm:border-transparent sm:bg-transparent sm:hover:border-border sm:hover:bg-inputBg"
            >
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 text-left"
                    @click="openFolder(folder)"
                    :aria-label="tx('files.open_folder')"
                >
                    <i class="las la-folder text-3xl text-warning"></i>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-primary">{{ folder.name }}</p>
                        <p class="truncate whitespace-nowrap text-xs text-secondary">{{ folder.files_count || 0 }} {{ tx('files.files') }}</p>
                    </div>
                </button>
                <button
                    type="button"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-secondary hover:bg-card sm:h-9 sm:w-9 sm:opacity-0 sm:group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="openShare(folder, 'folder')"
                    :title="tx('files.share_folder')"
                    :aria-label="tx('files.share_folder')"
                >
                    <i class="las la-share-alt"></i>
                </button>
                <button
                    type="button"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-secondary hover:bg-card sm:h-9 sm:w-9 sm:opacity-0 sm:group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="openRename(folder, 'folder')"
                    :title="tx('files.rename_folder')"
                    :aria-label="tx('files.rename_folder')"
                >
                    <i class="las la-pen"></i>
                </button>
                <button
                    type="button"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-secondary hover:bg-card sm:h-9 sm:w-9 sm:opacity-0 sm:group-hover:opacity-100"
                    :disabled="isFiltering"
                    @click.stop="confirmDeleteFolder(folder)"
                    :title="tx('files.delete_folder')"
                    :aria-label="tx('files.delete_folder')"
                >
                    <i class="las la-trash"></i>
                </button>
            </div>

            <div v-for="file in activeFiles" :key="file.id" class="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <i class="las la-file-alt shrink-0 text-3xl text-secondary"></i>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-primary">{{ fileName(file) }}</p>
                        <p class="truncate text-xs text-secondary">{{ contextLabel(file) }} · {{ file.type }} · {{ formatSize(file.size) }}</p>
                    </div>
                </div>
                <div class="flex w-full items-center justify-end gap-1 sm:w-auto">
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9"
                        :disabled="isFiltering"
                        @click="openShare(file)"
                        :title="tx('files.share_file')"
                        :aria-label="tx('files.share_file')"
                    >
                        <i class="las la-share-alt"></i>
                    </button>
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9"
                        :disabled="isFiltering"
                        @click="openRename(file)"
                        :title="tx('files.rename_file')"
                        :aria-label="tx('files.rename_file')"
                    >
                        <i class="las la-pen"></i>
                    </button>
                    <a :href="route('auth.files.download', file.id)" class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9" :title="tx('files.download_file')" :aria-label="tx('files.download_file')">
                        <i class="las la-download"></i>
                    </a>
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9"
                        :disabled="isFiltering"
                        @click="deleteFile(file)"
                        :title="tx('files.delete_file')"
                        :aria-label="tx('files.delete_file')"
                    >
                        <i class="las la-trash"></i>
                    </button>
                </div>
            </div>

            <div v-if="!activeFiles.length && !activeFolders.length" class="col-span-full flex min-h-40 items-center justify-center p-4 text-center text-sm text-secondary">
                {{ emptyStateText }}
            </div>
            <p v-if="activeFiles.length" class="col-span-full text-xs text-secondary">
                {{ tx('files.range_files', { start: fileRangeStart, end: fileRangeEnd, total: totalFiles }) }}
            </p>
            <p v-if="activeFolders.length" class="col-span-full text-xs text-secondary">
                {{ tx('files.range_folders', { start: folderRangeStart, end: folderRangeEnd, total: totalFolders }) }}
            </p>
            <div v-if="lastFoldersPage > 1" class="col-span-full flex flex-col gap-2 border-t border-border pt-2 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-secondary">{{ tx('files.folder_page', { current: currentFoldersPage, last: lastFoldersPage }) }}</span>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFoldersPage <= 1"
                        type="button"
                        @click="goToFoldersPage(currentFoldersPage - 1)"
                    >
                        {{ tx('files.back') }}
                    </button>
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFoldersPage >= lastFoldersPage"
                        type="button"
                        @click="goToFoldersPage(currentFoldersPage + 1)"
                    >
                        {{ tx('files.next') }}
                    </button>
                </div>
            </div>

            <div v-if="lastFilesPage > 1" class="col-span-full flex flex-col gap-2 border-t border-border pt-2 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-secondary">{{ tx('files.file_page', { current: currentFilesPage, last: lastFilesPage }) }}</span>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFilesPage <= 1"
                        type="button"
                        @click="goToFilesPage(currentFilesPage - 1)"
                    >
                        {{ tx('files.back') }}
                    </button>
                    <button
                        class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                        :disabled="currentFilesPage >= lastFilesPage"
                        type="button"
                        @click="goToFilesPage(currentFilesPage + 1)"
                    >
                        {{ tx('files.next') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
