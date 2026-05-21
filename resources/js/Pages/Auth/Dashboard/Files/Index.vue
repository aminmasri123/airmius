<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    files: { type: [Array, Object], default: () => [] },
    folders: { type: [Array, Object], default: () => [] },
    currentFolder: { type: Object, default: null },
    scope: { type: Object, default: () => ({ type: 'user' }) },
    search: { type: String, default: '' },
    file_sort: { type: String, default: 'name-asc' },
    folder_sort: { type: String, default: 'name-asc' },
    per_page: { type: Number, default: 24 },
    folders_per_page: { type: Number, default: 24 },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
})

const showDeleteModal = ref(false)
const showShareModal = ref(false)
const showRenameModal = ref(false)
const deleteTarget = ref(null)
const deleteType = ref('file')
const deleteConfirmation = ref('')
const deleteProcessing = ref(false)
const fileInput = ref(null)
const itemToShare = ref(null)
const shareType = ref('file')
const friendSearch = ref('')
const pageSizeOptions = [12, 24, 36, 48, 72, 100]
const normalizePageSize = (value) => {
    const normalized = Number.parseInt(value, 10)

    return pageSizeOptions.includes(normalized) ? normalized : 24
}
const fileSearch = ref(props.search || '')
const filesPerPage = ref(normalizePageSize(props.per_page))
const foldersPerPage = ref(normalizePageSize(props.folders_per_page))
const folderSort = ref(props.folder_sort || 'name-asc')
const fileSort = ref(props.file_sort || 'name-asc')
const renameTarget = ref(null)
const renameType = ref('file')
const page = usePage()
const isFiltering = ref(false)
const SEARCH_DEBOUNCE_MS = 350
let searchDebounceTimer = null

const scopeForm = useForm({
    scope: props.scope.type || 'user',
    club_id: props.scope.club_id,
    team_id: props.scope.team_id,
    event_id: props.scope.event_id,
    folder_id: props.scope.folder_id,
})

const uploadForm = useForm({
    scope: scopeForm.scope,
    club_id: scopeForm.club_id,
    team_id: scopeForm.team_id,
    event_id: scopeForm.event_id,
    folder_id: props.currentFolder?.id || null,
    file: null,
})

const folderForm = useForm({
    scope: scopeForm.scope,
    club_id: scopeForm.club_id,
    team_id: scopeForm.team_id,
    event_id: scopeForm.event_id,
    parent_id: props.currentFolder?.id || null,
    name: '',
})

const shareForm = useForm({
    target_type: 'user',
    target_id: '',
    email: '',
})

const renameForm = useForm({
    name: '',
    display_name: '',
})
const deleteConfirmationInputRef = ref(null)
const renameFolderInputRef = ref(null)
const renameFileInputRef = ref(null)
const shareTargetSelectRef = ref(null)
const shareEmailInputRef = ref(null)

const scopeOptions = [
    { value: 'user', label: 'Meine Dateien' },
    { value: 'team', label: 'Team' },
    { value: 'club', label: 'Verein' },
    { value: 'event', label: 'Event' },
]

const fileSortOptions = [
    { value: 'name-asc', label: 'Name (A-Z)' },
    { value: 'name-desc', label: 'Name (Z-A)' },
    { value: 'newest', label: 'Neueste zuerst' },
    { value: 'oldest', label: 'Aelteste zuerst' },
    { value: 'size-asc', label: 'Groesse aufsteigend' },
    { value: 'size-desc', label: 'Groesse absteigend' },
]

const folderSortOptions = [
    { value: 'name-asc', label: 'Name (A-Z)' },
    { value: 'name-desc', label: 'Name (Z-A)' },
    { value: 'newest', label: 'Neueste zuerst' },
    { value: 'oldest', label: 'Aelteste zuerst' },
]

const activeFolders = computed(() => {
    return folderItems.value
})
const activeFiles = computed(() => {
    return fileItems.value
})
const storageUsage = computed(() => page.props.auth?.user?.storage_usage || null)
const isStorageFull = computed(() => storageUsage.value?.is_full === true)
const uploadFileName = computed(() => uploadForm.file?.name || 'Datei wählen')
const deleteTargetName = computed(() => {
    if (!deleteTarget.value) return ''

    return deleteType.value === 'folder' ? deleteTarget.value.name : fileName(deleteTarget.value)
})
const canConfirmDelete = computed(() => deleteConfirmation.value.trim().toLowerCase() === 'löschen')
const shareTargets = computed(() => {
    const query = friendSearch.value.trim().toLowerCase()

    if (!query) return props.users

    return props.users.filter((user) => `${user.name || ''} ${user.email || ''}`.toLowerCase().includes(query))
})
const fileItems = computed(() => {
    if (Array.isArray(props.files)) return props.files
    if (!props.files || !Array.isArray(props.files.data)) return []

    return props.files.data
})
const folderItems = computed(() => {
    if (Array.isArray(props.folders)) return props.folders
    if (!props.folders || !Array.isArray(props.folders.data)) return []

    return props.folders.data
})
const filesPagination = computed(() => {
    return !Array.isArray(props.files) && props.files && typeof props.files === 'object' ? props.files : null
})
const foldersPagination = computed(() => {
    return !Array.isArray(props.folders) && props.folders && typeof props.folders === 'object' ? props.folders : null
})
const totalFiles = computed(() => filesPagination.value?.total || activeFiles.value.length)
const totalFolders = computed(() => foldersPagination.value?.total || activeFolders.value.length)
const currentFilesPage = computed(() => Number(filesPagination.value?.current_page || 1))
const currentFoldersPage = computed(() => Number(foldersPagination.value?.current_page || 1))
const lastFilesPage = computed(() => Number(filesPagination.value?.last_page || 1))
const lastFoldersPage = computed(() => Number(foldersPagination.value?.last_page || 1))
const fileRangeStart = computed(() => {
    if (!activeFiles.value.length) return 0

    return (currentFilesPage.value - 1) * filesPerPage.value + 1
})
const fileRangeEnd = computed(() => {
    if (!activeFiles.value.length) return 0

    return Math.min(currentFilesPage.value * filesPerPage.value, totalFiles.value)
})
const folderRangeStart = computed(() => {
    if (!activeFolders.value.length) return 0

    return (currentFoldersPage.value - 1) * foldersPerPage.value + 1
})
const folderRangeEnd = computed(() => {
    if (!activeFolders.value.length) return 0

    return Math.min(currentFoldersPage.value * foldersPerPage.value, totalFolders.value)
})
const hasActiveSearch = computed(() => fileSearch.value.trim() !== '')
const emptyStateText = computed(() => {
    if (!hasActiveSearch.value) {
        return 'Dieser Ordner ist leer.'
    }

    return `Keine Treffer zu "${fileSearch.value.trim()}"`
})
const filterStatusText = computed(() => {
    if (isFiltering.value) {
        return 'Liste wird aktualisiert...'
    }

    if (lastFilesPage.value > 1 || lastFoldersPage.value > 1) {
        return `Dateiseite ${currentFilesPage.value} / ${lastFilesPage.value}, Ordnerseite ${currentFoldersPage.value} / ${lastFoldersPage.value}`
    }

    return hasActiveSearch.value ? `Ergebnis: ${totalFiles.value} Dateien, ${totalFolders.value} Ordner` : 'Aktuelle Ansicht'
})

const syncForms = () => {
    ;[uploadForm, folderForm].forEach((form) => {
        form.scope = scopeForm.scope
        form.club_id = scopeForm.club_id
        form.team_id = scopeForm.team_id
        form.event_id = scopeForm.event_id
    })
}

const scopePayload = (folderId = null) => ({
    scope: scopeForm.scope,
    club_id: scopeForm.club_id,
    team_id: scopeForm.team_id,
    event_id: scopeForm.event_id,
    folder_id: folderId || undefined,
    search: fileSearch.value,
    folder_sort: folderSort.value,
    file_sort: fileSort.value,
    per_page: filesPerPage.value,
    folders_per_page: foldersPerPage.value,
    files_page: currentFilesPage.value,
    folders_page: currentFoldersPage.value,
})

const visitFileManager = (payload = {}, routerOptions = {}) => {
    const { onStart, onFinish, ...rest } = routerOptions

    router.get(route('auth.files.index'), payload, {
        preserveState: false,
        preserveScroll: true,
        ...rest,
        onStart: () => {
            isFiltering.value = true
            onStart?.()
        },
        onFinish: () => {
            isFiltering.value = false
            onFinish?.()
        },
    })
}

const syncFromServer = () => {
    scopeForm.scope = props.scope?.type || 'user'
    scopeForm.club_id = props.scope?.club_id || null
    scopeForm.team_id = props.scope?.team_id || null
    scopeForm.event_id = props.scope?.event_id || null
    scopeForm.folder_id = props.scope?.folder_id || null
    fileSearch.value = props.search || ''
    fileSort.value = props.file_sort || 'name-asc'
    folderSort.value = props.folder_sort || 'name-asc'
    filesPerPage.value = normalizePageSize(props.per_page)
    foldersPerPage.value = normalizePageSize(props.folders_per_page)
    syncForms()
}

syncFromServer()

const changeScope = () => {
    if (scopeForm.scope !== 'club') scopeForm.club_id = null
    if (scopeForm.scope !== 'team') scopeForm.team_id = null
    if (scopeForm.scope !== 'event') scopeForm.event_id = null
    scopeForm.folder_id = null
    syncForms()
    const payload = scopePayload()
    payload.files_page = 1
    payload.folders_page = 1
    visitFileManager(payload)
}

const openFolder = (folder) => {
    scopeForm.folder_id = folder?.id || null
    const payload = scopePayload(folder?.id)
    payload.files_page = 1
    payload.folders_page = 1
    visitFileManager(payload)
}

const submitUpload = () => {
    syncForms()
    uploadForm.folder_id = props.currentFolder?.id || null
    uploadForm.post(route('auth.files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('file')
            if (fileInput.value) {
                fileInput.value.value = ''
            }
        },
    })
}

const selectUploadFile = () => {
    fileInput.value?.click()
}

const setUploadFile = (event) => {
    uploadForm.file = event.target.files?.[0] || null
}

const createFolder = () => {
    syncForms()
    folderForm.parent_id = props.currentFolder?.id || null
    folderForm.post(route('auth.folders.store'), {
        preserveScroll: true,
        onSuccess: () => folderForm.reset('name'),
    })
}

const openDeleteModal = (item, type = 'file') => {
    deleteTarget.value = item
    deleteType.value = type
    deleteConfirmation.value = ''
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    if (deleteProcessing.value) return

    showDeleteModal.value = false
    deleteTarget.value = null
    deleteType.value = 'file'
    deleteConfirmation.value = ''
}

const deleteFile = (file) => openDeleteModal(file, 'file')
const confirmDeleteFolder = (folder) => {
    openDeleteModal(folder, 'folder')
}

const deleteConfirmed = () => {
    if (!deleteTarget.value || !canConfirmDelete.value) return

    const deleteRoute = deleteType.value === 'folder'
        ? route('auth.folders.destroy', deleteTarget.value.id)
        : route('auth.files.destroy', deleteTarget.value.id)

    deleteProcessing.value = true
    router.delete(deleteRoute, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            closeDeleteModal()
        },
    })
}

const openRename = (item, type = 'file') => {
    renameTarget.value = item
    renameType.value = type
    renameForm.name = type === 'folder' ? item.name : ''
    renameForm.display_name = type === 'file' ? fileName(item) : ''
    showRenameModal.value = true
}

const submitRename = () => {
    if (!renameTarget.value) return

    const renameRoute = renameType.value === 'folder'
        ? route('auth.folders.update', renameTarget.value.id)
        : route('auth.files.update', renameTarget.value.id)

    renameForm.put(renameRoute, {
        preserveScroll: true,
        onSuccess: () => {
            showRenameModal.value = false
            renameTarget.value = null
            renameForm.reset('name', 'display_name')
        },
    })
}

const openShare = (item, type = 'file') => {
    itemToShare.value = item
    shareType.value = type
    shareForm.target_type = 'user'
    shareForm.target_id = props.users[0]?.id || ''
    shareForm.email = ''
    friendSearch.value = ''
    showShareModal.value = true
}

const shareItem = () => {
    if (!itemToShare.value) return

    const shareRoute = shareType.value === 'folder'
        ? route('auth.folders.share', itemToShare.value.id)
        : route('auth.files.share', itemToShare.value.id)

    shareForm.post(shareRoute, {
        preserveScroll: true,
        onSuccess: () => {
            showShareModal.value = false
            shareForm.reset('email')
        },
    })
}

const formatSize = (size) => {
    if (!size) return ''
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`
    return `${(size / 1024 / 1024).toFixed(1)} MB`
}

const formatStorage = (size) => {
    const value = Number(size || 0)

    if (value < 1024) return `${value} B`
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}

const fileName = (file) => file.display_name || file.path.split('/').pop()
const contextLabel = (file) => file.event?.title || file.team?.name || file.club?.name || 'Privat'

const clearSearch = () => {
    fileSearch.value = ''
    scheduleFilterRefresh(true)
}

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
    }
})

const scheduleFilterRefresh = (immediate = false) => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
        searchDebounceTimer = null
    }

    if (immediate) {
        applyFilters()
        return
    }

    searchDebounceTimer = setTimeout(() => {
        applyFilters()
    }, SEARCH_DEBOUNCE_MS)
}

const applyFilters = () => {
    const payload = scopePayload()
    payload.files_page = 1
    payload.folders_page = 1

    visitFileManager(payload)
}
watch(fileSearch, () => {
    scheduleFilterRefresh()
})

const goToFilesPage = (page) => {
    if (!filesPagination.value || page < 1 || page > lastFilesPage.value) return

    const payload = scopePayload()
    payload.files_page = page

    visitFileManager(payload)
}

const goToFoldersPage = (page) => {
    if (!foldersPagination.value || page < 1 || page > lastFoldersPage.value) return

    const payload = scopePayload()
    payload.folders_page = page

    visitFileManager(payload)
}

watch(() => props.search, (value) => {
    if (value !== fileSearch.value) {
        fileSearch.value = value || ''
    }
})

watch(() => props.scope, syncFromServer, { deep: true })
watch(() => props.file_sort, (value) => {
    if (value !== fileSort.value) {
        fileSort.value = value || 'name-asc'
    }
})
watch(() => props.folder_sort, (value) => {
    if (value !== folderSort.value) {
        folderSort.value = value || 'name-asc'
    }
})
watch(() => props.per_page, (value) => {
    const next = normalizePageSize(value)
    if (next !== filesPerPage.value) {
        filesPerPage.value = next
    }
})
watch(() => props.folders_per_page, (value) => {
    const next = normalizePageSize(value)
    if (next !== foldersPerPage.value) {
        foldersPerPage.value = next
    }
})

watch(showDeleteModal, async (show) => {
    if (!show) return

    await nextTick()
    deleteConfirmationInputRef.value?.focus()
})

watch(showRenameModal, async (show) => {
    if (!show) return

    await nextTick()

    if (renameType.value === 'folder') {
        renameFolderInputRef.value?.focus()
        return
    }

    renameFileInputRef.value?.focus()
})

watch(showShareModal, async (show) => {
    if (!show) return

    await nextTick()

    if (shareForm.target_type === 'user') {
        shareTargetSelectRef.value?.focus()
        return
    }

    shareEmailInputRef.value?.focus()
})
</script>

<template>
    <AppLayout title="Dateien">
        <Head title="Dateien" />

        <div class="space-y-3">
            <div class="rounded-lg border border-border bg-card p-3">
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm">
                        <span class="mb-1 block text-secondary">Bereich</span>
                        <select v-model="scopeForm.scope" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'club'" class="text-sm">
                        <span class="mb-1 block text-secondary">Verein</span>
                        <select v-model="scopeForm.club_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'team'" class="text-sm">
                        <span class="mb-1 block text-secondary">Team</span>
                        <select v-model="scopeForm.team_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'event'" class="text-sm">
                        <span class="mb-1 block text-secondary">Event</span>
                        <select v-model="scopeForm.event_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_260px] 2xl:grid-cols-[minmax(0,1fr)_300px]">
                <section class="min-w-0 rounded-lg border border-border bg-card">
                    <div class="flex flex-col gap-2 border-b border-border p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 class="text-lg font-semibold text-primary">{{ currentFolder?.name || 'Dateimanager' }}</h1>
                            <p class="text-sm text-secondary">{{ totalFolders }} Ordner · {{ totalFiles }} Dateien</p>
                        </div>
                        <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-2 xl:flex xl:flex-wrap xl:items-center">
                            <div class="relative sm:col-span-2 xl:col-span-1">
                                <input
                                    v-model="fileSearch"
                                    class="h-9 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:w-44"
                                    placeholder="Suchen..."
                                    :disabled="isFiltering"
                                    aria-label="Dateien und Ordner durchsuchen"
                                    @keyup.enter="scheduleFilterRefresh(true)"
                                />
                                <button v-if="fileSearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-secondary hover:text-primary" type="button" @click="clearSearch" aria-label="Suche löschen">x</button>
                            </div>
                            <select
                                v-model="filesPerPage"
                                class="h-9 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:w-36"
                                aria-label="Dateien pro Seite"
                                :disabled="isFiltering"
                                @change="applyFilters"
                            >
                                <option v-for="pageSize in pageSizeOptions" :key="`page-size-${pageSize}`" :value="pageSize">
                                    Dateien: {{ pageSize }} / Seite
                                </option>
                            </select>
                            <select
                                v-model="foldersPerPage"
                                class="h-9 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:w-36"
                                aria-label="Ordner pro Seite"
                                :disabled="isFiltering"
                                @change="applyFilters"
                            >
                                <option v-for="pageSize in pageSizeOptions" :key="`folder-page-size-${pageSize}`" :value="pageSize">
                                    Ordner: {{ pageSize }} / Seite
                                </option>
                            </select>
                            <select
                                v-model="folderSort"
                                class="h-9 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:w-44"
                                aria-label="Ordner sortieren"
                                :disabled="isFiltering"
                                @change="applyFilters"
                            >
                                <option v-for="option in folderSortOptions" :key="`folder-${option.value}`" :value="option.value">
                                    Ordner: {{ option.label }}
                                </option>
                            </select>
                            <select
                                v-model="fileSort"
                                class="h-9 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:w-44"
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
                            type="button"
                            v-for="folder in activeFolders"
                            :key="folder.id"
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
                                    @click="goToFoldersPage(currentFoldersPage - 1)"
                                    type="button"
                                >
                                    Zurück
                                </button>
                                <button
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                                    :disabled="currentFoldersPage >= lastFoldersPage"
                                    @click="goToFoldersPage(currentFoldersPage + 1)"
                                    type="button"
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
                                    @click="goToFilesPage(currentFilesPage - 1)"
                                    type="button"
                                >
                                    Zurück
                                </button>
                                <button
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                                    :disabled="currentFilesPage >= lastFilesPage"
                                    @click="goToFilesPage(currentFilesPage + 1)"
                                    type="button"
                                >
                                    Weiter
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="min-w-0 space-y-3">
                    <section v-if="storageUsage" class="rounded-lg border border-border bg-card p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Speicher</h2>
                                <p class="mt-0.5 text-base font-bold text-primary">{{ formatStorage(storageUsage.remaining_bytes) }} frei</p>
                            </div>
                            <span class="shrink-0 rounded-full border border-border px-2 py-1 text-xs font-semibold text-primary">
                                {{ storageUsage.plan_name }}
                            </span>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-inputBg">
                            <div
                                class="h-2 rounded-full bg-buttonPrimary"
                                :style="{ width: `${storageUsage.used_percent}%` }"
                            ></div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-secondary">
                            <span>{{ formatStorage(storageUsage.used_bytes) }} genutzt</span>
                            <span>{{ storageUsage.limit_gb }} GB gesamt</span>
                        </div>
                    </section>

                    <form class="rounded-lg border border-border bg-card p-3" @submit.prevent="submitUpload">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Upload</h2>
                            <span class="truncate text-xs text-secondary">{{ currentFolder?.name || 'Hauptebene' }}</span>
                        </div>
                        <input ref="fileInput" class="hidden" type="file" @change="setUploadFile">
                        <button
                            type="button"
                            class="mt-3 flex h-10 w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-border bg-inputBg px-3 text-left text-sm text-primary hover:bg-muted"
                            @click="selectUploadFile"
                            aria-label="Datei auswählen"
                        >
                            <span class="truncate">{{ uploadFileName }}</span>
                            <i class="las la-paperclip text-lg text-secondary"></i>
                        </button>
                        <p v-if="isStorageFull" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs font-semibold text-warning">
                            Dein Speicher ist voll. Bitte lösche Dateien oder upgrade.
                        </p>
                        <p v-if="uploadForm.errors.file || uploadForm.errors.general" class="mt-2 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">
                            {{ uploadForm.errors.file || uploadForm.errors.general }}
                        </p>
                        <button :disabled="uploadForm.processing || !uploadForm.file || isStorageFull" class="mt-2 h-10 w-full rounded-lg bg-buttonPrimary px-4 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                            Hochladen
                        </button>
                    </form>

                    <form class="rounded-lg border border-border bg-card p-3" @submit.prevent="createFolder">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Neuer Ordner</h2>
                        <input v-model="folderForm.name" class="mt-3 h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary" placeholder="Ordnername">
                        <button :disabled="folderForm.processing || !folderForm.name.trim()" class="mt-2 h-10 w-full rounded-lg bg-buttonPrimary px-4 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                            Erstellen
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </AppLayout>

    <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
        <div class="space-y-5 text-primary">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-error">Endgültig löschen</p>
                <h2 class="mt-1 text-lg font-bold">
                    {{ deleteType === 'folder' ? 'Ordner löschen' : 'Datei löschen' }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ deleteType === 'folder'
                        ? 'Der Ordner und seine Dateien werden entfernt.'
                        : 'Diese Datei wird entfernt.' }}
                </p>
            </div>

            <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-primary">
                <span class="font-semibold">{{ deleteTargetName }}</span>
            </div>

            <label class="block text-sm">
                <span class="mb-1 block text-secondary">Schreibe <span class="font-semibold text-primary">löschen</span>, um fortzufahren.</span>
            <input
                ref="deleteConfirmationInputRef"
                v-model="deleteConfirmation"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                autocomplete="off"
                placeholder="löschen"
                @keyup.enter="deleteConfirmed"
                >
            </label>

            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg border border-border px-4 py-2 text-primary hover:bg-inputBg" @click="closeDeleteModal">Abbrechen</button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canConfirmDelete || deleteProcessing"
                    @click="deleteConfirmed"
                >
                    {{ deleteProcessing ? 'Lösche...' : 'Endgültig löschen' }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showRenameModal" max-width="md" @close="showRenameModal = false">
        <form class="space-y-4 text-primary" @submit.prevent="submitRename">
            <h2 class="text-lg font-bold">{{ renameType === 'folder' ? 'Ordner' : 'Datei' }} umbenennen</h2>
            <input
                v-if="renameType === 'folder'"
                v-model="renameForm.name"
                ref="renameFolderInputRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Ordnername"
            >
            <input
                v-else
                v-model="renameForm.display_name"
                ref="renameFileInputRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Dateiname"
            >
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600" @click="showRenameModal = false">Abbrechen</button>
                <button
                    type="submit"
                    :disabled="renameForm.processing || (renameType === 'folder' ? !renameForm.name.trim() : !renameForm.display_name.trim())"
                    class="flex-1 rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary disabled:opacity-50"
                >
                    Speichern
                </button>
            </div>
        </form>
    </Modal>

    <Modal :show="showShareModal" max-width="md" @close="showShareModal = false">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">{{ shareType === 'folder' ? 'Ordner' : 'Datei' }} freigeben</h2>
            <select v-model="shareForm.target_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="shareForm.target_id = shareTargets[0]?.id || ''; shareForm.email = ''">
                <option value="user">Freund</option>
                <option v-if="shareType === 'file'" value="email">Externe E-Mail</option>
            </select>
            <input v-if="shareForm.target_type === 'user'" v-model="friendSearch" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Freund suchen">
            <select
                v-if="shareForm.target_type === 'user'"
                v-model="shareForm.target_id"
                ref="shareTargetSelectRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
            >
                <option value="">Auswählen</option>
                <option v-for="target in shareTargets" :key="target.id" :value="target.id">
                    {{ target.name }}{{ target.email ? ` · ${target.email}` : '' }}
                </option>
            </select>
            <input
                v-else
                ref="shareEmailInputRef"
                v-model="shareForm.email"
                type="email"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="name@example.com"
            >
            <button
                type="button"
                :disabled="shareForm.processing || (shareForm.target_type === 'user' ? !shareForm.target_id : !shareForm.email)"
                class="w-full rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary disabled:opacity-50"
                @click="shareItem"
            >
                Freigeben
            </button>
        </div>
    </Modal>
</template>
