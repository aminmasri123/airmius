<script setup>
import { router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    canCreate: { type: Boolean, default: false },
    stories: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => ['team', 'organization', 'public'] },
    user: { type: Object, default: null },
})

const emit = defineEmits(['report-story'])

const storyDuration = 6500
const progressIntervalMs = 80

const showCreateModal = ref(false)
const activeGroupIndex = ref(null)
const activeStoryIndex = ref(0)
const storyProgress = ref(0)
const progressTimer = ref(null)
const mediaPreview = ref(null)
const mediaInput = ref(null)
const storyViewer = ref(null)
const storyVideo = ref(null)
const storyPaused = ref(false)
const storyUploadNotice = ref(null)
const storyUploading = ref(false)
let storyUploadNoticeTimer = null

const storyForm = useForm({
    visibility: props.visibilities.includes('public') ? 'public' : props.visibilities[0] || 'organization',
    publisher_type: 'user',
    club_id: '',
    team_id: '',
    caption: '',
    media: null,
})

const visibilityOptions = computed(() => props.visibilities.filter((visibility) => ['public', 'organization', 'team'].includes(visibility)))
const selectedTeam = computed(() => storyForm.visibility === 'team'
    ? props.teams.find((team) => String(team.id) === String(storyForm.team_id)) || (props.teams.length === 1 ? props.teams[0] : null)
    : null)
const selectedClub = computed(() => {
    const clubId = storyForm.club_id || selectedTeam.value?.club_id

    return props.clubs.find((club) => String(club.id) === String(clubId)) || (props.clubs.length === 1 ? props.clubs[0] : null)
})
const availableTeams = computed(() => {
    if (!storyForm.club_id) {
        return props.teams
    }

    return props.teams.filter((team) => String(team.club_id) === String(storyForm.club_id))
})
const publisherOptions = computed(() => {
    const options = [{ value: 'user', label: 'Als ich' }]

    if (selectedClub.value?.can_publish_as) {
        options.push({ value: 'club', label: 'Als Verein' })
    }

    if (selectedTeam.value?.can_publish_as) {
        options.push({ value: 'team', label: 'Als Team' })
    }

    return options
})
const hasMedia = computed(() => Boolean(storyForm.media))
const storyGroups = computed(() => {
    const groups = new Map()

    props.stories.forEach((story) => {
        const key = story.actor?.key || `user:${story.user_id}`

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                actor: story.actor || {
                    key,
                    type: 'user',
                    name: story.user?.name,
                    profile_photo_thumb: story.user?.profile_photo_thumb,
                },
                user_id: story.user_id,
                stories: [],
                latest_id: story.id,
            })
        }

        const group = groups.get(key)
        group.stories.push(story)
        group.latest_id = Math.max(group.latest_id, story.id)
    })

    return Array.from(groups.values())
        .map((group) => ({
            ...group,
            stories: group.stories.sort((a, b) => a.id - b.id),
            viewed: group.stories.every((story) => story.viewed_by_me || story.user_id === props.user?.id),
        }))
        .sort((a, b) => Number(a.viewed) - Number(b.viewed) || b.latest_id - a.latest_id)
})
const hasStories = computed(() => storyGroups.value.length > 0)
const activeGroup = computed(() => activeGroupIndex.value === null ? null : storyGroups.value[activeGroupIndex.value] || null)
const activeStory = computed(() => activeGroup.value?.stories[activeStoryIndex.value] || null)
const activeStoryCount = computed(() => activeGroup.value?.stories.length || 0)
const isVideoStory = computed(() => activeStory.value?.media_kind === 'video')

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const storyName = (storyOrGroup) => storyOrGroup?.actor?.type === 'user' && storyOrGroup?.user_id === props.user?.id
    ? 'Deine Story'
    : storyOrGroup?.actor?.name || storyOrGroup?.user?.name
const visibilityLabel = (visibility) => ({
    public: 'öffentlich',
    organization: 'Verein',
    team: 'Team',
}[visibility] || visibility)
const reactionOptions = [
    { key: 'clap', label: 'Applaus', icon: 'las la-sign-language' },
    { key: 'fire', label: 'Stark', icon: 'las la-fire' },
    { key: 'heart', label: 'Liebe', icon: 'las la-heart' },
    { key: 'strong', label: 'Power', icon: 'las la-dumbbell' },
    { key: 'wow', label: 'Wow', icon: 'las la-star' },
]

watch(() => storyForm.visibility, (visibility) => {
    if (visibility !== 'team') {
        storyForm.team_id = ''
        if (storyForm.publisher_type === 'team') {
            storyForm.publisher_type = 'user'
        }
    }

    if (visibility === 'public') {
        storyForm.club_id = ''
        if (storyForm.publisher_type !== 'user') {
            storyForm.publisher_type = 'user'
        }
    }
})

watch(() => storyForm.club_id, (clubId) => {
    if (!storyForm.team_id || !clubId) return

    const teamBelongsToClub = props.teams.some((team) => String(team.id) === String(storyForm.team_id) && String(team.club_id) === String(clubId))

    if (!teamBelongsToClub) {
        storyForm.team_id = ''
    }
})

watch(activeStory, (story) => {
    if (story) {
        markStoryViewed(story)
        storyProgress.value = 0
        storyPaused.value = false

        if (story.media_kind === 'video') {
            stopProgress()
        } else {
            startProgress()
        }
    } else {
        stopProgress()
    }
})

watch(publisherOptions, (options) => {
    if (!options.some((option) => option.value === storyForm.publisher_type)) {
        storyForm.publisher_type = 'user'
    }
})

onBeforeUnmount(() => {
    stopProgress()

    if (mediaPreview.value) {
        URL.revokeObjectURL(mediaPreview.value)
    }

    if (storyUploadNoticeTimer) {
        window.clearTimeout(storyUploadNoticeTimer)
    }
})

const openCreate = () => {
    storyForm.clearErrors()
    showCreateModal.value = true
}

const closeCreate = () => {
    showCreateModal.value = false
    resetForm()
}

const resetForm = () => {
    if (mediaPreview.value) {
        URL.revokeObjectURL(mediaPreview.value)
    }

    mediaPreview.value = null
    storyForm.reset('club_id', 'team_id', 'caption', 'media', 'publisher_type')
    storyForm.visibility = props.visibilities.includes('public') ? 'public' : props.visibilities[0] || 'organization'
    storyForm.publisher_type = 'user'

    if (mediaInput.value) {
        mediaInput.value.value = null
    }
}

const handleMedia = (event) => {
    const file = event.target.files?.[0] || null

    if (mediaPreview.value) {
        URL.revokeObjectURL(mediaPreview.value)
    }

    storyForm.media = file
    mediaPreview.value = file ? URL.createObjectURL(file) : null
}

const showStoryUploadNotice = (type, message) => {
    storyUploadNotice.value = { type, message }

    if (storyUploadNoticeTimer) {
        window.clearTimeout(storyUploadNoticeTimer)
    }

    if (type !== 'info') {
        storyUploadNoticeTimer = window.setTimeout(() => {
            storyUploadNotice.value = null
        }, 4200)
    }
}

const submitStory = () => {
    if (!storyForm.media) return

    storyForm.clearErrors()

    storyForm.post(route('auth.stories.store'), {
        forceFormData: true,
        preserveScroll: true,
        only: ['stories', 'notificationCenter', 'auth', 'flash', 'errors'],
        onStart: () => {
            storyUploading.value = true
            showCreateModal.value = false
            showStoryUploadNotice('info', 'Story wird hochgeladen...')
        },
        onSuccess: () => {
            resetForm()
            showStoryUploadNotice('success', 'Story wurde gepostet.')
        },
        onError: () => {
            showCreateModal.value = true
            showStoryUploadNotice('error', 'Story konnte nicht gepostet werden. Bitte prüfe die Felder.')
        },
        onFinish: () => {
            storyUploading.value = false
        },
    })
}

const openGroup = (group) => {
    const index = storyGroups.value.findIndex((item) => item.key === group.key)
    if (index < 0) return

    activeGroupIndex.value = index
    activeStoryIndex.value = Math.max(0, group.stories.findIndex((story) => !story.viewed_by_me && story.user_id !== props.user?.id))

    nextTick(() => storyViewer.value?.focus())
}

const pauseStory = () => {
    if (!activeStory.value) return

    storyPaused.value = true
    stopProgress()
    storyVideo.value?.pause?.()
}

const resumeStory = () => {
    if (!activeStory.value) return

    storyPaused.value = false

    if (isVideoStory.value) {
        storyVideo.value?.play?.().catch(() => {})
        return
    }

    startProgress(false)
}

const toggleStoryPause = () => {
    if (storyPaused.value) {
        resumeStory()
    } else {
        pauseStory()
    }
}

const closeStory = () => {
    activeGroupIndex.value = null
    activeStoryIndex.value = 0
    storyPaused.value = false
    stopProgress()
}

const markStoryViewed = (story) => {
    if (story.viewed_by_me || story.user_id === props.user?.id) return

    story.viewed_by_me = true
    router.post(route('auth.stories.viewed', story.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['stories', 'notificationCenter', 'auth'],
    })
}

const startProgress = (reset = true) => {
    stopProgress()

    if (reset) {
        storyProgress.value = 0
    }

    storyPaused.value = false

    progressTimer.value = window.setInterval(() => {
        storyProgress.value += (progressIntervalMs / storyDuration) * 100

        if (storyProgress.value >= 100) {
            nextStory()
        }
    }, progressIntervalMs)
}

const stopProgress = () => {
    if (progressTimer.value) {
        window.clearInterval(progressTimer.value)
        progressTimer.value = null
    }
}

const syncVideoProgress = (event) => {
    const video = event.target

    if (!video?.duration) return

    storyProgress.value = Math.min((video.currentTime / video.duration) * 100, 100)
}

const previousStory = () => {
    if (!activeGroup.value) return

    if (activeStoryIndex.value > 0) {
        activeStoryIndex.value -= 1
        return
    }

    if (activeGroupIndex.value > 0) {
        activeGroupIndex.value -= 1
        activeStoryIndex.value = Math.max(0, (storyGroups.value[activeGroupIndex.value]?.stories.length || 1) - 1)
    }
}

const nextStory = () => {
    if (!activeGroup.value) return

    if (activeStoryIndex.value < activeStoryCount.value - 1) {
        activeStoryIndex.value += 1
        return
    }

    if (activeGroupIndex.value < storyGroups.value.length - 1) {
        activeGroupIndex.value += 1
        activeStoryIndex.value = 0
        return
    }

    closeStory()
}

const deleteStory = () => {
    if (!activeStory.value?.can_delete) return

    router.delete(route('auth.stories.destroy', activeStory.value.id), {
        preserveScroll: true,
        only: ['stories', 'notificationCenter', 'auth', 'flash'],
        onSuccess: closeStory,
    })
}

const reportStory = () => {
    if (!activeStory.value) return

    emit('report-story', activeStory.value)
    closeStory()
}

const reactToStory = (reaction) => {
    if (!activeStory.value || activeStory.value.user_id === props.user?.id) return

    const previousReaction = activeStory.value.my_reaction
    const previousCount = activeStory.value.reactions_count || 0

    activeStory.value.my_reaction = reaction
    activeStory.value.reactions_count = previousReaction ? previousCount : previousCount + 1
    router.post(route('auth.stories.react', activeStory.value.id), { reaction }, {
        preserveScroll: true,
        preserveState: true,
        only: ['stories', 'notificationCenter', 'auth'],
        onError: () => {
            activeStory.value.my_reaction = previousReaction
            activeStory.value.reactions_count = previousCount
        },
    })
}
</script>

<template>
    <div class="surface-card overflow-hidden p-3">
        <div class="custom-scrollbar flex gap-3 overflow-x-auto">
            <button
                v-if="canCreate"
                type="button"
                class="flex w-20 shrink-0 flex-col items-center gap-2 text-center"
                @click="openCreate"
            >
                <span class="flex h-14 w-14 items-center justify-center rounded-full border border-dashed border-border bg-inputBg text-primary">
                    <i class="las la-plus text-xl"></i>
                </span>
                <span class="line-clamp-2 text-xs font-semibold text-primary">Story</span>
            </button>

            <button
                v-for="group in storyGroups"
                :key="group.key"
                type="button"
                class="flex w-20 shrink-0 flex-col items-center gap-2 text-center"
                @click="openGroup(group)"
            >
                <span
                    class="relative rounded-full p-0.5"
                    :class="group.viewed ? 'bg-border' : 'bg-buttonPrimary'"
                >
                    <img
                        v-if="group.actor?.profile_photo_thumb"
                        :src="group.actor.profile_photo_thumb"
                        :alt="group.actor.name"
                        class="h-14 w-14 rounded-full border-2 border-card object-cover"
                    />
                    <span
                        v-else
                        class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-card bg-inputBg text-sm font-semibold text-primary"
                    >
                        {{ initials(group.actor?.name) }}
                    </span>
                    <span
                        v-if="group.stories.length > 1"
                        class="absolute -bottom-1 -right-1 rounded-full border border-card bg-buttonPrimary px-1.5 py-0.5 text-[10px] font-bold text-buttonTextPrimary"
                    >
                        {{ group.stories.length }}
                    </span>
                </span>
                <span class="line-clamp-2 text-xs font-semibold text-primary">
                    {{ storyName(group) }}
                </span>
            </button>

            <div
                v-if="!hasStories && !canCreate"
                class="flex min-h-20 items-center text-sm text-secondary"
            >
                Noch keine Storys.
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="storyUploadNotice"
                role="status"
                aria-live="polite"
                class="fixed inset-x-3 top-4 z-[90] mx-auto flex max-w-sm items-center gap-3 rounded-xl border px-4 py-3 text-sm font-semibold shadow-xl backdrop-blur"
                :class="storyUploadNotice.type === 'success'
                    ? 'border-success/30 bg-success/95 text-white'
                    : storyUploadNotice.type === 'error'
                        ? 'border-error/30 bg-error/95 text-white'
                        : 'border-border bg-card/95 text-primary'"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15">
                    <i
                        :class="[
                            storyUploadNotice.type === 'success'
                                ? 'las la-check'
                                : storyUploadNotice.type === 'error'
                                    ? 'las la-exclamation-triangle'
                                    : 'las la-spinner la-spin',
                            'text-lg',
                        ]"
                    ></i>
                </span>
                <span>{{ storyUploadNotice.message }}</span>
            </div>

            <div
                v-if="showCreateModal"
                class="fixed inset-0 z-[70] flex items-end bg-black/70 sm:items-center sm:p-4"
                @click.self="closeCreate"
            >
                <form
                    class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-border bg-card p-4 shadow-xl sm:mx-auto sm:max-w-lg sm:rounded-2xl"
                    @submit.prevent="submitStory"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Story</p>
                            <h2 class="text-lg font-semibold text-primary">Neue Story erstellen</h2>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="closeCreate">
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
                            @change="handleMedia"
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
                            @click="closeCreate"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="storyUploading || storyForm.processing || !hasMedia || (storyForm.visibility === 'organization' && !storyForm.club_id) || (storyForm.visibility === 'team' && !storyForm.team_id)"
                        >
                            Story posten
                        </button>
                    </div>
                </form>
            </div>

            <div
                v-if="activeStory"
                ref="storyViewer"
                class="fixed inset-0 z-[75] flex items-center justify-center bg-black/90 p-3"
                @click.self="closeStory"
                @keydown.left.prevent="previousStory"
                @keydown.right.prevent="nextStory"
                @keydown.space.prevent="toggleStoryPause"
                tabindex="0"
            >
                <div class="relative flex h-full max-h-[780px] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-black text-white shadow-2xl">
                    <div class="absolute left-0 right-0 top-0 z-20 bg-gradient-to-b from-black/80 to-transparent p-4">
                        <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${activeStoryCount}, minmax(0, 1fr))` }">
                            <div
                                v-for="(_, index) in activeGroup.stories"
                                :key="index"
                                class="h-1 overflow-hidden rounded-full bg-white/30"
                            >
                                <div
                                    class="h-full rounded-full bg-white"
                                    :style="{ width: index < activeStoryIndex ? '100%' : index === activeStoryIndex ? `${Math.min(storyProgress, 100)}%` : '0%' }"
                                ></div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <img
                                v-if="activeStory.actor?.profile_photo_thumb"
                                :src="activeStory.actor.profile_photo_thumb"
                                :alt="activeStory.actor.name"
                                class="h-9 w-9 rounded-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-xs font-semibold"
                            >
                                {{ initials(activeStory.actor?.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ storyName(activeStory) }}</p>
                                <p class="text-xs text-white/70">
                                    {{ visibilityLabel(activeStory.visibility) }}
                                    <span v-if="activeStory.views_count !== undefined"> &middot; {{ activeStory.views_count }} gesehen</span>
                                    <span v-if="activeStory.reactions_count"> &middot; {{ activeStory.reactions_count }} Reaktionen</span>
                                </p>
                            </div>
                            <button
                                v-if="activeStory.can_delete"
                                type="button"
                                class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                                title="Story löschen"
                                @click="deleteStory"
                            >
                                <i class="las la-trash"></i>
                            </button>
                            <button
                                v-if="activeStory.user_id !== user?.id"
                                type="button"
                                class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                                title="Story melden"
                                @click="reportStory"
                            >
                                <i class="las la-flag"></i>
                            </button>
                            <button
                                type="button"
                                class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                                @click="closeStory"
                            >
                                <i class="las la-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" class="absolute bottom-0 left-0 top-0 z-10 w-1/3" aria-label="Vorherige Story" @click="previousStory" @pointerdown="pauseStory" @pointerup="resumeStory" @pointerleave="resumeStory"></button>
                    <button type="button" class="absolute bottom-0 right-0 top-0 z-10 w-1/3" aria-label="Nächste Story" @click="nextStory" @pointerdown="pauseStory" @pointerup="resumeStory" @pointerleave="resumeStory"></button>

                    <div class="flex min-h-0 flex-1 items-center justify-center">
                        <video
                            v-if="activeStory.media_kind === 'video'"
                            ref="storyVideo"
                            :src="activeStory.media_url"
                            :poster="activeStory.media_thumbnail_url"
                            class="max-h-full w-full"
                            autoplay
                            muted
                            playsinline
                            @loadedmetadata="syncVideoProgress"
                            @timeupdate="syncVideoProgress"
                            @ended="nextStory"
                        ></video>
                        <img
                            v-else
                            :src="activeStory.media_url"
                            :alt="activeStory.caption || ''"
                            class="max-h-full w-full object-contain"
                        />
                    </div>

                    <div v-if="activeStory.caption" class="absolute bottom-0 left-0 right-0 z-20 bg-gradient-to-t from-black/80 to-transparent p-4 pt-16">
                        <p class="whitespace-pre-line break-words text-sm leading-6">{{ activeStory.caption }}</p>
                    </div>

                    <div class="absolute bottom-4 left-4 right-4 z-30 flex items-end justify-between gap-3">
                        <div v-if="activeStory.user_id === user?.id" class="max-w-[55%] rounded-lg bg-black/50 px-3 py-2 text-xs text-white/80">
                            <p class="font-semibold text-white">{{ activeStory.views_count || 0 }} gesehen</p>
                            <p v-if="activeStory.viewer_preview?.length" class="mt-1 truncate">
                                {{ activeStory.viewer_preview.map((viewer) => viewer.name).join(', ') }}
                            </p>
                        </div>

                        <div v-else class="ml-auto flex gap-1 rounded-full bg-black/45 p-1">
                            <button
                                v-for="reaction in reactionOptions"
                                :key="reaction.key"
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-white/80 hover:bg-white/15 hover:text-white"
                                :class="activeStory.my_reaction === reaction.key ? 'bg-white/20 text-white' : ''"
                                :title="reaction.label"
                                @click="reactToStory(reaction.key)"
                            >
                                <i :class="reaction.icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
