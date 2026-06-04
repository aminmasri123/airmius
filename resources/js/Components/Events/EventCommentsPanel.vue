<script setup>
defineProps({
    event: { type: Object, required: true },
    commentForm: { type: Object, required: true },
    submitComment: { type: Function, required: true },
})
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-5">
        <h2 class="text-lg font-semibold text-primary">Diskussion</h2>
        <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="submitComment">
            <input v-model="commentForm.content" class="min-w-0 flex-1 rounded-lg border-border bg-inputBg text-primary" placeholder="Kommentar schreiben" required />
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary" :disabled="commentForm.processing">
                Senden
            </button>
        </form>
        <div class="mt-4 divide-y divide-border">
            <div v-for="comment in event.comments" :key="comment.id" class="py-3">
                <div class="text-sm font-semibold text-primary">{{ comment.user.name }}</div>
                <p class="mt-1 whitespace-pre-line text-sm text-secondary">{{ comment.content }}</p>
            </div>
            <div v-if="!event.comments?.length" class="py-4 text-sm text-secondary">Noch keine Kommentare.</div>
        </div>
    </section>
</template>
