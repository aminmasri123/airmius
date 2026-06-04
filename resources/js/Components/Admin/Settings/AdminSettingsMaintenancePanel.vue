<script setup>
defineProps({
    form: { type: Object, required: true },
})
</script>

<template>
    <div class="rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Wartemodus</h2>
                <p class="mt-1 text-sm text-secondary">
                    Wenn aktiv, sehen alle nicht berechtigten Benutzer nur die Warteseite.
                </p>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-3">
                <span class="text-sm font-semibold text-secondary">
                    {{ form.maintenance_enabled ? 'Aktiv' : 'Inaktiv' }}
                </span>
                <input v-model="form.maintenance_enabled" type="checkbox" class="peer sr-only">
                <span
                    class="relative h-7 w-12 rounded-full transition"
                    :class="form.maintenance_enabled ? 'bg-buttonPrimary' : 'bg-muted'"
                >
                    <span
                        class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition"
                        :class="{ 'translate-x-5': form.maintenance_enabled }"
                    ></span>
                </span>
            </label>
        </div>
    </div>

    <div class="grid gap-4">
        <div>
            <label for="maintenance_title" class="text-sm font-semibold text-primary">Titel</label>
            <input
                id="maintenance_title"
                v-model="form.maintenance_title"
                type="text"
                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                maxlength="120"
                required
            >
            <p v-if="form.errors.maintenance_title" class="mt-1 text-sm text-error">
                {{ form.errors.maintenance_title }}
            </p>
        </div>

        <div>
            <label for="maintenance_message" class="text-sm font-semibold text-primary">Nachricht</label>
            <textarea
                id="maintenance_message"
                v-model="form.maintenance_message"
                rows="4"
                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                maxlength="500"
                required
            />
            <p v-if="form.errors.maintenance_message" class="mt-1 text-sm text-error">
                {{ form.errors.maintenance_message }}
            </p>
        </div>
    </div>
</template>
