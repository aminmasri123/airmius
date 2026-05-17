<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import FeedComments from '@/Components/Auth/Feed/FeedComments.vue'
import FeedComposer from '@/Components/Auth/Feed/FeedComposer.vue'
import FeedStories from '@/Components/Auth/Feed/FeedStories.vue'
import AdSlot from '@/Components/Ads/AdSlot.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { usePermissions } from '@/composables/usePermissions'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    posts: { type: Object, default: () => ({ data: [], links: [] }) },
    stories: { type: Array, default: () => [] },
    feedFilter: { type: String, default: 'all' },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => ['team', 'organization', 'public'] },
    postTypes: { type: Array, default: () => ['normal'] },
    sports: { type: Array, default: () => [] },
})

const { can } = usePermissions()
const { t, te } = useI18n()
const page = usePage()
const user = page.props.auth?.user
const commentSections = reactive({})
const editForms = reactive({})
const reportTarget = ref(null)
const deleteTarget = ref(null)
const deleteCommentTarget = ref(null)
const deleteConfirmText = ref('')
const reportForm = useForm({
    type: '',
    id: null,
    reason: 'other',
    details: '',
})
const canCreatePost = computed(() => can('post.store'))
const canEditPost = (post) => Boolean(post.can_update)
const canDeletePost = (post) => Boolean(post.can_delete)
const activeFeedFilter = computed(() => props.feedFilter || 'all')
const feedTabs = [
    { key: 'all', label: 'Alle', icon: 'las la-layer-group' },
    { key: 'team', label: 'Team', icon: 'las la-users' },
    { key: 'organization', label: 'Verein', icon: 'las la-building' },
    { key: 'knowledge', label: 'Wissen', icon: 'las la-lightbulb' },
    { key: 'questions', label: 'Fragen', icon: 'las la-question-circle' },
    { key: 'training', label: 'Training', icon: 'las la-dumbbell' },
]
const activeFeedFilterLabel = computed(() => feedTabs.find((tab) => tab.key === activeFeedFilter.value)?.label || 'Alle')
const reportReasons = [
    { value: 'insult', label: 'Beleidigung' },
    { value: 'bullying', label: 'Mobbing' },
    { value: 'hate', label: 'Hassrede' },
    { value: 'sexual', label: 'Sexueller Inhalt' },
    { value: 'violence', label: 'Gewalt' },
    { value: 'threat', label: 'Drohung' },
    { value: 'image_rights', label: 'Bild ohne Zustimmung' },
    { value: 'spam', label: 'Spam' },
    { value: 'other', label: 'Sonstiges' },
]

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const fileName = (fileOrPath) => {
    if (typeof fileOrPath === 'object' && fileOrPath !== null) {
        return fileOrPath.display_name || (fileOrPath.path || '').split('/').pop()
    }

    return (fileOrPath || '').split('/').pop()
}
const isImageMime = (type) => type?.startsWith('image/')
const isVideoMime = (type) => type?.startsWith('video/')
const sportLabel = (sport) => {
    if (!sport) return ''
    const key = `sports.${sport.slug}`
    return te(key) ? t(key) : sport.name
}
const postTypeLabel = (type) => ({
    normal: 'Normal',
    question: 'Frage',
    knowledge: 'Wissen',
    training_drill: 'Trainingsübung',
    tactic: 'Taktik',
    analysis: 'Analyse',
    experience: 'Erfahrung',
    club_update: 'Vereinsinfo',
}[type] || type)
const visibilityLabel = (visibility) => ({
    public: 'Öffentlich',
    organization: 'Verein',
    team: 'Team',
}[visibility] || visibility)
const contentOriginLabel = (origin) => ({
    self: 'Selbst erstellt',
    ai: 'Mit KI erstellt',
}[origin] || 'Selbst erstellt')
const skillsForSport = (sportId) => props.sports.find((sport) => String(sport.id) === String(sportId))?.skills || []

const changeFeedFilter = (filter) => {
    if (filter === activeFeedFilter.value) return

    router.get(route('auth.feed.index'), filter === 'all' ? {} : { filter }, {
        preserveScroll: true,
        preserveState: true,
        only: ['posts', 'stories', 'feedFilter', 'clubs', 'teams', 'notificationCenter', 'auth'],
    })
}

const setCommentSection = (postId, component) => {
    if (component) {
        commentSections[postId] = component
        return
    }

    delete commentSections[postId]
}

const editFormFor = (post) => {
    editForms[post.id] ??= useForm({
        _method: 'PUT',
        club_id: post.club_id || '',
        team_id: post.team_id || '',
        visibility: post.visibility || 'organization',
        post_type: post.post_type || 'normal',
        content_origin: post.content_origin || 'self',
        sport_id: post.sport_id || '',
        sport_skill_ids: post.sport_skills?.map((skill) => skill.id) || [],
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
        only: ['posts', 'clubs', 'teams', 'notificationCenter', 'auth', 'errors'],
        onSuccess: () => {
            form.editing = false
            form.reset('image', 'attachments')
        },
    })
}

const openDeletePost = (post) => {
    deleteTarget.value = post
    deleteConfirmText.value = ''
}

const closeDeletePost = () => {
    deleteTarget.value = null
    deleteConfirmText.value = ''
}

const deletePost = () => {
    if (!deleteTarget.value || deleteConfirmText.value !== 'delete') return

    router.delete(route('auth.posts.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth', 'flash'],
        onSuccess: closeDeletePost,
    })
}

const openReport = (type, item) => {
    reportTarget.value = { type, item }
    reportForm.type = type
    reportForm.id = item.id
    reportForm.reason = 'other'
    reportForm.details = ''
    reportForm.clearErrors()
}

const closeReport = () => {
    reportTarget.value = null
    reportForm.reset()
}

const submitReport = () => {
    reportForm.post(route('auth.reports.store'), {
        preserveScroll: true,
        only: ['posts', 'stories', 'notificationCenter', 'auth', 'flash', 'errors'],
        onSuccess: closeReport,
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

const toggleHelpful = (post) => {
    const wasHelpful = post.helpful_by_me
    post.helpful_by_me = !wasHelpful
    post.helpfuls_count += post.helpful_by_me ? 1 : -1

    router.post(route('auth.posts.helpful', post.id), {}, {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth'],
        onError: () => {
            post.helpful_by_me = wasHelpful
            post.helpfuls_count += wasHelpful ? 1 : -1
        },
    })
}

const deleteComment = (comment) => {
    deleteCommentTarget.value = comment
}

const closeDeleteComment = () => {
    deleteCommentTarget.value = null
}

const confirmDeleteComment = () => {
    if (!deleteCommentTarget.value) return

    router.delete(route('auth.comments.destroy', deleteCommentTarget.value.id), {
        preserveScroll: true,
        only: ['posts', 'notificationCenter', 'auth', 'flash'],
        onSuccess: closeDeleteComment,
    })
}

const visitPage = (url) => url && router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    only: ['posts', 'stories', 'feedFilter', 'clubs', 'teams', 'notificationCenter', 'auth'],
})
</script>

<template>
    <AppLayout title="Feed">

        <Head title="Feed" />
          <div class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-4 overflow-hidden px-3 pb-24 sm:px-4 md:gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">

        <section class="min-w-0 space-y-4">

            <FeedStories
                :can-create="canCreatePost"
                :stories="stories"
                :clubs="clubs"
                :teams="teams"
                :visibilities="visibilities"
                :user="user"
                @report-story="openReport('story', $event)"
            />

            <FeedComposer
                :can-create="canCreatePost"
                :clubs="clubs"
                :teams="teams"
                :visibilities="visibilities"
                :post-types="postTypes"
                :sports="sports"
            />

            <div class="surface-card overflow-hidden p-2">
                <div class="custom-scrollbar flex gap-2 overflow-x-auto">
                    <button
                        v-for="tab in feedTabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="activeFeedFilter === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                        @click="changeFeedFilter(tab.key)"
                    >
                        <i :class="tab.icon"></i>
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <AdSlot placement="feed" variant="banner" :fallback="false" />

            <article
                v-for="post in posts.data"
                :key="post.id"
                class="surface-card min-w-0 overflow-hidden"
            >
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 gap-3">
                        <Link :href="route('auth.users.show', post.user.id)" class="shrink-0">
                            <img
                                v-if="post.user?.profile_photo_thumb"
                                :src="post.user.profile_photo_thumb"
                                :alt="post.user.name"
                                class="h-10 w-10 rounded-full object-cover"
                            />

                            <div
                                v-else
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary"
                            >
                                {{ initials(post.user?.name) }}
                            </div>
                        </Link>

                        <div class="min-w-0">
                            <Link
                                :href="route('auth.users.show', post.user.id)"
                                class="block truncate text-sm font-semibold text-primary hover:underline"
                            >
                                {{ post.user?.name }}
                            </Link>

                            <p class="break-words text-xs text-secondary">
                                <Link
                                    v-if="post.team"
                                    :href="route('auth.teams.show', post.team.id)"
                                    class="hover:text-primary hover:underline"
                                >
                                    {{ post.team.name }}
                                </Link>

                                <Link
                                    v-else-if="post.club"
                                    :href="route('auth.clubs.show', post.club.id)"
                                    class="hover:text-primary hover:underline"
                                >
                                    {{ post.club.name }}
                                </Link>

                                <span v-else>Public</span>

                                · {{ visibilityLabel(post.visibility) }} · {{ formatDate(post.created_at) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-1">
                        <button
                            v-if="post.user_id !== user?.id"
                            class="rounded p-2 text-secondary hover:bg-muted hover:text-primary"
                            title="Beitrag melden"
                            @click="openReport('post', post)"
                        >
                            <i class="las la-flag"></i>
                        </button>

                        <button
                            v-if="canEditPost(post)"
                            class="rounded p-2 text-secondary hover:bg-muted hover:text-primary"
                            @click="editFormFor(post).editing = !editFormFor(post).editing"
                        >
                            <i class="las la-edit"></i>
                        </button>

                        <button
                            v-if="canDeletePost(post)"
                            class="rounded p-2 text-secondary hover:bg-error/10 hover:text-error"
                            @click="openDeletePost(post)"
                        >
                            <i class="las la-trash"></i>
                        </button>
                    </div>
                </div>

                <form
                    v-if="editFormFor(post).editing"
                    class="space-y-3 px-4 pb-4"
                    @submit.prevent="updatePost(post)"
                >
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <select
                            v-model="editFormFor(post).visibility"
                            class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
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
                            v-model="editFormFor(post).post_type"
                            class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
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
                            v-model="editFormFor(post).content_origin"
                            class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="self">Von mir selbst erstellt</option>
                            <option value="ai">Mit KI erstellt</option>
                        </select>

                        <select
                            v-model="editFormFor(post).club_id"
                            class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="">Kein Verein</option>
                            <option
                                v-for="club in clubs"
                                :key="club.id"
                                :value="club.id"
                            >
                                {{ club.name }}
                            </option>
                        </select>

                        <select
                            v-model="editFormFor(post).team_id"
                            class="rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="">Kein Team</option>
                            <option
                                v-for="team in teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-2 lg:grid-cols-[1fr_2fr]">
                        <SearchableSelect
                            v-model="editFormFor(post).sport_id"
                            :options="sports"
                            value-key="id"
                            translation-prefix="sports"
                            category-translation-prefix="sport_categories"
                            placeholder="Sportart zum Beitrag"
                        />

                        <div class="flex min-h-11 max-w-full flex-wrap gap-2 overflow-hidden rounded-lg border border-border bg-inputBg px-3 py-2">
                            <label
                                v-for="skill in skillsForSport(editFormFor(post).sport_id)"
                                :key="skill.id"
                                class="inline-flex max-w-full cursor-pointer items-center gap-2 rounded-full border border-border bg-card px-3 py-2 text-xs text-primary"
                            >
                                <input
                                    v-model="editFormFor(post).sport_skill_ids"
                                    type="checkbox"
                                    :value="skill.id"
                                    class="shrink-0 rounded border-border bg-inputBg"
                                >

                                <span class="min-w-0 truncate">
                                    {{ skill.name }}
                                </span>
                            </label>

                            <span
                                v-if="!editFormFor(post).sport_id"
                                class="min-w-0 break-words text-sm text-secondary"
                            >
                                Optional: Sportart wählen.
                            </span>
                        </div>
                    </div>

                    <textarea
                        v-model="editFormFor(post).content"
                        rows="3"
                        class="w-full resize-none rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                    />

                    <input
                        type="file"
                        multiple
                        accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                        class="block w-full text-sm text-secondary"
                        @change="editFormFor(post).attachments = Array.from($event.target.files || [])"
                    />

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm text-buttonTextPrimary sm:w-auto"
                            :disabled="editFormFor(post).processing"
                        >
                            Speichern
                        </button>

                        <button
                            type="button"
                            class="w-full rounded-lg border border-border px-4 py-3 text-sm text-primary sm:w-auto"
                            @click="editFormFor(post).editing = false"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>

                <div v-else class="px-4 pb-4">
                    <div class="mb-3 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-inputBg px-3 py-1 font-semibold text-secondary">
                            {{ postTypeLabel(post.post_type) }}
                        </span>

                        <span
                            class="rounded-full border px-3 py-1 font-semibold"
                            :class="post.content_origin === 'ai'
                                ? 'border-air-blue/40 bg-air-blue/10 text-air-blue'
                                : 'border-border text-secondary'"
                        >
                            {{ contentOriginLabel(post.content_origin) }}
                        </span>

                        <span
                            v-if="post.user_id === user?.id && post.moderation_status && post.moderation_status !== 'approved'"
                            class="rounded-full border border-border bg-inputBg px-3 py-1 font-semibold text-secondary"
                        >
                            In Prüfung
                        </span>

                        <span
                            v-if="post.sport"
                            class="rounded-full bg-inputBg px-3 py-1 text-secondary"
                        >
                            {{ sportLabel(post.sport) }}
                        </span>

                        <span
                            v-for="skill in post.sport_skills"
                            :key="skill.id"
                            class="rounded-full border border-border px-3 py-1 text-secondary"
                        >
                            {{ skill.name }}
                        </span>
                    </div>

                    <p
                        v-if="post.content"
                        class="whitespace-pre-line break-words text-sm leading-6 text-primary"
                    >
                        {{ post.content }}
                    </p>

                    <img
                        v-if="post.image"
                        :src="storageUrl(post.image)"
                        alt=""
                        class="mt-4 max-h-[70vh] w-full rounded-xl border border-border object-cover"
                    />

                    <div v-if="post.attachments?.length" class="mt-4 space-y-3">
                        <div
                            v-for="attachment in post.attachments"
                            :key="attachment.id"
                        >
                            <video
                                v-if="attachment.file && isVideoMime(attachment.file.type)"
                                :src="storageUrl(attachment.file.path)"
                                :poster="attachment.file.thumbnail_url || (attachment.file.thumbnail_path ? storageUrl(attachment.file.thumbnail_path) : null)"
                                controls
                                preload="metadata"
                                class="max-h-[70vh] w-full rounded-xl border border-border bg-black"
                            ></video>

                            <img
                                v-else-if="attachment.file && isImageMime(attachment.file.type)"
                                :src="storageUrl(attachment.file.path)"
                                :alt="fileName(attachment.file)"
                                class="max-h-[70vh] w-full rounded-xl border border-border object-cover"
                            />

                            <a
                                v-else-if="attachment.file"
                                :href="storageUrl(attachment.file.path)"
                                target="_blank"
                                class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            >
                                <i class="las la-paperclip"></i>

                                <span class="min-w-0 flex-1 truncate">
                                    {{ fileName(attachment.file) }}
                                </span>

                                <span class="hidden text-xs text-secondary sm:inline">
                                    {{ attachment.file.type }}
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1 border-y border-border px-4 py-2 text-xs text-secondary sm:flex-row sm:items-center sm:justify-between sm:text-sm">
                    <span>{{ post.likes_count }} Likes</span>
                    <span>{{ post.helpfuls_count }} hilfreich · {{ post.comments_count }} Kommentare</span>
                </div>

                <div class="grid grid-cols-3 border-b border-border text-xs sm:text-sm">
                    <button
                        type="button"
                        class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
                        :class="post.liked_by_me ? 'text-error' : 'text-primary'"
                        @click="toggleLike(post)"
                    >
                        <i :class="post.liked_by_me ? 'las la-heart' : 'lar la-heart'"></i>
                        Like
                    </button>

                    <button
                        type="button"
                        class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
                        :class="post.helpful_by_me ? 'text-air-green' : 'text-primary'"
                        @click="toggleHelpful(post)"
                    >
                        <i :class="post.helpful_by_me ? 'las la-check-circle' : 'lar la-check-circle'"></i>
                        Hilfreich
                    </button>

                    <button
                        type="button"
                        class="flex flex-col items-center justify-center gap-1 px-2 py-3 font-medium text-primary hover:bg-muted sm:flex-row sm:gap-2 sm:px-4"
                        @click="commentSections[post.id]?.focusComment()"
                    >
                        <i class="lar la-comment"></i>
                        Kommentar
                    </button>
                </div>

                <FeedComments
                    :ref="(component) => setCommentSection(post.id, component)"
                    :post="post"
                    :user="user"
                    @delete-comment="deleteComment"
                    @report-comment="openReport('comment', $event)"
                />
            </article>

            <div
                v-if="posts.data.length === 0"
                class="surface-card p-8 text-center text-secondary"
            >
                <p class="font-semibold text-primary">Noch keine Beiträge in „{{ activeFeedFilterLabel }}”.</p>
                <p class="mt-1 text-sm">Wechsle den Filter oder erstelle den ersten passenden Beitrag.</p>
            </div>

            <div
                v-if="posts.links?.length > 3"
                class="flex flex-wrap justify-center gap-1"
            >
                <button
                    v-for="link in posts.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="min-w-10 rounded border border-border px-3 py-2 text-sm disabled:opacity-50"
                    :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-card text-primary hover:bg-muted'"
                    @click="visitPage(link.url)"
                    v-html="link.label"
                />
            </div>
        </section>

        <aside class="min-w-0 hidden md:block space-y-4 xl:sticky xl:top-24 xl:self-start">
            <AdSlot placement="feed" variant="card" :fallback="false" />

            <div class="surface-card p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                    {{ $t('Deine Vereine') }}
                </h2>

                <div class="mt-4 space-y-2">
                    <div v-if="clubs && clubs.length > 0" class="space-y-2">
                        <Link
                            v-for="club in clubs"
                            :key="club.id"
                            :href="route('auth.clubs.show', club.id)"
                            class="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-inputBg p-3"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                {{ initials(club.name) }}
                            </div>

                            <span class="min-w-0 truncate text-sm font-medium text-primary">
                                {{ club.name }}
                            </span>
                        </Link>
                    </div>

                    <div v-else class="text-sm text-secondary">
                        {{ $t('Keine Teams/Clubs vorhanden.') }}
                    </div>
                </div>
            </div>

        </aside>
    </div>

        <div
            v-if="deleteTarget"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
            @click.self="closeDeletePost"
        >
            <form class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="deletePost">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-error">Beitrag löschen</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">Bist du sicher?</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Dieser Beitrag und seine Anhänge werden gelöscht. Gib <span class="font-semibold text-primary">delete</span> ein, um fortzufahren.
                        </p>
                    </div>
                    <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeDeletePost">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <input
                    v-model="deleteConfirmText"
                    type="text"
                    autocomplete="off"
                    placeholder="delete"
                    class="mt-5 w-full rounded-lg border-border bg-inputBg text-primary"
                />

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn" @click="closeDeletePost">Abbrechen</button>
                    <button
                        type="submit"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="deleteConfirmText !== 'delete'"
                    >
                        Löschen
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="deleteCommentTarget"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
            @click.self="closeDeleteComment"
        >
            <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-error">Kommentar entfernen</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">Bist du sicher?</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">
                            Dieser Kommentar wird dauerhaft vom Beitrag entfernt.
                        </p>
                    </div>
                    <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeDeleteComment">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn" @click="closeDeleteComment">Abbrechen</button>
                    <button
                        type="button"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                        @click="confirmDeleteComment"
                    >
                        Kommentar lÃ¶schen
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="reportTarget"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
            @click.self="closeReport"
        >
            <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="submitReport">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Inhalt melden</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">
                            Warum soll dieser Inhalt geprüft werden?
                        </h2>
                    </div>
                    <button type="button" class="rounded p-2 text-secondary hover:bg-muted" @click="closeReport">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-sm font-semibold text-primary">Grund</label>
                        <select v-model="reportForm.reason" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option v-for="reason in reportReasons" :key="reason.value" :value="reason.value">
                                {{ reason.label }}
                            </option>
                        </select>
                        <p v-if="reportForm.errors.reason" class="mt-1 text-sm text-error">{{ reportForm.errors.reason }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Details optional</label>
                        <textarea
                            v-model="reportForm.details"
                            rows="4"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                            placeholder="Was ist dir aufgefallen?"
                        />
                        <p v-if="reportForm.errors.details" class="mt-1 text-sm text-error">{{ reportForm.errors.details }}</p>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn" @click="closeReport">Abbrechen</button>
                    <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                        Meldung senden
                    </button>
                </div>
            </form>
        </div>

    </AppLayout>
</template>
