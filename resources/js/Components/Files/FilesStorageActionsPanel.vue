<script setup>
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'

defineProps({
    storageUsage: { type: Object, default: null },
    formatStorage: { type: Function, required: true },
    currentFolder: { type: Object, default: null },
    uploadForm: { type: Object, required: true },
    folderForm: { type: Object, required: true },
    uploadFileName: { type: String, required: true },
    isStorageFull: { type: Boolean, default: false },
    submitUpload: { type: Function, required: true },
    selectUploadFile: { type: Function, required: true },
    setUploadFile: { type: Function, required: true },
    createFolder: { type: Function, required: true },
    setFileInputElement: { type: Function, required: true },
})
</script>

<template>
    <aside class="min-w-0 space-y-3">
        <section v-if="storageUsage" class="rounded-lg border border-border bg-card p-3">
            <div class="flex items-start justify-between gap-3 md:items-center">
                <div class="min-w-0">
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
            <div class="mt-2 text-xs text-secondary md:flex md:items-center md:justify-between">
                <span class="md:hidden">{{ formatStorage(storageUsage.used_bytes) }} genutzt von {{ storageUsage.limit_gb }} GB gesamt</span>
                <span class="hidden md:inline">{{ formatStorage(storageUsage.used_bytes) }} genutzt</span>
                <span class="hidden md:inline">{{ storageUsage.limit_gb }} GB gesamt</span>
            </div>
        </section>

        <form class="rounded-lg border border-border bg-card p-3" @submit.prevent="submitUpload">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Upload</h2>
                <span class="truncate text-xs text-secondary">{{ currentFolder?.name || 'Hauptebene' }}</span>
            </div>
            <input :ref="setFileInputElement" class="hidden" type="file" @change="setUploadFile">
            <button
                type="button"
                class="mt-3 flex h-11 w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-border bg-inputBg px-3 text-left text-sm text-primary hover:bg-muted md:h-10"
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
            <AppLoadingState v-if="uploadForm.processing" class="mt-2" label="Upload laeuft..." inline />
            <AppButton
                type="submit"
                class="mt-2"
                block
                :loading="uploadForm.processing"
                :disabled="uploadForm.processing || !uploadForm.file || isStorageFull"
            >
                {{ uploadForm.processing ? 'Laedt hoch...' : 'Hochladen' }}
            </AppButton>
        </form>

        <form class="rounded-lg border border-border bg-card p-3" @submit.prevent="createFolder">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Neuer Ordner</h2>
            <input v-model="folderForm.name" class="mt-3 h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-sm text-primary md:h-10" placeholder="Ordnername">
            <AppLoadingState v-if="folderForm.processing" class="mt-2" label="Ordner wird erstellt..." inline />
            <AppButton
                type="submit"
                class="mt-2"
                block
                :loading="folderForm.processing"
                :disabled="folderForm.processing || !folderForm.name.trim()"
            >
                {{ folderForm.processing ? 'Erstellt...' : 'Erstellen' }}
            </AppButton>
        </form>
    </aside>
</template>
