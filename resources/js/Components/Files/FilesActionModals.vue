<script setup>
import Modal from '@/Components/Modal.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

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
})
</script>

<template>
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
                    :ref="setDeleteConfirmationInputElement"
                    :value="deleteConfirmation"
                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                    autocomplete="off"
                    :placeholder="tx('files.delete_word')"
                    @input="setDeleteConfirmation($event.target.value)"
                    @keyup.enter="deleteConfirmed"
                >
            </label>

            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg border border-border px-4 py-2 text-primary hover:bg-inputBg" @click="closeDeleteModal">{{ tx('files.cancel') }}</button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-error px-4 py-2 font-semibold text-buttonTextPrimary hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canConfirmDelete || deleteProcessing"
                    @click="deleteConfirmed"
                >
                    {{ deleteProcessing ? tx('files.deleting') : tx('files.delete_final') }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showRenameModal" max-width="md" @close="setShowRenameModal(false)">
        <form class="space-y-4 text-primary" @submit.prevent="submitRename">
            <h2 class="text-lg font-bold">{{ renameType === 'folder' ? tx('files.rename_folder') : tx('files.rename_file') }}</h2>
            <input
                v-if="renameType === 'folder'"
                :ref="setRenameFolderInputElement"
                v-model="renameForm.name"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                :placeholder="tx('files.folder_name')"
            >
            <input
                v-else
                :ref="setRenameFileInputElement"
                v-model="renameForm.display_name"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                :placeholder="tx('files.file')"
            >
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-lg border border-border py-2 text-primary hover:bg-inputBg" @click="setShowRenameModal(false)">{{ tx('files.cancel') }}</button>
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

    <Modal :show="showShareModal" max-width="md" @close="setShowShareModal(false)">
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">{{ shareType === 'folder' ? tx('files.share_folder') : tx('files.share_file') }}</h2>
            <p class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-secondary">{{ tx('files.internal_share_only') }}</p>
            <input
                :value="friendSearch"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                :placeholder="tx('files.friend_search')"
                @input="setFriendSearch($event.target.value)"
            >
            <select
                :ref="setShareTargetSelectElement"
                v-model="shareForm.target_id"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
            >
                <option value="">{{ tx('files.select') }}</option>
                <option v-for="target in shareTargets" :key="target.id" :value="target.id">
                    {{ target.name }}
                </option>
            </select>
            <button
                type="button"
                :disabled="shareForm.processing || !shareForm.target_id"
                class="w-full rounded-lg bg-buttonPrimary py-2 text-buttonTextPrimary disabled:opacity-50"
                @click="shareItem"
            >
                {{ tx('files.share') }}
            </button>
        </div>
    </Modal>
</template>
