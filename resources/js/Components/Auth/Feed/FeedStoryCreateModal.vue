<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    availableTeams: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    hasMedia: { type: Boolean, default: false },
    mediaPreview: { type: String, default: null },
    publisherOptions: { type: Array, default: () => [] },
    show: { type: Boolean, default: false },
    storyForm: { type: Object, required: true },
    storyUploading: { type: Boolean, default: false },
    visibilityOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'media-change', 'submit'])

const mediaInput = ref(null)

const submitDisabled = computed(() => props.storyUploading
    || props.storyForm.processing
    || !props.hasMedia
    || (props.storyForm.visibility === 'organization' && !props.storyForm.club_id)
    || (props.storyForm.visibility === 'team' && !props.storyForm.team_id))

const visibilityLabel = (visibility) => ({
    public: 'öffentlich',
    organization: 'Verein',
    team: 'Team',
}[visibility] || visibility)
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[70] flex items-end bg-black/70 sm:items-center sm:p-4"
        @click.self="emit('close')"
    >
        <form
            class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-border bg-card p-4 shadow-xl sm:mx-auto sm:max-w-lg sm:rounded-2xl"
            @submit.prevent="emit('submit')"
        >
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Story</p>
                    <h2 class="text-lg font-semibold text-primary">Neue Story erstellen</h2>
                </div>
                <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="emit('close')">
                    <i class="las la-times text-2xl"></i>
                </button>
            </div>

            <div class="space-y-3">
                <button
                    type="button"
                    class="flex min-h-52 w-full items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-inputBg text-secondary"
                    @click="mediaInput?.click()"
                >
                    <img
                        v-if="mediaPreview && storyForm.media?.type?.startsWith('image/')"
                        :src="mediaPreview"
                        alt=""
                        class="max-h-[60vh] w-full object-cover"
                    />
                    <video
                        v-else-if="mediaPreview"
                        :src="mediaPreview"
                        class="max-h-[60vh] w-full bg-black"
                        controls
                    ></video>
                    <span v-else class="flex flex-col items-center gap-2 text-sm">
                        <i class="las la-camera text-3xl"></i>
                        Bild oder Video wählen
                    </span>
                </button>

                <input
                    ref="mediaInput"
                    type="file"
                    accept="image/*,video/*"
                    class="hidden"
                    @change="emit('media-change', $event)"
                />

                <textarea
                    v-model="storyForm.caption"
                    rows="3"
                    placeholder="Kurzer Text zur Story..."
                    class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                />
                <p v-if="storyForm.errors.caption" class="text-xs text-error">{{ storyForm.errors.caption }}</p>
                <p v-if="storyForm.errors.media" class="text-xs text-error">{{ storyForm.errors.media }}</p>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <select
                        v-model="storyForm.visibility"
                        class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                    >
                        <option v-for="visibility in visibilityOptions" :key="visibility" :value="visibility">
                            {{ visibilityLabel(visibility) }}
                        </option>
                    </select>

                    <select
                        v-if="storyForm.visibility !== 'public'"
                        v-model="storyForm.club_id"
                        class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                    >
                        <option value="">Verein wählen</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">
                            {{ club.name }}
                        </option>
                    </select>

                    <select
                        v-if="storyForm.visibility === 'team'"
                        v-model="storyForm.team_id"
                        class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary sm:col-span-2"
                    >
                        <option value="">Team wählen</option>
                        <option v-for="team in availableTeams" :key="team.id" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>

                    <select
                        v-if="storyForm.visibility !== 'public'"
                        v-model="storyForm.publisher_type"
                        class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary sm:col-span-2"
                    >
                        <option v-for="option in publisherOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary"
                    @click="emit('close')"
                >
                    Abbrechen
                </button>
                <button
                    type="submit"
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="submitDisabled"
                >
                    Story posten
                </button>
            </div>
        </form>
    </div>
</template>

