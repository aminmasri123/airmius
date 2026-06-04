<script setup>
const props = defineProps({
    canCreate: { type: Boolean, default: false },
    hasStories: { type: Boolean, default: false },
    storyGroups: { type: Array, default: () => [] },
    user: { type: Object, default: null },
})

const emit = defineEmits(['open-create', 'open-group'])

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()

const storyName = (group) => group?.actor?.type === 'user' && group?.user_id === props.user?.id
    ? 'Deine Story'
    : group?.actor?.name || group?.user?.name
</script>

<template>
    <div class="custom-scrollbar flex gap-3 overflow-x-auto">
        <button
            v-if="canCreate"
            type="button"
            class="flex w-20 shrink-0 flex-col items-center gap-2 text-center"
            @click="emit('open-create')"
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
            @click="emit('open-group', group)"
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
</template>
