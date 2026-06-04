<script setup>
import { computed } from 'vue'

const props = defineProps({
    deleteTarget: { type: Object, default: null },
    deleteConfirmText: { type: String, default: '' },
    deleteCommentTarget: { type: Object, default: null },
    reportTarget: { type: Object, default: null },
    reportForm: { type: Object, required: true },
    reportReasons: { type: Array, default: () => [] },
    closeDeletePost: { type: Function, required: true },
    deletePost: { type: Function, required: true },
    closeDeleteComment: { type: Function, required: true },
    confirmDeleteComment: { type: Function, required: true },
    closeReport: { type: Function, required: true },
    submitReport: { type: Function, required: true },
})

const emit = defineEmits(['update:deleteConfirmText'])

const deleteConfirmModel = computed({
    get: () => props.deleteConfirmText,
    set: (value) => emit('update:deleteConfirmText', value),
})
</script>

<template>
    <div
        v-if="deleteTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeDeletePost"
    >
        <form class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="deletePost">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-error">Beitrag löschen</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">Bist du sicher?</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Dieser Beitrag und seine Anhänge werden gelöscht. Gib <span class="font-semibold text-primary">delete</span> ein, um fortzufahren.
                    </p>
                </div>
                <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeDeletePost">
                    <i class="las la-times"></i>
                </button>
            </div>

            <input
                v-model="deleteConfirmModel"
                type="text"
                autocomplete="off"
                placeholder="delete"
                class="mt-5 w-full rounded-lg border-border bg-inputBg text-primary"
            />

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn" @click="closeDeletePost">Abbrechen</button>
                <button
                    type="submit"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deleteConfirmModel !== 'delete'"
                >
                    Löschen
                </button>
            </div>
        </form>
    </div>

    <div
        v-if="deleteCommentTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeDeleteComment"
    >
        <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-error">Kommentar entfernen</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">Bist du sicher?</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        Dieser Kommentar wird dauerhaft vom Beitrag entfernt.
                    </p>
                </div>
                <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeDeleteComment">
                    <i class="las la-times"></i>
                </button>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn" @click="closeDeleteComment">Abbrechen</button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                    @click="confirmDeleteComment"
                >
                    Kommentar löschen
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="reportTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeReport"
    >
        <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="submitReport">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Inhalt melden</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">
                        Warum soll dieser Inhalt geprüft werden?
                    </h2>
                </div>
                <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeReport">
                    <i class="las la-times"></i>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="text-sm font-semibold text-primary">Grund</label>
                    <select v-model="reportForm.reason" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option v-for="reason in reportReasons" :key="reason.value" :value="reason.value">
                            {{ reason.label }}
                        </option>
                    </select>
                    <p v-if="reportForm.errors.reason" class="mt-1 text-sm text-error">{{ reportForm.errors.reason }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Details optional</label>
                    <textarea
                        v-model="reportForm.details"
                        rows="4"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                        placeholder="Was ist dir aufgefallen?"
                    />
                    <p v-if="reportForm.errors.details" class="mt-1 text-sm text-error">{{ reportForm.errors.details }}</p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn" @click="closeReport">Abbrechen</button>
                <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                    Meldung senden
                </button>
            </div>
        </form>
    </div>
</template>

