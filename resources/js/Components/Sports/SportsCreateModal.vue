<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    show: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close', 'create'])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="$emit('close')"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">
                                Neue Sportart
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                Slug kann leer bleiben und wird automatisch erzeugt.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-border px-3 py-1 text-secondary hover:border-borderHover hover:text-primary"
                            @click="$emit('close')"
                        >
                            <i class="las la-times text-lg"></i>
                        </button>
                    </div>
                </div>

                <form class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" @submit.prevent="$emit('create')">
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-secondary">
                            Name
                        </span>

                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z. B. Fußball"
                        >

                        <span v-if="form.errors.name" class="text-sm text-error">
                            {{ form.errors.name }}
                        </span>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-secondary">
                            Slug
                        </span>

                        <input v-model="form.slug" type="text" placeholder="optional" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">

                        <span v-if="form.errors.slug" class="text-sm text-error">
                            {{ form.errors.slug }}
                        </span>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-secondary">
                            Kategorie
                        </span>

                        <input
                            v-model="form.category"
                            list="sport-categories"
                            type="text"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            placeholder="z. B. Ballsport"
                        >
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold uppercase text-secondary">
                            Sortierung
                        </span>

                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-border text-buttonPrimary focus:ring-buttonPrimary">

                        Aktiv in Auswahlfeldern anzeigen
                    </label>
                </form>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary"
                            @click="$emit('close')"
                        >
                            Abbrechen
                        </button>

                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-50"
                            :disabled="form.processing"
                            @click="$emit('create')"
                        >
                            {{ form.processing ? 'Speichern...' : 'Erstellen' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

