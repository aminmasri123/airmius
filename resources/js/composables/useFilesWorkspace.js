import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

export const useFilesWorkspace = (props) => {
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
    const showMobileFilters = ref(false)
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
        { value: 'oldest', label: 'Älteste zuerst' },
        { value: 'size-asc', label: 'Größe aufsteigend' },
        { value: 'size-desc', label: 'Größe absteigend' },
    ]
    
    const folderSortOptions = [
        { value: 'name-asc', label: 'Name (A-Z)' },
        { value: 'name-desc', label: 'Name (Z-A)' },
        { value: 'newest', label: 'Neueste zuerst' },
        { value: 'oldest', label: 'Älteste zuerst' },
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

    const setDeleteConfirmation = (value) => {
        deleteConfirmation.value = value
    }

    const setFriendSearch = (value) => {
        friendSearch.value = value
    }

    const setShowRenameModal = (value) => {
        showRenameModal.value = Boolean(value)
    }

    const setShowShareModal = (value) => {
        showShareModal.value = Boolean(value)
    }

    const resetShareTarget = () => {
        shareForm.target_id = shareTargets.value[0]?.id || ''
        shareForm.email = ''
    }

    const setFileInputElement = (element) => {
        fileInput.value = element
    }

    const setDeleteConfirmationInputElement = (element) => {
        deleteConfirmationInputRef.value = element
    }

    const setRenameFolderInputElement = (element) => {
        renameFolderInputRef.value = element
    }

    const setRenameFileInputElement = (element) => {
        renameFileInputRef.value = element
    }

    const setShareTargetSelectElement = (element) => {
        shareTargetSelectRef.value = element
    }

    const setShareEmailInputElement = (element) => {
        shareEmailInputRef.value = element
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

    return {
        showDeleteModal,
        showShareModal,
        showRenameModal,
        deleteTarget,
        deleteType,
        deleteConfirmation,
        deleteProcessing,
        fileInput,
        itemToShare,
        shareType,
        friendSearch,
        pageSizeOptions,
        normalizePageSize,
        fileSearch,
        filesPerPage,
        foldersPerPage,
        folderSort,
        fileSort,
        renameTarget,
        renameType,
        page,
        isFiltering,
        showMobileFilters,
        SEARCH_DEBOUNCE_MS,
        scopeForm,
        uploadForm,
        folderForm,
        shareForm,
        renameForm,
        deleteConfirmationInputRef,
        renameFolderInputRef,
        renameFileInputRef,
        shareTargetSelectRef,
        shareEmailInputRef,
        scopeOptions,
        fileSortOptions,
        folderSortOptions,
        activeFolders,
        activeFiles,
        storageUsage,
        isStorageFull,
        uploadFileName,
        deleteTargetName,
        canConfirmDelete,
        shareTargets,
        fileItems,
        folderItems,
        filesPagination,
        foldersPagination,
        totalFiles,
        totalFolders,
        currentFilesPage,
        currentFoldersPage,
        lastFilesPage,
        lastFoldersPage,
        fileRangeStart,
        fileRangeEnd,
        folderRangeStart,
        folderRangeEnd,
        hasActiveSearch,
        emptyStateText,
        filterStatusText,
        syncForms,
        scopePayload,
        visitFileManager,
        syncFromServer,
        changeScope,
        openFolder,
        submitUpload,
        selectUploadFile,
        setUploadFile,
        setDeleteConfirmation,
        setFriendSearch,
        setShowRenameModal,
        setShowShareModal,
        resetShareTarget,
        setFileInputElement,
        setDeleteConfirmationInputElement,
        setRenameFolderInputElement,
        setRenameFileInputElement,
        setShareTargetSelectElement,
        setShareEmailInputElement,
        createFolder,
        openDeleteModal,
        closeDeleteModal,
        deleteFile,
        confirmDeleteFolder,
        deleteConfirmed,
        openRename,
        submitRename,
        openShare,
        shareItem,
        formatSize,
        formatStorage,
        fileName,
        contextLabel,
        clearSearch,
        scheduleFilterRefresh,
        applyFilters,
        goToFilesPage,
        goToFoldersPage,
    }
}

