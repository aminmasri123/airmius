<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    files: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
    allFolders: { type: Array, default: () => [] },
    currentFolder: { type: Object, default: null },
    scope: { type: Object, default: () => ({ type: 'user' }) },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
})

const showDeleteModal = ref(false)
const showShareModal = ref(false)
const showRenameModal = ref(false)
const folderToDelete = ref(null)
const itemToShare = ref(null)
const shareType = ref('file')
const friendSearch = ref('')
const renameTarget = ref(null)
const renameType = ref('file')

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

const scopeOptions = [
    { value: 'user', label: 'Meine Dateien' },
    { value: 'team', label: 'Team' },
    { value: 'club', label: 'Verein' },
    { value: 'event', label: 'Event' },
]

const activeFiles = computed(() => props.files)
const activeFolders = computed(() => props.folders)
const shareTargets = computed(() => {
    const query = friendSearch.value.trim().toLowerCase()

    if (!query) return props.users

    return props.users.filter((user) => `${user.name || ''} ${user.email || ''}`.toLowerCase().includes(query))
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
})

const changeScope = () => {
    if (scopeForm.scope !== 'club') scopeForm.club_id = null
    if (scopeForm.scope !== 'team') scopeForm.team_id = null
    if (scopeForm.scope !== 'event') scopeForm.event_id = null
    scopeForm.folder_id = null
    syncForms()
    router.get(route('auth.files.index'), scopePayload(), { preserveState: false, preserveScroll: true })
}

const openFolder = (folder) => {
    scopeForm.folder_id = folder?.id || null
    router.get(route('auth.files.index'), scopePayload(folder?.id), { preserveState: false, preserveScroll: true })
}

const submitUpload = () => {
    syncForms()
    uploadForm.folder_id = props.currentFolder?.id || null
    uploadForm.post(route('auth.files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('file'),
    })
}

const createFolder = () => {
    syncForms()
    folderForm.parent_id = props.currentFolder?.id || null
    folderForm.post(route('auth.folders.store'), {
        preserveScroll: true,
        onSuccess: () => folderForm.reset('name'),
    })
}

const deleteFile = (file) => router.delete(route('auth.files.destroy', file.id), { preserveScroll: true })
const confirmDeleteFolder = (folder) => {
    folderToDelete.value = folder
    showDeleteModal.value = true
}

const deleteFolderConfirmed = () => {
    if (!folderToDelete.value) return
    router.delete(route('auth.folders.destroy', folderToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            showDeleteModal.value = false
            folderToDelete.value = null
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

const fileName = (file) => file.display_name || file.path.split('/').pop()
const contextLabel = (file) => file.event?.title || file.team?.name || file.club?.name || 'Privat'
</script>

<template>
    <AppLayout title="Dateien">
        <Head title="Dateien" />

        <div class="space-y-4">
            <div class="flex flex-col gap-4 rounded-lg border border-border bg-card p-4 lg:flex-row lg:items-end">
                <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-sm">
                        <span class="mb-1 block text-secondary">Bereich</span>
                        <select v-model="scopeForm.scope" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="changeScope">
                            <option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'club'" class="text-sm">
                        <span class="mb-1 block text-secondary">Verein</span>
                        <select v-model="scopeForm.club_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'team'" class="text-sm">
                        <span class="mb-1 block text-secondary">Team</span>
                        <select v-model="scopeForm.team_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'event'" class="text-sm">
                        <span class="mb-1 block text-secondary">Event</span>
                        <select v-model="scopeForm.event_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="changeScope">
                            <option :value="null">Auswählen</option>
                            <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_340px]">
                <section class="rounded-lg border border-border bg-card">
                    <div class="flex flex-col gap-3 border-b border-border p-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 class="text-lg font-semibold text-primary">{{ currentFolder?.name || 'Dateimanager' }}</h1>
                            <p class="text-sm text-secondary">{{ activeFolders.length }} Ordner · {{ activeFiles.length }} Dateien</p>
                        </div>
                        <button v-if="currentFolder" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-inputBg" @click="openFolder(currentFolder.parent)">
                            <i class="las la-arrow-left"></i>
                            Zurück
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3">
                        <button v-for="folder in activeFolders" :key="folder.id" class="group flex items-center gap-3 rounded-lg border border-transparent p-3 text-left hover:border-border hover:bg-inputBg" @click="openFolder(folder)">
                            <i class="las la-folder text-3xl text-yellow-500"></i>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ folder.name }}</p>
                                <p class="text-xs text-secondary">{{ folder.files_count || 0 }} Dateien</p>
                            </div>
                            <span class="rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100" @click.stop="openShare(folder, 'folder')" title="Freigeben">
                                <i class="las la-share-alt"></i>
                            </span>
                            <span class="rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100" @click.stop="openRename(folder, 'folder')" title="Umbenennen">
                                <i class="las la-pen"></i>
                            </span>
                            <span class="rounded-lg p-2 text-secondary opacity-0 group-hover:opacity-100" @click.stop="confirmDeleteFolder(folder)" title="Löschen">
                                <i class="las la-trash"></i>
                            </span>
                        </button>

                        <div v-for="file in activeFiles" :key="file.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                            <i class="las la-file-alt text-3xl text-secondary"></i>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ fileName(file) }}</p>
                                <p class="truncate text-xs text-secondary">{{ contextLabel(file) }} · {{ file.type }} · {{ formatSize(file.size) }}</p>
                            </div>
                            <button class="rounded-lg p-2 text-secondary hover:bg-inputBg" @click="openShare(file)" title="Freigeben">
                                <i class="las la-share-alt"></i>
                            </button>
                            <button class="rounded-lg p-2 text-secondary hover:bg-inputBg" @click="openRename(file)" title="Umbenennen">
                                <i class="las la-pen"></i>
                            </button>
                            <a :href="route('auth.files.download', file.id)" class="rounded-lg p-2 text-secondary hover:bg-inputBg" title="Herunterladen">
                                <i class="las la-download"></i>
                            </a>
                            <button class="rounded-lg p-2 text-secondary hover:bg-inputBg" @click="deleteFile(file)" title="Löschen">
                                <i class="las la-trash"></i>
                            </button>
                        </div>

                        <div v-if="!activeFiles.length && !activeFolders.length" class="col-span-full p-8 text-center text-sm text-secondary">
                            Dieser Ordner ist leer.
                        </div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <form class="rounded-lg border border-border bg-card p-4" @submit.prevent="submitUpload">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Upload</h2>
                        <p class="mt-1 text-xs text-secondary">Ziel: {{ currentFolder?.name || 'Hauptebene' }}</p>
                        <input class="mt-3 block w-full text-sm text-secondary" type="file" @change="uploadForm.file = $event.target.files?.[0] || null">
                        <button :disabled="uploadForm.processing || !uploadForm.file" class="mt-3 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                            Hochladen
                        </button>
                    </form>

                    <form class="rounded-lg border border-border bg-card p-4" @submit.prevent="createFolder">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Neuer Ordner</h2>
                        <input v-model="folderForm.name" class="mt-3 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Ordnername">
                        <button :disabled="folderForm.processing || !folderForm.name.trim()" class="mt-3 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                            Erstellen
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </AppLayout>

    <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">Ordner löschen</h2>
            <p class="text-sm text-secondary">Der Ordner und seine Dateien werden entfernt.</p>
            <div class="flex gap-3">
                <button class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600" @click="showDeleteModal = false">Abbrechen</button>
                <button class="flex-1 rounded-lg bg-red-500 py-2 text-white hover:bg-red-600" @click="deleteFolderConfirmed">Löschen</button>
            </div>
        </div>
    </Modal>

    <Modal :show="showRenameModal" max-width="md" @close="showRenameModal = false">
        <form class="space-y-4 text-primary" @submit.prevent="submitRename">
            <h2 class="text-lg font-bold">{{ renameType === 'folder' ? 'Ordner' : 'Datei' }} umbenennen</h2>
            <input
                v-if="renameType === 'folder'"
                v-model="renameForm.name"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Ordnername"
            >
            <input
                v-else
                v-model="renameForm.display_name"
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
            <select v-if="shareForm.target_type === 'user'" v-model="shareForm.target_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="">Auswählen</option>
                <option v-for="target in shareTargets" :key="target.id" :value="target.id">
                    {{ target.name }}{{ target.email ? ` · ${target.email}` : '' }}
                </option>
            </select>
            <input v-else v-model="shareForm.email" type="email" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" placeholder="name@example.com">
            <button :disabled="shareForm.processing || (shareForm.target_type === 'user' ? !shareForm.target_id : !shareForm.email)" class="w-full rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary disabled:opacity-50" @click="shareItem">
                Freigeben
            </button>
        </div>
    </Modal>
</template>
