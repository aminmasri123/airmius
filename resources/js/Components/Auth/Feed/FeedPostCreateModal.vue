<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    user: { type: Object, default: null },
    postForm: { type: Object, required: true },
    imagePreview: { type: String, default: null },
    showAdvanced: { type: Boolean, default: false },
    composerNotice: { type: String, default: '' },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    postTypes: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    selectedCreateSport: { type: Object, default: null },
    createSportSkills: { type: Array, default: () => [] },
    postBlockReason: { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit', 'toggle-advanced', 'image-change', 'attachments-change'])

const { t, te } = useI18n()
const imageInput = ref(null)
const attachmentInput = ref(null)

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const postTypeLabel = (type) => ({
    normal: 'Normal',
    question: 'Frage',
    knowledge: 'Wissen',
    training_drill: 'Trainingsübung',
    tactic: 'Taktik',
    analysis: 'Analyse',
    experience: 'Erfahrung',
    club_update: 'Vereinsinfo',
}[type] || type)
const visibilityLabel = (visibility) => ({
    public: 'Öffentlich',
    organization: 'Verein',
    team: 'Team',
}[visibility] || visibility)
const visibilityHint = (visibility) => ({
    public: 'Sichtbar für dein Netzwerk und passende öffentliche Feed-Kontexte',
    organization: 'Sichtbar für Mitglieder des ausgewählten Vereins',
    team: 'Sichtbar für Mitglieder des ausgewählten Teams',
}[visibility] || '')
const sportLabel = (sport) => {
    if (!sport) return ''
    const key = `sports.${sport.slug}`
    return te(key) ? t(key) : sport.name
}
const clearInputs = () => {
    if (imageInput.value) imageInput.value.value = null
    if (attachmentInput.value) attachmentInput.value.value = null
}

defineExpose({ clearInputs })
</script>

<template>
    <div
        class="fixed inset-0 z-[80] flex items-end overflow-y-auto bg-black/60 px-3 py-4 sm:items-center sm:px-4"
        @click.self="emit('close')"
    >
        <div
            class="mx-auto max-h-[calc(100dvh-2rem)] w-full max-w-[min(42rem,100%)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-card p-4 shadow-xl sm:p-5"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="min-w-0 truncate text-lg font-semibold text-primary">Beitrag erstellen</h2>

                <button
                    type="button"
                    class="shrink-0 rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary"
                    @click="emit('close')"
                >
                    <i class="las la-times text-2xl"></i>
                </button>
            </div>

            <form class="space-y-3" @submit.prevent="emit('submit')">
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        v-if="user?.profile_photo_thumb"
                        :src="user.profile_photo_thumb"
                        :alt="user.name"
                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                    />

                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary"
                    >
                        {{ initials(user?.name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-primary">{{ user?.name }}</p>
                        <p class="text-xs text-secondary">Neuer Beitrag</p>
                    </div>
                </div>

                <textarea
                    v-model="postForm.content"
                    rows="5"
                    placeholder="Was gibt es Neues?"
                    class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary placeholder-secondary"
                />

                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted sm:inline-flex sm:w-auto sm:justify-start"
                    @click="emit('toggle-advanced')"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <i class="las la-sliders-h shrink-0"></i>
                        <span class="truncate">Zielgruppe, Sport & Typ</span>
                    </span>
                    <i :class="showAdvanced ? 'las la-angle-up' : 'las la-angle-down'"></i>
                </button>

                <div v-if="showAdvanced" class="space-y-3 rounded-lg border border-border bg-inputBg/40 p-3">
                    <div class="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2">
                        <select
                            v-model="postForm.visibility"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
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
                            v-model="postForm.post_type"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
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
                            v-model="postForm.content_origin"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="self">Von mir selbst erstellt</option>
                            <option value="ai">Mit KI erstellt</option>
                        </select>

                        <select
                            v-model="postForm.club_id"
                            :class="[
                                'w-full rounded-lg border bg-inputBg px-3 py-3 text-sm text-primary',
                                postForm.visibility === 'organization' && !postForm.club_id
                                    ? 'border-red-500'
                                    : 'border-border'
                            ]"
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
                            v-model="postForm.team_id"
                            :class="[
                                'w-full rounded-lg border bg-inputBg px-3 py-3 text-sm text-primary',
                                postForm.visibility === 'team' && !postForm.team_id
                                    ? 'border-red-500'
                                    : 'border-border'
                            ]"
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

                    <p class="text-xs text-secondary">
                        {{ visibilityHint(postForm.visibility) }}
                    </p>
                    <p v-if="composerNotice" class="text-xs font-semibold text-error">
                        {{ composerNotice }}
                    </p>

                    <SearchableSelect
                        v-model="postForm.sport_id"
                        :options="sports"
                        value-key="id"
                        translation-prefix="sports"
                        category-translation-prefix="sport_categories"
                        placeholder="Sportart zum Beitrag"
                    />

                    <div class="flex min-h-11 max-w-full flex-wrap gap-2 overflow-hidden rounded-lg border border-border bg-inputBg px-3 py-2">
                        <label
                            v-for="skill in createSportSkills"
                            :key="skill.id"
                            class="inline-flex max-w-full cursor-pointer items-center gap-2 rounded-full border border-border bg-card px-3 py-2 text-xs text-primary"
                        >
                            <input
                                v-model="postForm.sport_skill_ids"
                                type="checkbox"
                                :value="skill.id"
                                class="shrink-0 rounded border-border bg-inputBg"
                            >

                            <span class="min-w-0 truncate">
                                {{ skill.name }}
                            </span>
                        </label>

                        <span
                            v-if="postForm.sport_id && !createSportSkills.length"
                            class="min-w-0 break-words text-sm text-secondary"
                        >
                            Keine Skills für {{ sportLabel(selectedCreateSport) || 'diese Sportart' }}.
                        </span>

                        <span
                            v-if="!postForm.sport_id"
                            class="min-w-0 break-words text-sm text-secondary"
                        >
                            Optional: Sportart wählen, um passende Skills zu markieren.
                        </span>
                    </div>
                </div>

                <input
                    ref="imageInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="emit('image-change', $event)"
                />

                <input
                    ref="attachmentInput"
                    type="file"
                    multiple
                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                    class="hidden"
                    @change="emit('attachments-change', $event)"
                />

                <img
                    v-if="imagePreview"
                    :src="imagePreview"
                    alt="Preview"
                    class="max-h-[70vh] w-full rounded-xl border border-border object-cover"
                />

                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <button
                        type="button"
                        class="min-w-0 rounded-lg border border-border bg-card px-3 py-3 text-sm font-semibold text-primary sm:w-auto sm:px-4"
                        @click="imageInput?.click()"
                    >
                        <i class="las la-image"></i>
                        Bild
                    </button>

                    <button
                        type="button"
                        class="min-w-0 rounded-lg border border-border bg-card px-3 py-3 text-sm font-semibold text-primary sm:w-auto sm:px-4"
                        @click="attachmentInput?.click()"
                    >
                        <i class="las la-video"></i>
                        <span class="hidden sm:inline">Video / Dateien</span>
                        <span class="sm:hidden">Dateien</span>
                    </button>

                    <span
                        v-if="postForm.attachments.length"
                        class="col-span-2 rounded-lg bg-muted px-3 py-2 text-xs text-secondary sm:col-span-1"
                    >
                        {{ postForm.attachments.length }} Datei(en)
                    </span>

                    <span class="col-span-2 rounded-lg bg-inputBg px-3 py-2 text-xs text-secondary sm:col-span-1">
                        Bilder optimiert, Videos bis 50 MB
                    </span>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="w-full rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary sm:w-auto"
                        @click="emit('close')"
                    >
                        Abbrechen
                    </button>

                    <button
                        type="submit"
                        :disabled="postForm.processing || Boolean(postBlockReason)"
                        class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50 sm:w-auto"
                    >
                        Posten
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

