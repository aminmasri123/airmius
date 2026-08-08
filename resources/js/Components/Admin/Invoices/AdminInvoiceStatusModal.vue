<script setup>
defineProps({
    statusModal: { type: Object, required: true },
    statusForm: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    statusLabel: { type: Function, required: true },
    statusClasses: { type: Function, required: true },
    closeStatusModal: { type: Function, required: true },
    submitStatus: { type: Function, required: true },
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="statusModal.open"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-3 backdrop-blur-sm sm:p-4"
            @click.self="closeStatusModal"
        >
            <form
                class="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                @submit.prevent="submitStatus"
            >
                <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-air-blue">Status aktualisieren</p>
                        <h2 class="mt-1 truncate text-xl font-black text-primary">
                            {{ statusModal.invoice?.number || 'Rechnung' }}
                        </h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ statusModal.invoice?.title || 'Rechnungsstatus verwalten' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-secondary hover:bg-muted hover:text-primary"
                        :disabled="statusForm.processing"
                        aria-label="Status-Modal schließen"
                        @click="closeStatusModal"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border p-4 text-left transition"
                            :class="statusForm.status === option.value ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'"
                            @click="statusForm.status = option.value"
                        >
                            <span class="block text-sm font-black">{{ option.label }}</span>
                            <span class="mt-1 block text-xs leading-5">{{ option.hint }}</span>
                        </button>
                    </div>
                    <p v-if="statusForm.errors.status" class="text-sm text-error">{{ statusForm.errors.status }}</p>

                    <div class="rounded-xl border border-border bg-inputBg p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-secondary">{{ $t('Aktuell') }}</span>
                            <span class="rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(statusModal.invoice?.status)">
                                {{ statusLabel(statusModal.invoice?.status) }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <span class="text-secondary">Neu</span>
                            <span class="rounded-full border px-3 py-1 text-xs font-black" :class="statusClasses(statusForm.status)">
                                {{ statusLabel(statusForm.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-xl border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted" :disabled="statusForm.processing" @click="closeStatusModal">
                        Abbrechen
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-black text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="statusForm.processing"
                    >
                        <i class="las la-sync-alt text-lg"></i>
                        Status speichern
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
