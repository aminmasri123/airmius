<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    target: { type: Object, default: null },
})

const emit = defineEmits(['close', 'confirm'])
</script>

<template>
    <Modal :show="Boolean(target)" max-width="md" @close="emit('close')">
        <div v-if="target" class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-error">Mitfahrer entfernen</p>
                <h2 class="mt-1 text-lg font-bold text-primary">
                    {{ target.member.name }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    Diese Person wird aus der Fahrgemeinschaft
                    <span class="font-semibold text-primary">{{ target.ride.from }} -> {{ target.ride.to }}</span>
                    entfernt und bekommt eine Benachrichtigung.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                    @click="emit('confirm')"
                >
                    Entfernen
                </button>
            </div>
        </div>
    </Modal>
</template>
