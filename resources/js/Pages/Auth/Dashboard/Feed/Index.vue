<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'

const props = defineProps({
    posts: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    clubs: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const user = page.props.auth?.user
const fileInput = ref(null)
const imagePreview = ref(null)
const commentForms = reactive({})
let feedInterval = null

const postForm = useForm({
    club_id: '',
    content: '',
    image: null,
})

const canPost = computed(() => Boolean(postForm.content.trim() || postForm.image))

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()

const formatDate = (value) => new Intl.DateTimeFormat('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value))

const commentFormFor = (postId) => {
    if (!commentForms[postId]) {
        commentForms[postId] = useForm({ content: '' })
    }

    return commentForms[postId]
}

const imageUrl = (path) => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://')) return path

    return `/storage/${path}`
}

const handleImage = (event) => {
    const file = event.target.files?.[0] || null

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
        imagePreview.value = null
    }

    postForm.image = file
    imagePreview.value = file ? URL.createObjectURL(file) : null
}

const removeImage = () => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }

    imagePreview.value = null
    postForm.image = null

    if (fileInput.value) {
        fileInput.value.value = null
    }
}

const createPost = () => {
    if (!canPost.value) return

    postForm.post(route('auth.posts.store'), {
        forceFormData: true,
        preserveScroll: true,
        only: ['posts', 'clubs', 'notificationCenter', 'auth', 'errors'],
        onSuccess: () => {
            postForm.reset('content', 'image')
            removeImage()
        },
    })
}

const toggleLike = (post) => {
    const wasLiked = post.liked_by_me

    post.liked_by_me = !wasLiked
    post.likes_count += post.liked_by_me ? 1 : -1

    router.post(route('auth.posts.like', post.id), {}, {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth'],
        onError: () => {
            post.liked_by_me = wasLiked
            post.likes_count += wasLiked ? 1 : -1
        },
    })
}

const createComment = (post) => {
    const form = commentFormFor(post.id)
    const content = form.content.trim()

    if (!content) return

    form.post(route('auth.comments.store', post.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth'],
        onSuccess: () => form.reset('content'),
    })
}

const deletePost = (post) => {
    router.delete(route('auth.posts.destroy', post.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth'],
    })
}

const visitPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        only: ['posts', 'clubs', 'notificationCenter', 'auth'],
    })
}

const refreshFeed = () => {
    if (document.hidden || postForm.processing) return

    router.reload({
        only: ['posts', 'notificationCenter', 'auth'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    feedInterval = window.setInterval(refreshFeed, 5000)
})

onUnmounted(() => {
    if (feedInterval) {
        window.clearInterval(feedInterval)
    }

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }
})
</script>

<template>
    <AppLayout title="Feed">
        <Head title="Feed" />

        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
            <section class="space-y-4">
                <form class="surface-card p-4" @submit.prevent="createPost">
                    <div class="flex gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                            {{ initials(user?.name) }}
                        </div>

                        <div class="min-w-0 flex-1 space-y-3">
                            <select
                                v-model="postForm.club_id"
                                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover sm:max-w-xs"
                            >
                                <option value="">Allgemeiner Post</option>
                                <option v-for="club in clubs" :key="club.id" :value="club.id">
                                    {{ club.name }}
                                </option>
                            </select>

                            <textarea
                                v-model="postForm.content"
                                rows="4"
                                placeholder="Was gibt es Neues?"
                                class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                            />

                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleImage"
                            />

                            <div v-if="imagePreview" class="relative overflow-hidden rounded-xl border border-border">
                                <img :src="imagePreview" alt="Bildvorschau" class="max-h-[420px] w-full object-cover" />
                                <button
                                    type="button"
                                    class="absolute right-3 top-3 rounded-full bg-black/60 px-2 py-1 text-sm text-white backdrop-blur transition hover:bg-black/80"
                                    @click="removeImage"
                                >
                                    <i class="las la-times"></i>
                                </button>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary transition hover:border-borderHover"
                                        @click="fileInput?.click()"
                                    >
                                        <i class="las la-image"></i>
                                        Bild auswählen
                                    </button>
                                    <span v-if="postForm.image" class="inline-flex items-center rounded-lg bg-muted px-3 py-2 text-xs text-secondary">
                                        {{ postForm.image.name }}
                                    </span>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="postForm.processing || !canPost"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <i class="las la-paper-plane"></i>
                                    <span v-if="postForm.processing">Postet...</span>
                                    <span v-else>Posten</span>
                                </button>
                            </div>

                            <p v-if="postForm.errors.content || postForm.errors.image || postForm.errors.club_id" class="text-sm text-error">
                                {{ postForm.errors.content || postForm.errors.image || postForm.errors.club_id }}
                            </p>
                        </div>
                    </div>
                </form>

                <article v-for="post in posts.data" :key="post.id" class="surface-card overflow-hidden">
                    <div class="flex items-start justify-between gap-4 p-4">
                        <div class="flex min-w-0 gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold text-primary">
                                {{ initials(post.user?.name) }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold text-primary">{{ post.user?.name }}</h2>
                                <p class="text-xs text-secondary">
                                    {{ post.club?.name || 'Allgemein' }} · {{ formatDate(post.created_at) }}
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="post.user_id === user?.id"
                            type="button"
                            class="rounded p-2 text-secondary transition hover:bg-error/10 hover:text-error"
                            @click="deletePost(post)"
                        >
                            <i class="las la-trash"></i>
                        </button>
                    </div>

                    <div class="px-4 pb-4">
                        <p v-if="post.content" class="whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <img
                            v-if="post.image"
                            :src="imageUrl(post.image)"
                            alt=""
                            class="mt-4 max-h-[520px] w-full rounded-xl border border-border object-cover"
                        />
                    </div>

                    <div class="flex items-center justify-between border-y border-border px-4 py-2 text-sm text-secondary">
                        <span>{{ post.likes_count }} Likes</span>
                        <span>{{ post.comments_count }} Kommentare</span>
                    </div>

                    <div class="grid grid-cols-2 border-b border-border">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium transition hover:bg-muted"
                            :class="post.liked_by_me ? 'text-error' : 'text-primary'"
                            @click="toggleLike(post)"
                        >
                            <i :class="post.liked_by_me ? 'las la-heart' : 'lar la-heart'"></i>
                            Like
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-primary transition hover:bg-muted"
                            @click="commentFormFor(post.id)"
                        >
                            <i class="lar la-comment"></i>
                            Kommentar
                        </button>
                    </div>

                    <div class="space-y-3 p-4">
                        <div v-for="comment in post.comments" :key="comment.id" class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-inputBg text-xs font-semibold text-primary">
                                {{ initials(comment.user?.name) }}
                            </div>
                            <div class="min-w-0 flex-1 rounded-lg bg-inputBg px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-primary">{{ comment.user?.name }}</span>
                                    <span class="text-xs text-secondary">{{ comment.likes_count }} Likes</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm text-primary">{{ comment.content }}</p>
                            </div>
                        </div>

                        <form class="flex gap-2" @submit.prevent="createComment(post)">
                            <input
                                v-model="commentFormFor(post.id).content"
                                type="text"
                                placeholder="Kommentar schreiben..."
                                class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary placeholder-secondary focus:border-borderHover focus:ring-borderHover"
                            />
                            <button
                                type="submit"
                                :disabled="commentFormFor(post.id).processing || !commentFormFor(post.id).content.trim()"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <i class="las la-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </article>

                <div v-if="posts.data.length === 0" class="surface-card p-8 text-center text-secondary">
                    Noch keine Beiträge vorhanden.
                </div>

                <div v-if="posts.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                    <button
                        v-for="link in posts.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-muted'"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </section>

            <aside class="space-y-4">
                <div class="surface-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Deine Vereine</h2>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="club in clubs"
                            :key="club.id"
                            class="flex items-center gap-3 rounded-lg border border-border bg-inputBg p-3"
                        >
                            <div class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(club.name) }}
                            </div>
                            <span class="min-w-0 truncate text-sm font-medium text-primary">{{ club.name }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
