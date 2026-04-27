<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

const props = defineProps({
    posts: { type: Object, default: () => ({ data: [], links: [] }) },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => ['team', 'organization', 'public'] },
    activities: { type: Array, default: () => [] },
})

const user = usePage().props.auth?.user
const imageInput = ref(null)
const attachmentInput = ref(null)
const imagePreview = ref(null)
const commentForms = reactive({})
const editForms = reactive({})
const postForm = useForm({
    club_id: '',
    team_id: '',
    visibility: 'public',
    content: '',
    image: null,
    attachments: [],
})

const canPost = computed(() => Boolean(postForm.content.trim() || postForm.image || postForm.attachments.length))

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
const storageUrl = (path) => path?.startsWith('http') ? path : `/storage/${path}`
const fileName = (path) => (path || '').split('/').pop()

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
}

const createPost = () => {

    // ❌ VALIDIERUNG
    if (postForm.visibility === 'organization' && !postForm.club_id) {


        return
    }
    if (!canPost.value) return
    postForm.post(route('auth.posts.store'), {
        forceFormData: true,
        preserveScroll: true,
        only: ['posts', 'clubs', 'teams', 'activities', 'notificationCenter', 'auth', 'errors'],
        onSuccess: resetCreateForm,
    })
}

const commentFormFor = (postId) => {
    commentForms[postId] ??= useForm({ content: '' })
    return commentForms[postId]
}

const editFormFor = (post) => {
    editForms[post.id] ??= useForm({
        _method: 'PUT',
        club_id: post.club_id || '',
        team_id: post.team_id || '',
        visibility: post.visibility || 'organization',
        content: post.content || '',
        image: null,
        attachments: [],
        editing: false,
    })
    return editForms[post.id]
}

const updatePost = (post) => {
    const form = editFormFor(post)
    form.post(route('auth.posts.update', post.id), {
        forceFormData: true,
        preserveScroll: true,
        only: ['posts', 'clubs', 'teams', 'activities', 'notificationCenter', 'auth', 'errors'],
        onSuccess: () => {
            form.editing = false
            form.reset('image', 'attachments')
        },
    })
}

const deletePost = (post) => router.delete(route('auth.posts.destroy', post.id), { preserveScroll: true, only: ['posts', 'activities', 'notificationCenter', 'auth'] })

const toggleLike = (post) => {
    const wasLiked = post.liked_by_me
    post.liked_by_me = !wasLiked
    post.likes_count += post.liked_by_me ? 1 : -1

    router.post(route('auth.posts.like', post.id), {}, {
        preserveScroll: true,
        only: ['posts', 'activities', 'notificationCenter', 'auth'],
        onError: () => {
            post.liked_by_me = wasLiked
            post.likes_count += wasLiked ? 1 : -1
        },
    })
}

const createComment = (post) => {
    const form = commentFormFor(post.id)
    if (!form.content.trim()) return
    form.post(route('auth.comments.store', post.id), {
        preserveScroll: true,
        only: ['posts', 'activities', 'notificationCenter', 'auth'],
        onSuccess: () => form.reset(),
    })
}

const visitPage = (url) => url && router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    only: ['posts', 'clubs', 'teams', 'activities', 'notificationCenter', 'auth'],
})
</script>

<template>
    <AppLayout title="Feed">

        <Head title="Feed" />

        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
            <section class="space-y-4">
                <form class="surface-card p-4" @submit.prevent="createPost">
                    <div class="flex gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(user?.name) }}
                        </div>

                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="grid gap-2 sm:grid-cols-3">
                                <select v-model="postForm.visibility"
                                    class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="visibility in visibilities" :key="visibility" :value="visibility">{{
                                        visibility }}</option>
                                </select>
                                <select v-model="postForm.club_id" :class="[
                                    'rounded-lg border bg-inputBg px-3 py-2 text-sm text-primary',
                                    postForm.visibility === 'organization' && !postForm.club_id
                                        ? 'border-red-500'
                                        : 'border-border'
                                ]">
                                    <option value="">No organization</option>
                                    <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}
                                    </option>
                                </select>
                                <p v-if="errorMessage" class="text-sm text-red-500">
                                    {{ errorMessage }}
                                </p>
                                <select v-model="postForm.team_id" :class="[
                                    'rounded-lg border bg-inputBg px-3 py-2 text-sm text-primary',
                                    postForm.visibility === 'team' && !postForm.team_id
                                        ? 'border-red-500'
                                        : 'border-border'
                                ]">
                                    <option value="">No team</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}
                                    </option>
                                </select>
                            </div>

                            <textarea v-model="postForm.content" rows="4" placeholder="Was gibt es Neues?"
                                class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary" />

                            <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImage" />
                            <input ref="attachmentInput" type="file" multiple class="hidden"
                                @change="handleAttachments" />

                            <img v-if="imagePreview" :src="imagePreview" alt="Preview"
                                class="max-h-[420px] w-full rounded-xl border border-border object-cover" />

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button"
                                        class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary"
                                        @click="imageInput?.click()">
                                        <i class="las la-image"></i> Bild
                                    </button>
                                    <button type="button"
                                        class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary"
                                        @click="attachmentInput?.click()">
                                        <i class="las la-paperclip"></i> Dateien
                                    </button>
                                    <span v-if="postForm.attachments.length"
                                        class="rounded-lg bg-muted px-3 py-2 text-xs text-secondary">{{
                                            postForm.attachments.length }} Datei(en)</span>
                                </div>

                                <button type="submit" :disabled="postForm.processing || !canPost |
                                    (postForm.visibility === 'organization' && !postForm.club_id) ||
                                    (postForm.visibility === 'team' && !postForm.team_id)"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50">
                                    Posten
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <article v-for="post in posts.data" :key="post.id" class="surface-card overflow-hidden">
                    <div class="flex items-start justify-between gap-4 p-4">
                        <div class="flex min-w-0 gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold text-primary">
                                {{ initials(post.user?.name) }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold text-primary">{{ post.user?.name }}</h2>
                                <p class="text-xs text-secondary">{{ post.team?.name || post.club?.name || 'Public' }} ·
                                    {{ post.visibility }} · {{ formatDate(post.created_at) }}</p>
                            </div>
                        </div>

                        <div v-if="post.user_id === user?.id" class="flex gap-1">
                            <button class="rounded p-2 text-secondary hover:bg-muted hover:text-primary"
                                @click="editFormFor(post).editing = !editFormFor(post).editing">
                                <i class="las la-edit"></i>
                            </button>
                            <button class="rounded p-2 text-secondary hover:bg-error/10 hover:text-error"
                                @click="deletePost(post)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>

                    <form v-if="editFormFor(post).editing" class="space-y-3 px-4 pb-4"
                        @submit.prevent="updatePost(post)">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <select v-model="editFormFor(post).visibility"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option v-for="visibility in visibilities" :key="visibility" :value="visibility">{{
                                    visibility }}</option>
                            </select>
                            <select v-model="editFormFor(post).club_id"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="">No organization</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                            </select>
                            <select v-model="editFormFor(post).team_id"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="">No team</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </div>
                        <textarea v-model="editFormFor(post).content" rows="3"
                            class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" />
                        <input type="file" multiple class="block w-full text-sm text-secondary"
                            @change="editFormFor(post).attachments = Array.from($event.target.files || [])" />
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary"
                                :disabled="editFormFor(post).processing">Speichern</button>
                            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary"
                                @click="editFormFor(post).editing = false">Abbrechen</button>
                        </div>
                    </form>

                    <div v-else class="px-4 pb-4">
                        <p v-if="post.content" class="whitespace-pre-line text-sm leading-6 text-primary">{{
                            post.content }}</p>
                        <img v-if="post.image" :src="storageUrl(post.image)" alt=""
                            class="mt-4 max-h-[520px] w-full rounded-xl border border-border object-cover" />
                        <div v-if="post.attachments?.length" class="mt-4 space-y-2">
                            <a v-for="attachment in post.attachments" :key="attachment.id"
                                :href="storageUrl(attachment.file.path)" target="_blank"
                                class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <i class="las la-paperclip"></i>
                                <span class="min-w-0 flex-1 truncate">{{ fileName(attachment.file.path) }}</span>
                                <span class="text-xs text-secondary">{{ attachment.file.type }}</span>
                            </a>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-y border-border px-4 py-2 text-sm text-secondary">
                        <span>{{ post.likes_count }} Likes</span>
                        <span>{{ post.comments_count }} Kommentare</span>
                    </div>

                    <div class="grid grid-cols-2 border-b border-border">
                        <button type="button"
                            class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium hover:bg-muted"
                            :class="post.liked_by_me ? 'text-error' : 'text-primary'" @click="toggleLike(post)">
                            <i :class="post.liked_by_me ? 'las la-heart' : 'lar la-heart'"></i>
                            Like
                        </button>
                        <button type="button"
                            class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-primary hover:bg-muted"
                            @click="commentFormFor(post.id)">
                            <i class="lar la-comment"></i>
                            Kommentar
                        </button>
                    </div>

                    <div class="space-y-3 p-4">
                        <div v-for="comment in post.comments" :key="comment.id" class="flex gap-3">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-inputBg text-xs font-semibold text-primary">
                                {{ initials(comment.user?.name) }}
                            </div>
                            <div class="min-w-0 flex-1 rounded-lg bg-inputBg px-3 py-2">
                                <span class="text-xs font-semibold text-primary">{{ comment.user?.name }}</span>
                                <p class="mt-1 whitespace-pre-line text-sm text-primary">{{ comment.content }}</p>
                            </div>
                        </div>

                        <form class="flex gap-2" @submit.prevent="createComment(post)">
                            <input v-model="commentFormFor(post.id).content" type="text"
                                placeholder="Kommentar schreiben..."
                                class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" />
                            <button type="submit"
                                :disabled="commentFormFor(post.id).processing || !commentFormFor(post.id).content.trim()"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-buttonTextPrimary disabled:opacity-50">
                                <i class="las la-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </article>

                <div v-if="posts.data.length === 0" class="surface-card p-8 text-center text-secondary">
                    Noch keine Beiträge vorhanden.
                </div>

                <div v-if="posts.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                    <button v-for="link in posts.links" :key="link.label" type="button" :disabled="!link.url"
                        class="min-w-10 rounded border border-border px-3 py-2 text-sm disabled:opacity-50"
                        :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-muted'"
                        @click="visitPage(link.url)" v-html="link.label" />
                </div>
            </section>

            <aside class="space-y-4">
                <div class="surface-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Deine Vereine</h2>
                    <div class="mt-4 space-y-2">
                        <div v-for="club in clubs" :key="club.id"
                            class="flex items-center gap-3 rounded-lg border border-border bg-inputBg p-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(club.name) }}
                            </div>
                            <span class="min-w-0 truncate text-sm font-medium text-primary">{{ club.name }}</span>
                        </div>
                    </div>
                </div>

                <div class="surface-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Activity Feed</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="activity in activities" :key="activity.id"
                            class="rounded-lg border border-border bg-inputBg p-3">
                            <div class="text-sm font-medium text-primary">{{ activity.user?.name || 'System' }}</div>
                            <div class="text-xs text-secondary">{{ activity.type }} · {{ formatDate(activity.created_at)
                                }}</div>
                        </div>
                        <div v-if="!activities.length" class="text-sm text-secondary">No activity yet.</div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
