<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Modal from '@/Components/Modal.vue'
const showDeleteModal = ref(false)
const folderToDelete = ref(null)

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
    folders: {
        type: Array,
        default: () => [],
    },
    scope: {
        type: Object,
        default: () => ({ type: 'user' }),
    },
    clubs: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
})

const scopeForm = useForm({
    scope: props.scope.type || 'user',
    club_id: props.scope.club_id,
    team_id: props.scope.team_id,
    event_id: props.scope.event_id,
})

const uploadForm = useForm({
    scope: props.scope.type || 'user',
    club_id: props.scope.club_id,
    team_id: props.scope.team_id,
    event_id: props.scope.event_id,
    folder_id: null,
    file: null,
})

const folderForm = useForm({
    scope: props.scope.type || 'user',
    club_id: props.scope.club_id,
    team_id: props.scope.team_id,
    event_id: props.scope.event_id,
    parent_id: null,
    name: '',
})

const scopeOptions = [
    { value: 'user', label: 'Meine Dateien' },
    { value: 'team', label: 'Team' },
    { value: 'club', label: 'Club' },
    { value: 'event', label: 'Event' },
]

const activeFolders = computed(() => props.folders)
const activeFiles = computed(() => props.files)

const syncForms = () => {
    ;[uploadForm, folderForm].forEach((form) => {
        form.scope = scopeForm.scope
        form.club_id = scopeForm.club_id
        form.team_id = scopeForm.team_id
        form.event_id = scopeForm.event_id
    })
}

const changeScope = () => {
    if (scopeForm.scope !== 'club') scopeForm.club_id = null
    if (scopeForm.scope !== 'team') scopeForm.team_id = null
    if (scopeForm.scope !== 'event') scopeForm.event_id = null
    syncForms()

    router.get(route('auth.files.index'), scopeForm.data(), {
        preserveState: false,
        preserveScroll: true,
    })
}

const submitUpload = () => {
    syncForms()
    uploadForm.post(route('auth.files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('file', 'folder_id'),
    })
}

const createFolder = () => {
    syncForms()
    folderForm.post(route('auth.folders.store'), {
        preserveScroll: true,
        onSuccess: () => folderForm.reset('name', 'parent_id'),
    })
}

const deleteFile = (file) => {
    router.delete(route('auth.files.destroy', file.id), {
        preserveScroll: true,
    })
}

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
        }
    })
}

const deleteFolder = (folder) => {
    router.delete(route('auth.folders.destroy', folder.id), {
        preserveScroll: true,
        onError: (errors) => {
            alert('Ordner enthält noch Dateien!')
        }
    })
}

const formatSize = (size) => {
    if (!size) return ''
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`
    return `${(size / 1024 / 1024).toFixed(1)} MB`
}

const contextLabel = (file) => {
    if (file.event) return file.event.title
    if (file.team) return file.team.name
    if (file.club) return file.club.name
    return 'Privat'
}
</script>

<template>
    <AppLayout title="Dateien">

        <Head title="Dateien" />

        <div class="space-y-4">
            <div class="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 lg:flex-row lg:items-end">
                <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-sm">
                        <span class="mb-1 block text-secondary">Bereich</span>
                        <select v-model="scopeForm.scope"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            @change="changeScope">
                            <option v-for="option in scopeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'club'" class="text-sm">
                        <span class="mb-1 block text-secondary">Club</span>
                        <select v-model="scopeForm.club_id"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            @change="changeScope">
                            <option :value="null">AuswÃ¤hlen</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'team'" class="text-sm">
                        <span class="mb-1 block text-secondary">Team</span>
                        <select v-model="scopeForm.team_id"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            @change="changeScope">
                            <option :value="null">AuswÃ¤hlen</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </label>

                    <label v-if="scopeForm.scope === 'event'" class="text-sm">
                        <span class="mb-1 block text-secondary">Event</span>
                        <select v-model="scopeForm.event_id"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            @change="changeScope">
                            <option :value="null">AuswÃ¤hlen</option>
                            <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
                <section class="rounded-lg border border-border bg-card">
                    <div class="border-b border-border p-4">
                        <h1 class="text-lg font-semibold text-primary">Dateien</h1>
                        <p class="text-sm text-secondary">{{ activeFiles.length }} Datei(en), {{ activeFolders.length }}
                            Ordner</p>
                    </div>

                    <div class="divide-y divide-border">
                        <div v-for="folder in activeFolders" :key="folder.id" class="flex items-center gap-3 p-4">
                            <i class="las la-folder text-2xl text-secondary"></i>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">{{ folder.name }}</p>
                                <p class="text-xs text-secondary">Ordner</p>
                            </div>
                            <button
                                class="rounded-lg border border-border px-3 py-2 text-sm text-secondary hover:bg-inputBg"
                                @click="confirmDeleteFolder(folder)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>

                        <div v-for="file in activeFiles" :key="file.id" class="flex items-center gap-3 p-4">
                            <i class="las la-file-alt text-2xl text-secondary"></i>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-primary">{{ file.path.split('/').pop() }}
                                </p>
                                <p class="truncate text-xs text-secondary">
                                    {{ contextLabel(file) }} · {{ file.type }} · {{ formatSize(file.size) }}
                                </p>
                            </div>
                            <Link :href="route('auth.files.download', file.id)"
                                class="rounded-lg border border-border px-3 py-2 text-sm text-secondary hover:bg-inputBg">
                                <i class="las la-download"></i>
                            </Link>
                            <button
                                class="rounded-lg border border-border px-3 py-2 text-sm text-secondary hover:bg-inputBg"
                                @click="deleteFile(file)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>

                        <div v-if="!activeFiles.length && !activeFolders.length"
                            class="p-8 text-center text-sm text-secondary">
                            Keine Dateien in diesem Bereich.
                        </div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <form class="rounded-lg border border-border bg-card p-4" @submit.prevent="submitUpload">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Upload</h2>
                        <select v-model="uploadForm.folder_id"
                            class="mt-3 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option :value="null">Kein Ordner</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}
                            </option>
                        </select>
                        <input class="mt-3 block w-full text-sm text-secondary" type="file"
                            @change="uploadForm.file = $event.target.files?.[0] || null">
                        <button :disabled="uploadForm.processing || !uploadForm.file"
                            class="mt-3 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                            Hochladen
                        </button>
                    </form>

                    <form class="rounded-lg border border-border bg-card p-4" @submit.prevent="createFolder">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Ordner</h2>
                        <select v-model="folderForm.parent_id"
                            class="mt-3 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <option :value="null">Ohne Parent</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}
                            </option>
                        </select>
                        <input v-model="folderForm.name"
                            class="mt-3 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            placeholder="Ordnername">
                        <button :disabled="folderForm.processing || !folderForm.name.trim()"
                            class="mt-3 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
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

            <p class="text-sm text-secondary">
                Sind Sie sicher, dass Sie diesen Ordner <strong>inklusive aller Dateien</strong> löschen möchten?
            </p>
            <p>
                Dieser Ordner enthält {{ folderToDelete?.files_count ?? '?' }} Dateien.
            </p>

            <div class="flex gap-3">
                <button class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600"
                    @click="showDeleteModal = false">
                    Abbrechen
                </button>

                <button class="flex-1 rounded-lg bg-red-500 py-2 text-white hover:bg-red-600"
                    @click="deleteFolderConfirmed">
                    Löschen
                </button>
            </div>
        </div>
    </Modal>
</template>
