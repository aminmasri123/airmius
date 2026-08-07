<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

const props = defineProps({
    files: { type: [Array, Object], default: () => [] },
    folders: { type: [Array, Object], default: () => [] },
    currentFolder: { type: Object, default: null },
    scope: { type: Object, default: () => ({ type: 'user' }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name-asc' },
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
const itemsPerPage = ref(normalizePageSize(props.per_page || props.folders_per_page))
const itemSort = ref(props.sort || props.file_sort || props.folder_sort || 'name-asc')
const renameTarget = ref(null)
const renameType = ref('file')
const page = usePage()
const isFiltering = ref(false)
const showMobileFilters = ref(false)
const showMobileActions = ref(false)
const showUploadModal = ref(false)
const uploadTarget = ref('user')
const uploadClubId = ref(null)
const uploadTeamId = ref(null)
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
    { value: 'user', label: tx('files.scope.user') },
    { value: 'team', label: tx('files.scope.team') },
    { value: 'club', label: tx('files.scope.club') },
    { value: 'event', label: tx('files.scope.event') },
]

const itemSortOptions = [
    { value: 'name-asc', label: tx('files.sort.name_asc') },
    { value: 'name-desc', label: tx('files.sort.name_desc') },
    { value: 'newest', label: tx('files.sort.newest') },
    { value: 'oldest', label: tx('files.sort.oldest') },
]

const activeFolders = computed(() => {
    return folderItems.value
})
const activeFiles = computed(() => {
    return fileItems.value
})
const storageUsage = computed(() => page.props.auth?.user?.storage_usage || null)
const isStorageFull = computed(() => storageUsage.value?.is_full === true)
const uploadFileName = computed(() => uploadForm.file?.name || tx('files.choose_file'))
const uploadTargetReady = computed(() => {
    if (uploadTarget.value === 'club') return Boolean(uploadClubId.value)
    if (uploadTarget.value === 'team') return Boolean(uploadTeamId.value)

    return true
})
const uploadTargetLabel = computed(() => tx(`files.scope.${uploadTarget.value}`))
const deleteTargetName = computed(() => {
    if (!deleteTarget.value) return ''

    return deleteType.value === 'folder' ? deleteTarget.value.name : fileName(deleteTarget.value)
})
const canConfirmDelete = computed(() => deleteConfirmation.value.trim().toLowerCase() === tx('files.delete_word').toLowerCase())
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

    return (currentFilesPage.value - 1) * itemsPerPage.value + 1
})
const fileRangeEnd = computed(() => {
    if (!activeFiles.value.length) return 0

    return Math.min(currentFilesPage.value * itemsPerPage.value, totalFiles.value)
})
const folderRangeStart = computed(() => {
    if (!activeFolders.value.length) return 0

    return (currentFoldersPage.value - 1) * itemsPerPage.value + 1
})
const folderRangeEnd = computed(() => {
    if (!activeFolders.value.length) return 0

    return Math.min(currentFoldersPage.value * itemsPerPage.value, totalFolders.value)
})
const hasActiveSearch = computed(() => fileSearch.value.trim() !== '')
const emptyStateText = computed(() => {
    if (!hasActiveSearch.value) {
        return tx('files.empty_folder')
    }

    return tx('files.no_results', { query: fileSearch.value.trim() })
})
const filterStatusText = computed(() => {
    if (isFiltering.value) {
        return tx('files.refreshing')
    }

    if (lastFilesPage.value > 1 || lastFoldersPage.value > 1) {
        return tx('files.page_status', { foldersPage: currentFoldersPage.value, foldersLast: lastFoldersPage.value, filesPage: currentFilesPage.value, filesLast: lastFilesPage.value })
    }

    return hasActiveSearch.value ? tx('files.result_status', { folders: totalFolders.value, files: totalFiles.value }) : ''
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
    sort: itemSort.value,
    folder_sort: itemSort.value,
    file_sort: itemSort.value,
    per_page: itemsPerPage.value,
    folders_per_page: itemsPerPage.value,
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
    itemSort.value = props.sort || props.file_sort || props.folder_sort || 'name-asc'
    itemsPerPage.value = normalizePageSize(props.per_page || props.folders_per_page)
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

const openUploadModal = () => {
    uploadTarget.value = 'user'
    uploadClubId.value = null
    uploadTeamId.value = null
    uploadForm.reset('file')
    uploadForm.clearErrors()
    showUploadModal.value = true
}

const closeUploadModal = () => {
    if (uploadForm.processing) return

    showUploadModal.value = false
}

const selectUploadTarget = (target) => {
    uploadTarget.value = target
    if (target !== 'club') uploadClubId.value = null
    if (target !== 'team') uploadTeamId.value = null
}

const submitUpload = () => {
    uploadForm.scope = uploadTarget.value
    uploadForm.club_id = uploadTarget.value === 'club' ? uploadClubId.value : null
    uploadForm.team_id = uploadTarget.value === 'team' ? uploadTeamId.value : null
    uploadForm.event_id = null
    uploadForm.folder_id = uploadTarget.value === scopeForm.scope
        && (uploadTarget.value !== 'club' || uploadClubId.value === scopeForm.club_id)
        && (uploadTarget.value !== 'team' || uploadTeamId.value === scopeForm.team_id)
        ? props.currentFolder?.id || null
        : null
    uploadForm.post(route('auth.files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('file')
            showUploadModal.value = false
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
        onSuccess: () => {
            folderForm.reset('name')
            showMobileActions.value = false
        },
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
watch(() => [props.sort, props.file_sort, props.folder_sort], ([sort, fileSort, folderSort]) => {
    const next = sort || fileSort || folderSort || 'name-asc'
    if (next !== itemSort.value) {
        itemSort.value = next
    }
})
watch(() => [props.per_page, props.folders_per_page], ([perPage, foldersPerPage]) => {
    const next = normalizePageSize(perPage || foldersPerPage)
    if (next !== itemsPerPage.value) {
        itemsPerPage.value = next
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
    <AppLayout :title="tx('files.page_title')">
        <Head :title="tx('files.page_title')" />
        <input ref="fileInput" class="hidden" type="file" @change="setUploadFile">

        <div class="space-y-3">
            <div class="rounded-lg border border-border bg-card p-2 sm:p-3">
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm">
                        <span class="mb-1 block text-secondary">{{ tx('files.scope_label') }}</span>
                        <select v-model="scopeForm.scope" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'club'" class="text-sm">
                        <span class="mb-1 block text-secondary">Verein</span>
                        <select v-model="scopeForm.club_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">{{ tx('files.select') }}</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'team'" class="text-sm">
                        <span class="mb-1 block text-secondary">Team</span>
                        <select v-model="scopeForm.team_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">{{ tx('files.select') }}</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'event'" class="text-sm">
                        <span class="mb-1 block text-secondary">Event</span>
                        <select v-model="scopeForm.event_id" class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-primary" @change="changeScope">
                            <option :value="null">{{ tx('files.select') }}</option>
                            <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_260px] 2xl:grid-cols-[minmax(0,1fr)_300px]">
                <section class="order-2 min-w-0 rounded-lg border border-border bg-card md:order-1">
                    <div class="flex flex-col gap-3 border-b border-border p-3">
                        <div class="flex items-start justify-between gap-3">
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
                                    class="hidden h-10 items-center gap-2 rounded-lg bg-buttonPrimary px-3 text-sm font-semibold text-buttonTextPrimary shadow-sm hover:opacity-90 disabled:opacity-50 md:inline-flex"
                                    :disabled="isFiltering || isStorageFull"
                                    @click="openUploadModal"
                                >
                                    <i class="las la-cloud-upload-alt text-base"></i>
                                    {{ tx('files.upload') }}
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-lg border border-border text-lg text-primary hover:bg-inputBg md:hidden"
                                    :class="{ 'bg-inputBg': showMobileFilters }"
                                    :aria-expanded="showMobileFilters"
                                    :aria-label="tx('files.search_sort')"
                                    @click="showMobileFilters = !showMobileFilters; showMobileActions = false"
                                >
                                    <i class="las la-sliders-h"></i>
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-lg border border-border text-lg text-primary hover:bg-inputBg md:hidden"
                                    :aria-expanded="showMobileActions"
                                    :aria-label="tx('files.create_folder')"
                                    @click="showMobileActions = !showMobileActions; showMobileFilters = false"
                                >
                                    <i :class="showMobileActions ? 'las la-times' : 'las la-folder-plus'"></i>
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-lg bg-buttonPrimary text-lg text-buttonTextPrimary shadow-sm disabled:opacity-50 md:hidden"
                                    :disabled="isFiltering || isStorageFull"
                                    :aria-label="tx('files.upload')"
                                    @click="openUploadModal"
                                >
                                    <i class="las la-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="showMobileActions" class="grid gap-2 rounded-lg border border-border bg-inputBg/40 p-2 md:hidden">
                            <form class="rounded-lg border border-border bg-card p-3" @submit.prevent="createFolder">
                                <h2 class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('files.create_folder') }}</h2>
                                <input v-model="folderForm.name" class="mt-3 h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary" :placeholder="tx('files.folder_name')">
                                <AppLoadingState v-if="folderForm.processing" class="mt-2" :label="tx('files.folder_creating')" inline />
                                <AppButton
                                    type="submit"
                                    class="mt-2"
                                    block
                                    :loading="folderForm.processing"
                                    :disabled="folderForm.processing || !folderForm.name.trim()"
                                >
                                    {{ folderForm.processing ? tx('files.created') : tx('files.create') }}
                                </AppButton>
                            </form>
                        </div>

                        <div
                            :class="[
                                showMobileFilters ? 'grid' : 'hidden',
                                'w-full grid-cols-1 gap-2 md:grid md:grid-cols-2 xl:flex xl:flex-wrap xl:items-center xl:justify-end',
                            ]"
                        >
                            <div class="relative sm:col-span-2 xl:col-span-1">
                                <input
                                    v-model="fileSearch"
                                    class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                                    :placeholder="tx('files.search_placeholder')"
                                    :disabled="isFiltering"
                                    :aria-label="tx('files.search_aria')"
                                    @keyup.enter="scheduleFilterRefresh(true)"
                                />
                                <button v-if="fileSearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-secondary hover:text-primary" type="button" @click="clearSearch" :aria-label="tx('files.search_clear')">x</button>
                            </div>
                            <select
                                v-model="itemsPerPage"
                                class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-36"
                                :aria-label="tx('files.per_page')"
                                :disabled="isFiltering"
                                @change="applyFilters"
                            >
                                <option v-for="pageSize in pageSizeOptions" :key="`page-size-${pageSize}`" :value="pageSize">
                                    {{ tx('files.per_page_option', { count: pageSize }) }}
                                </option>
                            </select>
                            <select
                                v-model="itemSort"
                                class="h-10 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary xl:h-9 xl:w-44"
                                :aria-label="tx('files.sort_aria')"
                                :disabled="isFiltering"
                                @change="applyFilters"
                            >
                                <option v-for="option in itemSortOptions" :key="`sort-${option.value}`" :value="option.value">
                                    {{ tx('files.sort_option', { value: option.label }) }}
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
                            <p v-if="filterStatusText" class="text-xs text-secondary" role="status" aria-live="polite">{{ filterStatusText }}</p>
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
                                <i class="las la-folder text-3xl text-yellow-500"></i>
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
                                :title="tx('files.share')"
                                :aria-label="tx('files.share_folder')"
                            >
                                <i class="las la-share-alt"></i>
                            </button>
                            <button
                                type="button"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-secondary hover:bg-card sm:h-9 sm:w-9 sm:opacity-0 sm:group-hover:opacity-100"
                                :disabled="isFiltering"
                                @click.stop="openRename(folder, 'folder')"
                                :title="tx('files.rename')"
                                :aria-label="tx('files.rename_folder')"
                            >
                                <i class="las la-pen"></i>
                            </button>
                            <button
                                type="button"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-secondary hover:bg-card sm:h-9 sm:w-9 sm:opacity-0 sm:group-hover:opacity-100"
                                :disabled="isFiltering"
                                @click.stop="confirmDeleteFolder(folder)"
                                :title="tx('files.delete')"
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
                                    :title="tx('files.share')"
                                    :aria-label="tx('files.share_file')"
                                >
                                    <i class="las la-share-alt"></i>
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9"
                                    :disabled="isFiltering"
                                    @click="openRename(file)"
                                    title="Umbenennen"
                                    :aria-label="tx('files.rename_file')"
                                >
                                    <i class="las la-pen"></i>
                                </button>
                                <a :href="route('auth.files.download', file.id)" class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9" :title="tx('files.download')" :aria-label="tx('files.download_file')">
                                    <i class="las la-download"></i>
                                </a>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-lg text-secondary hover:bg-inputBg sm:h-9 sm:w-9"
                                    :disabled="isFiltering"
                                    @click="deleteFile(file)"
                                    :title="tx('files.delete')"
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
                                    @click="goToFoldersPage(currentFoldersPage - 1)"
                                    type="button"
                                >
                                    {{ tx('files.back') }}
                                </button>
                                <button
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                                    :disabled="currentFoldersPage >= lastFoldersPage"
                                    @click="goToFoldersPage(currentFoldersPage + 1)"
                                    type="button"
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
                                    @click="goToFilesPage(currentFilesPage - 1)"
                                    type="button"
                                >
                                    {{ tx('files.back') }}
                                </button>
                                <button
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary disabled:opacity-50"
                                    :disabled="currentFilesPage >= lastFilesPage"
                                    @click="goToFilesPage(currentFilesPage + 1)"
                                    type="button"
                                >
                                    {{ tx('files.next') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="order-1 min-w-0 space-y-3 md:order-2">
                    <section v-if="storageUsage" class="hidden rounded-lg border border-border bg-card p-3 md:block">
                        <div class="flex items-start justify-between gap-3 md:items-center">
                            <div class="min-w-0">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tx('files.storage') }}</h2>
                        <p class="mt-0.5 text-base font-bold text-primary">{{ formatStorage(storageUsage.remaining_bytes) }} {{ tx('files.free') }}</p>
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
                        <div class="mt-2 text-xs text-secondary md:flex md:items-center md:justify-between">
                            <span class="md:hidden">{{ tx('files.used_of_total', { used: formatStorage(storageUsage.used_bytes), limit: storageUsage.limit_gb }) }}</span>
                            <span class="hidden md:inline">{{ formatStorage(storageUsage.used_bytes) }} {{ tx('files.used') }}</span>
                            <span class="hidden md:inline">{{ storageUsage.limit_gb }} GB {{ tx('files.total') }}</span>
                        </div>
                    </section>

                    <section class="hidden rounded-lg border border-border bg-card p-4 md:block">
                        <div class="flex items-start gap-3">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-buttonPrimary/10 text-buttonPrimary">
                                <i class="las la-cloud-upload-alt text-xl"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-primary">{{ tx('files.upload') }}</h2>
                                <p class="mt-1 text-xs leading-5 text-secondary">{{ tx('files.scope_label') }}: {{ uploadTargetLabel }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-4 flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-3 text-sm font-semibold text-buttonTextPrimary shadow-sm hover:opacity-90 disabled:opacity-50"
                            :disabled="isStorageFull"
                            @click="openUploadModal"
                        >
                            <i class="las la-plus"></i>
                            {{ tx('files.upload') }}
                        </button>
                        <p v-if="isStorageFull" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs font-semibold text-warning">
                            {{ tx('files.storage_full') }}
                        </p>
                    </section>

                    <form class="hidden rounded-lg border border-border bg-card p-3 md:block" @submit.prevent="createFolder">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tx('files.new_folder') }}</h2>
                        <input v-model="folderForm.name" class="mt-3 h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary md:h-10" :placeholder="tx('files.folder_name')">
                        <AppLoadingState v-if="folderForm.processing" class="mt-2" :label="tx('files.folder_creating')" inline />
                        <AppButton
                            type="submit"
                            class="mt-2"
                            block
                            :loading="folderForm.processing"
                            :disabled="folderForm.processing || !folderForm.name.trim()"
                        >
                            {{ folderForm.processing ? tx('files.created') : tx('files.create') }}
                        </AppButton>
                    </form>
                </aside>
            </div>
        </div>

        <div
            v-if="storageUsage"
            class="fixed inset-x-3 bottom-3 z-40 rounded-lg border border-border bg-card/95 p-2 shadow-[0_-10px_24px_rgba(0,0,0,0.18)] backdrop-blur md:hidden"
        >
            <div class="flex items-center justify-between gap-3 text-xs">
                <span class="min-w-0 truncate font-semibold text-primary">
                    {{ tx('files.storage_mobile', { used: formatStorage(storageUsage.used_bytes), limit: storageUsage.limit_gb }) }}
                </span>
                <span class="shrink-0 rounded-full border border-border px-2 py-0.5 text-[11px] font-semibold text-secondary">
                    {{ formatStorage(storageUsage.remaining_bytes) }} {{ tx('files.free') }}
                </span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-inputBg">
                <div
                    class="h-1.5 rounded-full bg-buttonPrimary"
                    :style="{ width: `${storageUsage.used_percent}%` }"
                ></div>
            </div>
        </div>
    </AppLayout>

    <Modal :show="showUploadModal" max-width="lg" @close="closeUploadModal">
        <form class="space-y-6 text-primary" @submit.prevent="submitUpload">
            <div class="flex items-start gap-3">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-buttonPrimary/10 text-buttonPrimary">
                    <i class="las la-cloud-upload-alt text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-buttonPrimary">{{ tx('files.upload') }}</p>
                    <h2 class="mt-1 text-xl font-bold">{{ tx('files.uploadTitle') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-secondary">{{ tx('files.scope_label') }}: {{ uploadTargetLabel }}</p>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-primary">{{ tx('files.scope_label') }}</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="target in [
                            { value: 'user', icon: 'las la-user', label: tx('files.scope.user') },
                            ...(clubs.length ? [{ value: 'club', icon: 'las la-building', label: tx('files.scope.club') }] : []),
                            ...(teams.length ? [{ value: 'team', icon: 'las la-users', label: tx('files.scope.team') }] : []),
                        ]"
                        :key="target.value"
                        type="button"
                        class="flex min-h-24 flex-col items-start justify-between rounded-2xl border p-3 text-left transition hover:border-buttonPrimary hover:bg-buttonPrimary/5"
                        :class="uploadTarget === target.value ? 'border-buttonPrimary bg-buttonPrimary/10 ring-2 ring-buttonPrimary/20' : 'border-border bg-inputBg/40'"
                        @click="selectUploadTarget(target.value)"
                    >
                        <i :class="[target.icon, uploadTarget === target.value ? 'text-buttonPrimary' : 'text-secondary']" class="text-xl"></i>
                        <span class="text-sm font-semibold">{{ target.label }}</span>
                    </button>
                </div>
            </div>

            <label v-if="uploadTarget === 'club'" class="block text-sm">
                <span class="mb-1 block text-secondary">{{ tx('files.scope.club') }}</span>
                <select v-model="uploadClubId" class="h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary">
                    <option :value="null">{{ tx('files.select') }}</option>
                    <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                </select>
            </label>

            <label v-if="uploadTarget === 'team'" class="block text-sm">
                <span class="mb-1 block text-secondary">{{ tx('files.scope.team') }}</span>
                <select v-model="uploadTeamId" class="h-11 w-full rounded-xl border border-border bg-inputBg px-3 text-primary">
                    <option :value="null">{{ tx('files.select') }}</option>
                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                </select>
            </label>

            <div class="rounded-2xl border border-dashed border-buttonPrimary/50 bg-buttonPrimary/5 p-4">
                <button
                    type="button"
                    class="flex min-h-20 w-full flex-col items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-center hover:border-buttonPrimary"
                    :disabled="uploadForm.processing"
                    @click="selectUploadFile"
                >
                    <i class="las la-paperclip text-2xl text-buttonPrimary"></i>
                    <span class="max-w-full truncate text-sm font-semibold">{{ uploadFileName }}</span>
                    <span class="text-xs text-secondary">{{ tx('files.choose_file') }}</span>
                </button>
                <p v-if="isStorageFull" class="mt-3 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2 text-xs font-semibold text-warning">
                    {{ tx('files.storage_full') }}
                </p>
                <p v-if="uploadForm.errors.file || uploadForm.errors.general" class="mt-3 rounded-xl border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">
                    {{ uploadForm.errors.file || uploadForm.errors.general }}
                </p>
                <AppLoadingState v-if="uploadForm.processing" class="mt-3" :label="tx('files.upload_running')" inline />
            </div>

            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-inputBg" :disabled="uploadForm.processing" @click="closeUploadModal">
                    {{ tx('files.cancel') }}
                </button>
                <AppButton
                    type="submit"
                    class="flex-1"
                    :loading="uploadForm.processing"
                    :disabled="uploadForm.processing || !uploadForm.file || !uploadTargetReady || isStorageFull"
                >
                    {{ uploadForm.processing ? tx('files.uploading') : tx('files.upload') }}
                </AppButton>
            </div>
        </form>
    </Modal>

    <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
        <div class="space-y-5 text-primary">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-error">{{ tx('files.delete_final') }}</p>
                <h2 class="mt-1 text-lg font-bold">
                    {{ deleteType === 'folder' ? tx('files.delete_folder') : tx('files.delete_file') }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ deleteType === 'folder' ? tx('files.delete_folder_message') : tx('files.delete_file_message') }}
                </p>
            </div>

            <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-primary">
                <span class="font-semibold">{{ deleteTargetName }}</span>
            </div>

            <label class="block text-sm">
                <span class="mb-1 block text-secondary">{{ tx('files.delete_confirmation_hint') }}</span>
            <input
                ref="deleteConfirmationInputRef"
                v-model="deleteConfirmation"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                autocomplete="off"
                :placeholder="tx('files.delete_word')"
                @keyup.enter="deleteConfirmed"
                >
            </label>

            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg border border-border px-4 py-2 text-primary hover:bg-inputBg" @click="closeDeleteModal">{{ tx('files.cancel') }}</button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canConfirmDelete || deleteProcessing"
                    @click="deleteConfirmed"
                >
                    {{ deleteProcessing ? tx('files.deleting') : tx('files.delete_final') }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showRenameModal" max-width="md" @close="showRenameModal = false">
        <form class="space-y-4 text-primary" @submit.prevent="submitRename">
            <h2 class="text-lg font-bold">{{ renameType === 'folder' ? tx('files.folder') : tx('files.file') }} {{ tx('files.rename') }}</h2>
            <input
                v-if="renameType === 'folder'"
                v-model="renameForm.name"
                ref="renameFolderInputRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                :placeholder="tx('files.folder_name')"
            >
            <input
                v-else
                v-model="renameForm.display_name"
                ref="renameFileInputRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                :placeholder="tx('files.file_name')"
            >
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600" @click="showRenameModal = false">{{ tx('files.cancel') }}</button>
                <button
                    type="submit"
                    :disabled="renameForm.processing || (renameType === 'folder' ? !renameForm.name.trim() : !renameForm.display_name.trim())"
                    class="flex-1 rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary disabled:opacity-50"
                >
                    {{ tx('files.save') }}
                </button>
            </div>
        </form>
    </Modal>

    <Modal :show="showShareModal" max-width="md" @close="showShareModal = false">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">{{ shareType === 'folder' ? tx('files.folder') : tx('files.file') }} {{ tx('files.share') }}</h2>
            <select v-model="shareForm.target_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="shareForm.target_id = shareTargets[0]?.id || ''; shareForm.email = ''">
                <option value="user">{{ tx('files.friend') }}</option>
                <option v-if="shareType === 'file'" value="email">{{ tx('files.external_email') }}</option>
            </select>
            <input v-if="shareForm.target_type === 'user'" v-model="friendSearch" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="tx('files.friend_search')">
            <select
                v-if="shareForm.target_type === 'user'"
                v-model="shareForm.target_id"
                ref="shareTargetSelectRef"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
            >
                <option value="">{{ tx('files.select') }}</option>
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
                {{ tx('files.share') }}
            </button>
        </div>
    </Modal>
</template>
