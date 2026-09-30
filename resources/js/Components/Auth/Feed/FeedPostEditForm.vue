<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    post: { type: Object, required: true },
    editForm: { type: Object, required: true },
    visibilities: { type: Array, default: () => [] },
    postTypes: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    visibilityLabel: { type: Function, required: true },
    postTypeLabel: { type: Function, required: true },
    skillsForSport: { type: Function, required: true },
})

const emit = defineEmits(['update-post'])
</script>

<template>
    <form
        class="space-y-3 px-4 pb-4"
        @submit.prevent="emit('update-post')"
    >
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <select
                v-model="editForm.visibility"
                @change="editForm.club_id = ''; editForm.team_id = ''"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            >
                <option
                    v-for="visibility in visibilities"
                    :key="visibility"
                    :value="visibility"
                >
                    {{ visibilityLabel(visibility) }}
                </option>
            </select>

            <select
                v-model="editForm.post_type"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            >
                <option
                    v-for="type in postTypes"
                    :key="type"
                    :value="type"
                >
                    {{ postTypeLabel(type) }}
                </option>
            </select>

            <select
                v-model="editForm.content_origin"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            >
                <option value="self">Von mir selbst erstellt</option>
                <option value="ai">Mit KI erstellt</option>
            </select>

            <select
                v-model="editForm.club_id"
                v-if="editForm.visibility === 'organization'"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            >
                <option value="">Kein Verein</option>
                <option
                    v-for="club in clubs"
                    :key="club.id"
                    :value="club.id"
                >
                    {{ club.name }}
                </option>
            </select>

            <select
                v-model="editForm.team_id"
                v-if="editForm.visibility === 'team'"
                class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            >
                <option value="">Kein Team</option>
                <option
                    v-for="team in teams"
                    :key="team.id"
                    :value="team.id"
                >
                    {{ team.name }}
                </option>
            </select>
        </div>

        <div class="grid grid-cols-1 gap-2 lg:grid-cols-[1fr_2fr]">
            <SearchableSelect
                v-model="editForm.sport_id"
                :options="sports"
                value-key="id"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                placeholder="Sportart zum Beitrag"
            />

            <div class="flex min-h-11 max-w-full flex-wrap gap-2 overflow-hidden rounded-lg border border-border bg-inputBg px-3 py-2">
                <label
                    v-for="skill in skillsForSport(editForm.sport_id)"
                    :key="skill.id"
                    class="inline-flex max-w-full cursor-pointer items-center gap-2 rounded-full border border-border bg-card px-3 py-2 text-xs text-primary"
                >
                    <input
                        v-model="editForm.sport_skill_ids"
                        type="checkbox"
                        :value="skill.id"
                        class="shrink-0 rounded border-border bg-inputBg"
                    >

                    <span class="min-w-0 truncate">
                        {{ skill.name }}
                    </span>
                </label>

                <span
                    v-if="!editForm.sport_id"
                    class="min-w-0 break-words text-sm text-secondary"
                >
                    Optional: Sportart wählen.
                </span>
            </div>
        </div>

        <textarea
            v-model="editForm.content"
            rows="3"
            class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
        />

        <input
            type="file"
            multiple
            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
            class="block w-full text-sm text-secondary"
            @change="editForm.attachments = Array.from($event.target.files || [])"
        >

        <div class="flex flex-col gap-2 sm:flex-row">
            <button
                class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm text-buttonTextPrimary sm:w-auto"
                :disabled="editForm.processing"
            >
                Speichern
            </button>

            <button
                type="button"
                class="w-full rounded-lg border border-border px-4 py-3 text-sm text-primary sm:w-auto"
                @click="editForm.editing = false"
            >
                Abbrechen
            </button>
        </div>
    </form>
</template>
