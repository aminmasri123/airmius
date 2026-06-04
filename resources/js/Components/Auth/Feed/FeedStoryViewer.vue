<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
    activeGroup: { type: Object, default: null },
    activeStory: { type: Object, default: null },
    activeStoryCount: { type: Number, default: 0 },
    activeStoryIndex: { type: Number, default: 0 },
    paused: { type: Boolean, default: false },
    reactionOptions: { type: Array, default: () => [] },
    storyProgress: { type: Number, default: 0 },
    user: { type: Object, default: null },
})

const emit = defineEmits([
    'close',
    'delete',
    'next',
    'pause',
    'previous',
    'react',
    'report',
    'resume',
    'toggle-pause',
    'video-progress',
])

const storyViewer = ref(null)
const storyVideo = ref(null)

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()

const storyName = (story) => story?.actor?.type === 'user' && story?.user_id === props.user?.id
    ? 'Deine Story'
    : story?.actor?.name || story?.user?.name

const visibilityLabel = (visibility) => ({
    public: 'öffentlich',
    organization: 'Verein',
    team: 'Team',
}[visibility] || visibility)

const playVideo = () => storyVideo.value?.play?.().catch(() => {})

watch(() => props.activeStory, (story) => {
    if (!story) return

    nextTick(() => {
        storyViewer.value?.focus()

        if (story.media_kind === 'video' && !props.paused) {
            playVideo()
        }
    })
})

watch(() => props.paused, (paused) => {
    if (!storyVideo.value) return

    if (paused) {
        storyVideo.value.pause()
        return
    }

    playVideo()
})
</script>

<template>
    <div
        v-if="activeStory"
        ref="storyViewer"
        class="fixed inset-0 z-[75] flex items-center justify-center bg-black/90 p-3"
        tabindex="0"
        @click.self="emit('close')"
        @keydown.left.prevent="emit('previous')"
        @keydown.right.prevent="emit('next')"
        @keydown.space.prevent="emit('toggle-pause')"
    >
        <div class="relative flex h-full max-h-[780px] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-black text-white shadow-2xl">
            <div class="absolute left-0 right-0 top-0 z-20 bg-gradient-to-b from-black/80 to-transparent p-4">
                <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${activeStoryCount}, minmax(0, 1fr))` }">
                    <div
                        v-for="(_, index) in activeGroup?.stories || []"
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
                        @click="emit('delete')"
                    >
                        <i class="las la-trash"></i>
                    </button>
                    <button
                        v-if="activeStory.user_id !== user?.id"
                        type="button"
                        class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                        title="Story melden"
                        @click="emit('report')"
                    >
                        <i class="las la-flag"></i>
                    </button>
                    <button
                        type="button"
                        class="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                        @click="emit('close')"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>
            </div>

            <button type="button" class="absolute bottom-0 left-0 top-0 z-10 w-1/3" aria-label="Vorherige Story" @click="emit('previous')" @pointerdown="emit('pause')" @pointerup="emit('resume')" @pointerleave="emit('resume')"></button>
            <button type="button" class="absolute bottom-0 right-0 top-0 z-10 w-1/3" aria-label="Nächste Story" @click="emit('next')" @pointerdown="emit('pause')" @pointerup="emit('resume')" @pointerleave="emit('resume')"></button>

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
                    @loadedmetadata="emit('video-progress', $event)"
                    @timeupdate="emit('video-progress', $event)"
                    @ended="emit('next')"
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
                        @click="emit('react', reaction.key)"
                    >
                        <i :class="reaction.icon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

