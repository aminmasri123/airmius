<script setup>
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canCreate: { type: Boolean, default: false },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => ['team', 'organization', 'public', 'friends', 'private'] },
    postTypes: { type: Array, default: () => ['normal'] },
    sports: { type: Array, default: () => [] },
})

const page = usePage()
const { t, te } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}
const user = computed(() => page.props.auth?.user)
const imageInput = ref(null)
const attachmentInput = ref(null)
const imagePreview = ref(null)
const showPostModal = ref(false)
const showComposerAdvanced = ref(false)
const composerNotice = ref('')

const postForm = useForm({
    club_id: '',
    team_id: '',
    visibility: user.value?.default_post_visibility || 'public',
    post_type: 'normal',
    content_origin: 'self',
    sport_id: '',
    sport_skill_ids: [],
    content: '',
    image: null,
    attachments: [],
})

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const canPost = computed(() => Boolean(postForm.content.trim() || postForm.image || postForm.attachments.length))
const postTypeLabel = (type) => tx(`feed.types.${type}`, type)
const visibilityLabel = (visibility) => tx(`feed.visibility.${visibility}`, visibility)
const visibilityHint = (visibility) => ({
    friends: tx('feed.visibility.friends', 'Nur Freunde'),
    private: tx('Nur für dich sichtbar', 'Nur für dich sichtbar'),
    public: tx('Sichtbar für dein Netzwerk und passende öffentliche Feed-Kontexte', 'Sichtbar für dein Netzwerk und passende öffentliche Feed-Kontexte'),
    organization: tx('Sichtbar für Mitglieder des ausgewählten Vereins', 'Sichtbar für Mitglieder des ausgewählten Vereins'),
    team: tx('Sichtbar für Mitglieder des ausgewählten Teams', 'Sichtbar für Mitglieder des ausgewählten Teams'),
}[visibility] || '')
const selectedCreateSport = computed(() => props.sports.find((sport) => String(sport.id) === String(postForm.sport_id)))
const createSportSkills = computed(() => selectedCreateSport.value?.skills || [])
const postBlockReason = computed(() => {
    if (!canPost.value) return tx('Schreibe einen Text oder füge ein Bild, Video oder eine Datei hinzu.', 'Schreibe einen Text oder füge ein Bild, Video oder eine Datei hinzu.')
    if (postForm.visibility === 'organization' && !postForm.club_id) return tx('Wähle einen Verein für einen Vereinsbeitrag.', 'Wähle einen Verein für einen Vereinsbeitrag.')
    if (postForm.visibility === 'team' && !postForm.team_id) return tx('Wähle ein Team für einen Teambeitrag.', 'Wähle ein Team für einen Teambeitrag.')

    return ''
})
const sportLabel = (sport) => {
    if (!sport) return ''
    const key = `sports.${sport.slug}`
    return te(key) ? t(key) : sport.name
}

watch(() => postForm.sport_id, () => {
    postForm.sport_skill_ids = []
})

watch(() => postForm.visibility, (visibility) => {
    if (visibility !== 'organization') postForm.club_id = ''
    if (visibility !== 'team') postForm.team_id = ''
})

watch(() => [postForm.visibility, postForm.club_id, postForm.team_id, postForm.content, postForm.image, postForm.attachments.length], () => {
    composerNotice.value = ''
})

const handleImage = (event) => {
    const file = event.target.files?.[0] || null
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    postForm.image = file
    imagePreview.value = file ? URL.createObjectURL(file) : null
}

const handleAttachments = (event) => {
    postForm.attachments = Array.from(event.target.files || [])
}

const resetCreateForm = () => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    imagePreview.value = null
    if (imageInput.value) imageInput.value.value = null
    if (attachmentInput.value) attachmentInput.value.value = null
    postForm.reset('content', 'image', 'attachments')
    postForm.visibility = user.value?.default_post_visibility || 'public'
    postForm.content_origin = 'self'
    postForm.sport_skill_ids = []
    composerNotice.value = ''
    showComposerAdvanced.value = false
}

const closeComposer = () => {
    showPostModal.value = false
}

const submitPost = () => {
    if (postBlockReason.value) {
        composerNotice.value = postBlockReason.value
        if (postForm.visibility !== 'public') {
            showComposerAdvanced.value = true
        }
        return
    }

    postForm.post(route('auth.posts.store'), {
        forceFormData: true,
        preserveScroll: true,
        only: ['posts', 'clubs', 'teams', 'notificationCenter', 'auth', 'errors'],
        onSuccess: () => {
            resetCreateForm()
            closeComposer()
        },
    })
}
</script>

<template>
    <button
        v-if="canCreate"
        type="button"
        class="surface-card flex w-full min-w-0 items-center gap-3 p-4 text-left"
        @click="postForm.visibility = user?.default_post_visibility || 'public'; showPostModal = true"
    >
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

        <span class="min-w-0 flex-1 truncate rounded-full border border-border bg-inputBg px-4 py-3 text-sm text-secondary">
            {{ tx('Was gibt es Neues?', 'Was gibt es Neues?') }}
        </span>
    </button>

    <Teleport to="body">
        <div
            v-if="showPostModal"
            class="fixed inset-0 z-[80] flex items-end overflow-y-auto bg-black/60 px-3 py-4 sm:items-center sm:px-4"
            @click.self="closeComposer"
        >
            <div
                class="mx-auto max-h-[calc(100dvh-2rem)] w-full max-w-[min(42rem,100%)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-card p-4 shadow-xl sm:p-5"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="min-w-0 truncate text-lg font-semibold text-primary">{{ tx('Beitrag erstellen', 'Beitrag erstellen') }}</h2>

                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary"
                        @click="closeComposer"
                    >
                        <i class="las la-times text-2xl"></i>
                    </button>
                </div>

                <form class="space-y-3" @submit.prevent="submitPost">
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
                            <p class="text-xs text-secondary">{{ tx('Neuer Beitrag', 'Neuer Beitrag') }}</p>
                        </div>
                    </div>

                    <textarea
                        v-model="postForm.content"
                        rows="5"
                        :placeholder="tx('Was gibt es Neues?', 'Was gibt es Neues?')"
                        class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary placeholder-secondary"
                    />

                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted sm:inline-flex sm:w-auto sm:justify-start"
                        @click="showComposerAdvanced = !showComposerAdvanced"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <i class="las la-sliders-h shrink-0"></i>
                            <span class="truncate">{{ tx('Zielgruppe, Sport & Typ', 'Zielgruppe, Sport & Typ') }}</span>
                        </span>
                        <i :class="showComposerAdvanced ? 'las la-angle-up' : 'las la-angle-down'"></i>
                    </button>

                    <div v-if="showComposerAdvanced" class="space-y-3 rounded-lg border border-border bg-inputBg/40 p-3">
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
                                <option value="self">{{ tx('Von mir selbst erstellt', 'Von mir selbst erstellt') }}</option>
                                <option value="ai">{{ tx('Mit KI erstellt', 'Mit KI erstellt') }}</option>
                            </select>

                            <select
                                v-model="postForm.club_id"
                                v-if="postForm.visibility === 'organization'"
                                :class="[
                                    'w-full rounded-lg border bg-inputBg px-3 py-3 text-sm text-primary',
                                    postForm.visibility === 'organization' && !postForm.club_id
                                        ? 'border-red-500'
                                        : 'border-border'
                                ]"
                            >
                                <option value="">{{ tx('events.none.club', 'Kein Verein') }}</option>
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
                                v-if="postForm.visibility === 'team'"
                                :class="[
                                    'w-full rounded-lg border bg-inputBg px-3 py-3 text-sm text-primary',
                                    postForm.visibility === 'team' && !postForm.team_id
                                        ? 'border-red-500'
                                        : 'border-border'
                                ]"
                            >
                                <option value="">{{ tx('events.none.team', 'Kein Team') }}</option>
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
                            :placeholder="tx('Sportart zum Beitrag', 'Sportart zum Beitrag')"
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
                                {{ tx('Keine Skills für {sport}.', 'Keine Skills für {sport}.', { sport: sportLabel(selectedCreateSport) || tx('diese Sportart', 'diese Sportart') }) }}
                            </span>

                            <span
                                v-if="!postForm.sport_id"
                                class="min-w-0 break-words text-sm text-secondary"
                            >
                                {{ tx('Optional: Sportart wählen, um passende Skills zu markieren.', 'Optional: Sportart wählen, um passende Skills zu markieren.') }}
                            </span>
                        </div>
                    </div>

                    <input
                        ref="imageInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="handleImage"
                    />

                    <input
                        ref="attachmentInput"
                        type="file"
                        multiple
                        accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                        class="hidden"
                        @change="handleAttachments"
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
                            {{ tx('Bild', 'Bild') }}
                        </button>

                        <button
                            type="button"
                            class="min-w-0 rounded-lg border border-border bg-card px-3 py-3 text-sm font-semibold text-primary sm:w-auto sm:px-4"
                            @click="attachmentInput?.click()"
                        >
                            <i class="las la-video"></i>
                            <span class="hidden sm:inline">{{ tx('Video / Dateien', 'Video / Dateien') }}</span>
                            <span class="sm:hidden">{{ tx('Dateien', 'Dateien') }}</span>
                        </button>

                        <span
                            v-if="postForm.attachments.length"
                            class="col-span-2 rounded-lg bg-muted px-3 py-2 text-xs text-secondary sm:col-span-1"
                        >
                            {{ postForm.attachments.length }} Datei(en)
                        </span>

                        <span class="col-span-2 rounded-lg bg-inputBg px-3 py-2 text-xs text-secondary sm:col-span-1">
                            {{ tx('Bilder optimiert, Videos bis 50 MB', 'Bilder optimiert, Videos bis 50 MB') }}
                        </span>
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <AppLoadingState
                            v-if="postForm.processing"
                            class="sm:mr-auto"
                            :label="tx('Post wird veröffentlicht...', 'Post wird veröffentlicht...')"
                            inline
                        />

                        <AppButton
                            type="button"
                            variant="secondary"
                            size="lg"
                            class="w-full sm:w-auto"
                            :disabled="postForm.processing"
                            @click="closeComposer"
                        >
                            {{ tx('Abbrechen', 'Abbrechen') }}
                        </AppButton>

                        <AppButton
                            type="submit"
                            :disabled="postForm.processing || Boolean(postBlockReason)"
                            :loading="postForm.processing"
                            size="lg"
                            class="w-full sm:w-auto"
                        >
                            {{ postForm.processing ? tx('Postet...', 'Postet...') : tx('Posten', 'Posten') }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
