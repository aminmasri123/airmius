<script setup>
import { usePermissions } from '@/composables/usePermissions'
import { Link, useForm } from '@inertiajs/vue3'
import { nextTick, reactive } from 'vue'

const props = defineProps({
    post: { type: Object, required: true },
    user: { type: Object, default: null },
})

const emit = defineEmits(['delete-comment', 'report-comment'])

const { can } = usePermissions()
const commentInputs = reactive({})
const commentForms = reactive({})
const commentEditForms = reactive({})
const commentsLoading = reactive({})
const commentsErrors = reactive({})

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const canEditComment = (comment) => comment.user_id === props.user?.id
const canDeleteComment = (comment) => comment.user_id === props.user?.id || props.post.user_id === props.user?.id || can('comment.delete')

const setCommentInput = (element) => {
    if (element) {
        commentInputs[props.post.id] = element
    }
}

const commentFormFor = () => {
    commentForms[props.post.id] ??= useForm({ content: '' })
    return commentForms[props.post.id]
}

const commentEditFormFor = (comment) => {
    commentEditForms[comment.id] ??= useForm({
        content: comment.content || '',
        editing: false,
    })
    return commentEditForms[comment.id]
}

const focusComment = async () => {
    commentFormFor()
    await nextTick()
    commentInputs[props.post.id]?.focus()
}

const loadAllComments = async () => {
    commentsLoading[props.post.id] = true
    commentsErrors[props.post.id] = ''

    try {
        const response = await window.axios.get(route('auth.comments.index', props.post.id))
        props.post.comments = response.data.comments || []
    } catch (error) {
        commentsErrors[props.post.id] = 'Kommentare konnten nicht geladen werden.'
    } finally {
        commentsLoading[props.post.id] = false
    }
}

const createComment = () => {
    const form = commentFormFor()
    if (!form.content.trim()) return

    form.post(route('auth.comments.store', props.post.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth'],
        onSuccess: () => form.reset(),
    })
}

const startEditComment = (comment) => {
    const form = commentEditFormFor(comment)
    form.content = comment.content || ''
    form.editing = true
    form.clearErrors()
}

const cancelEditComment = (comment) => {
    const form = commentEditFormFor(comment)
    form.content = comment.content || ''
    form.editing = false
    form.clearErrors()
}

const updateComment = (comment) => {
    const form = commentEditFormFor(comment)
    if (!form.content.trim()) return

    form.put(route('auth.comments.update', comment.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth', 'flash', 'errors'],
        onSuccess: () => {
            form.editing = false
        },
    })
}

defineExpose({ focusComment })
</script>

<template>
    <div class="space-y-3 p-4">
        <div
            v-for="comment in post.comments"
            :key="comment.id"
            class="flex gap-3"
        >
            <Link
                :href="route('auth.users.show', comment.user.id)"
                class="shrink-0"
            >
                <img
                    v-if="comment.user?.profile_photo_thumb"
                    :src="comment.user.profile_photo_thumb"
                    :alt="comment.user.name"
                    class="h-8 w-8 rounded-full object-cover"
                />

                <div
                    v-else
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary"
                >
                    {{ initials(comment.user?.name) }}
                </div>
            </Link>

            <div class="min-w-0 flex-1 rounded-lg bg-inputBg px-3 py-2">
                <div class="flex items-start justify-between gap-2">
                    <Link
                        :href="route('auth.users.show', comment.user.id)"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        {{ comment.user?.name }}
                    </Link>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            v-if="canEditComment(comment)"
                            type="button"
                            class="rounded px-1 text-xs text-secondary hover:bg-muted hover:text-primary"
                            title="Kommentar bearbeiten"
                            @click="startEditComment(comment)"
                        >
                            <i class="las la-edit"></i>
                        </button>
                        <button
                            v-if="canDeleteComment(comment)"
                            type="button"
                            class="rounded px-1 text-xs text-secondary hover:bg-error/10 hover:text-error"
                            title="Kommentar löschen"
                            @click="emit('delete-comment', comment)"
                        >
                            <i class="las la-trash"></i>
                        </button>
                        <button
                            v-if="comment.user_id !== user?.id"
                            type="button"
                            class="rounded px-1 text-xs text-secondary hover:bg-muted hover:text-primary"
                            title="Kommentar melden"
                            @click="emit('report-comment', comment)"
                        >
                            <i class="las la-flag"></i>
                        </button>
                    </div>
                </div>

                <form
                    v-if="commentEditFormFor(comment).editing"
                    class="mt-2 space-y-2"
                    @submit.prevent="updateComment(comment)"
                >
                    <textarea
                        v-model="commentEditFormFor(comment).content"
                        rows="3"
                        class="w-full resize-none rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary"
                    />
                    <p v-if="commentEditFormFor(comment).errors.content" class="text-xs text-error">
                        {{ commentEditFormFor(comment).errors.content }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                            :disabled="commentEditFormFor(comment).processing || !commentEditFormFor(comment).content.trim()"
                        >
                            Speichern
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                            @click="cancelEditComment(comment)"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>

                <p v-else class="mt-1 whitespace-pre-line break-words text-sm text-primary">
                    {{ comment.content }}
                </p>
            </div>
        </div>

        <div
            v-if="post.comments_count > (post.comments?.length || 0)"
            class="flex flex-col gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-xs text-secondary sm:flex-row sm:items-center sm:justify-between"
        >
            <span>
                Es werden die neuesten {{ post.comments?.length || 0 }} von {{ post.comments_count }} Kommentaren angezeigt.
            </span>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 font-semibold text-primary hover:bg-muted disabled:opacity-50"
                :disabled="commentsLoading[post.id]"
                @click="loadAllComments"
            >
                <i class="las la-comments"></i>
                {{ commentsLoading[post.id] ? 'Lade...' : 'Alle anzeigen' }}
            </button>
        </div>

        <div v-if="commentsErrors[post.id]" class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">
            {{ commentsErrors[post.id] }}
        </div>

        <form
            class="flex gap-2"
            @submit.prevent="createComment"
        >
            <input
                v-model="commentFormFor().content"
                :ref="setCommentInput"
                type="text"
                placeholder="Kommentar schreiben..."
                class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
            />

            <button
                type="submit"
                :disabled="commentFormFor().processing || !commentFormFor().content.trim()"
                class="rounded-lg bg-buttonPrimary px-4 py-3 text-buttonTextPrimary disabled:opacity-50"
            >
                <i class="las la-paper-plane"></i>
            </button>
        </form>
    </div>
</template>

