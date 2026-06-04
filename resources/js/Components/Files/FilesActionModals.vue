<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    showDeleteModal: { type: Boolean, default: false },
    showRenameModal: { type: Boolean, default: false },
    showShareModal: { type: Boolean, default: false },
    deleteType: { type: String, required: true },
    renameType: { type: String, required: true },
    shareType: { type: String, required: true },
    deleteTargetName: { type: String, default: '' },
    deleteConfirmation: { type: String, default: '' },
    canConfirmDelete: { type: Boolean, default: false },
    deleteProcessing: { type: Boolean, default: false },
    renameForm: { type: Object, required: true },
    shareForm: { type: Object, required: true },
    shareTargets: { type: Array, default: () => [] },
    friendSearch: { type: String, default: '' },
    closeDeleteModal: { type: Function, required: true },
    deleteConfirmed: { type: Function, required: true },
    submitRename: { type: Function, required: true },
    shareItem: { type: Function, required: true },
    setDeleteConfirmation: { type: Function, required: true },
    setFriendSearch: { type: Function, required: true },
    setShowRenameModal: { type: Function, required: true },
    setShowShareModal: { type: Function, required: true },
    resetShareTarget: { type: Function, required: true },
    setDeleteConfirmationInputElement: { type: Function, required: true },
    setRenameFolderInputElement: { type: Function, required: true },
    setRenameFileInputElement: { type: Function, required: true },
    setShareTargetSelectElement: { type: Function, required: true },
    setShareEmailInputElement: { type: Function, required: true },
})
</script>

<template>
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
                    :ref="setDeleteConfirmationInputElement"
                    :value="deleteConfirmation"
                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                    autocomplete="off"
                    placeholder="löschen"
                    @input="setDeleteConfirmation($event.target.value)"
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

    <Modal :show="showRenameModal" max-width="md" @close="setShowRenameModal(false)">
        <form class="space-y-4 text-primary" @submit.prevent="submitRename">
            <h2 class="text-lg font-bold">{{ renameType === 'folder' ? 'Ordner' : 'Datei' }} umbenennen</h2>
            <input
                v-if="renameType === 'folder'"
                :ref="setRenameFolderInputElement"
                v-model="renameForm.name"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Ordnername"
            >
            <input
                v-else
                :ref="setRenameFileInputElement"
                v-model="renameForm.display_name"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Dateiname"
            >
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg bg-gray-500 py-2 text-white hover:bg-gray-600" @click="setShowRenameModal(false)">Abbrechen</button>
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

    <Modal :show="showShareModal" max-width="md" @close="setShowShareModal(false)">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">{{ shareType === 'folder' ? 'Ordner' : 'Datei' }} freigeben</h2>
            <select v-model="shareForm.target_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" @change="resetShareTarget">
                <option value="user">Freund</option>
                <option v-if="shareType === 'file'" value="email">Externe E-Mail</option>
            </select>
            <input
                v-if="shareForm.target_type === 'user'"
                :value="friendSearch"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                placeholder="Freund suchen"
                @input="setFriendSearch($event.target.value)"
            >
            <select
                v-if="shareForm.target_type === 'user'"
                :ref="setShareTargetSelectElement"
                v-model="shareForm.target_id"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
            >
                <option value="">Auswählen</option>
                <option v-for="target in shareTargets" :key="target.id" :value="target.id">
                    {{ target.name }}{{ target.email ? ` · ${target.email}` : '' }}
                </option>
            </select>
            <input
                v-else
                :ref="setShareEmailInputElement"
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


