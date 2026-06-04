<script setup>
defineProps({
    post: { type: Object, required: true },
})

const emit = defineEmits(['focus-comments', 'toggle-helpful', 'toggle-like'])
</script>

<template>
    <div class="flex flex-col gap-1 border-y border-border px-4 py-2 text-xs text-secondary sm:flex-row sm:items-center sm:justify-between sm:text-sm">
        <span>{{ post.likes_count }} Likes</span>
        <span>{{ post.helpfuls_count }} hilfreich · {{ post.comments_count }} Kommentare</span>
    </div>

    <div class="grid grid-cols-3 border-b border-border text-xs sm:text-sm">
        <button
            type="button"
            class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
            :class="post.liked_by_me ? 'text-error' : 'text-primary'"
            @click="emit('toggle-like')"
        >
            <i :class="post.liked_by_me ? 'las la-heart' : 'lar la-heart'"></i>
            Like
        </button>

        <button
            type="button"
            class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
            :class="post.helpful_by_me ? 'text-air-green' : 'text-primary'"
            @click="emit('toggle-helpful')"
        >
            <i :class="post.helpful_by_me ? 'las la-check-circle' : 'lar la-check-circle'"></i>
            Hilfreich
        </button>

        <button
            type="button"
            class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium text-primary hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
            @click="emit('focus-comments')"
        >
            <i class="lar la-comment"></i>
            Kommentar
        </button>
    </div>
</template>

