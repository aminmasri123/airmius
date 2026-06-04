<script setup>
defineProps({
    deleteTarget: {
        type: Object,
        default: null,
    },
    confirmation: {
        type: String,
        default: '',
    },
})

defineEmits(['close', 'confirm', 'update:confirmation'])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="deleteTarget"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 px-4"
            @click.self="$emit('close')"
        >
            <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-error">Sponsor löschen</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">{{ deleteTarget.name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Dieser Sponsor wird dauerhaft gelöscht. Gib zur Bestätigung <span class="font-semibold text-primary">delete</span> ein.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg border border-border px-3 py-1 text-secondary hover:text-primary" @click="$emit('close')">
                        <i class="las la-times text-lg"></i>
                    </button>
                </div>

                <input
                    :value="confirmation"
                    class="mt-4 w-full rounded-lg border-border bg-inputBg text-primary"
                    placeholder="delete"
                    autocomplete="off"
                    @input="$emit('update:confirmation', $event.target.value)"
                >

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="$emit('close')">
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="confirmation !== 'delete'"
                        @click="$emit('confirm')"
                    >
                        Endgültig löschen
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

