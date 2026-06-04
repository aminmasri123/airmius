<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    selectedSport: {
        type: Object,
        default: null,
    },
})

defineEmits(['delete', 'save'])

const usageLabel = (sport) => [
    `${sport.teams_count} Teams`,
    `${sport.clubs_count} Vereine`,
    `${sport.profiles_count} Profile`,
    `${sport.posts_count} Beiträge`,
].join(' · ')
</script>

<template>
    <div class="min-w-0 space-y-6 overflow-y-auto">
        <section v-if="selectedSport" class="rounded-lg border border-border bg-card">
            <div class="flex flex-col gap-3 border-b border-border p-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-semibold text-primary">
                        {{ selectedSport.name }}
                    </h2>

                    <p class="mt-1 text-sm text-secondary">
                        {{ usageLabel(selectedSport) }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                        :disabled="form.processing"
                        @click="$emit('save')"
                    >
                        Speichern
                    </button>

                    <button
                        type="button"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="selectedSport.usage_count > 0"
                        :title="selectedSport.usage_count > 0 ? 'Genutzte Sportarten bitte deaktivieren statt löschen.' : 'Sportart löschen'"
                        @click="$emit('delete')"
                    >
                        Löschen
                    </button>
                </div>
            </div>

            <div class="grid gap-4 p-4 md:grid-cols-2">
                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-secondary">
                        Name
                    </span>

                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                    <span v-if="form.errors.name" class="text-sm text-error">
                        {{ form.errors.name }}
                    </span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-secondary">
                        Slug
                    </span>

                    <input v-model="form.slug" type="text" placeholder="wird aus Name erzeugt" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                    <span v-if="form.errors.slug" class="text-sm text-error">
                        {{ form.errors.slug }}
                    </span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-secondary">
                        Kategorie
                    </span>

                    <input v-model="form.category" list="sport-categories" type="text" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                    <span v-if="form.errors.category" class="text-sm text-error">
                        {{ form.errors.category }}
                    </span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-secondary">
                        Sortierung
                    </span>

                    <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">

                    <span v-if="form.errors.sort_order" class="text-sm text-error">
                        {{ form.errors.sort_order }}
                    </span>
                </label>

                <label class="flex items-center gap-3 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-border text-buttonPrimary focus:ring-buttonPrimary">

                    Aktiv in Auswahlfeldern anzeigen
                </label>

                <div class="rounded-lg border border-border bg-inputBg p-3 text-sm text-secondary">
                    <p>
                        <strong class="text-primary">
                            {{ selectedSport.skills_count }}
                        </strong>
                        Skills hinterlegt
                    </p>

                    <p class="mt-1">
                        <strong class="text-primary">
                            {{ selectedSport.usage_count }}
                        </strong>
                        gesamte Nutzungen
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>


