<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'
import { confirmDialog, promptDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    posts: Object,
    filters: Object,
    can: Object,
    categories: {
        type: Array,
        default: () => [],
    },
})

const editingPost = ref(null)
const filterStatus = ref(props.filters?.status || 'all')
const search = ref(props.filters?.search || '')
const coverUploadInput = ref(null)
const contentImageInput = ref(null)
const editorRef = ref(null)
const editorDirection = ref('ltr')
const contentImageUploading = ref(false)

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    cover_image: '',
    cover_image_upload: null,
    category: '',
    blog_category_id: '',
    tags: '',
    meta_title: '',
    meta_description: '',
    status: 'draft',
    published_at: '',
    _method: '',
})

const statusOptions = computed(() => {
    const options = [
        ['draft', 'Entwurf'],
        ['review', 'Review'],
        ['archived', 'Archiviert'],
    ]

    if (props.can.publish) {
        options.splice(2, 0, ['published', 'Veröffentlicht'])
    }

    return options
})

const categoryOptions = computed(() => props.categories || [])

const statusClasses = {
    draft: 'bg-muted text-secondary',
    review: 'bg-air-orange/15 text-air-orange',
    published: 'bg-air-green/15 text-air-green',
    archived: 'bg-error/15 text-error',
}

const toolbarGroups = [
    [
        { label: 'B', title: 'Fett', command: 'bold', class: 'font-black' },
        { label: 'I', title: 'Kursiv', command: 'italic', class: 'italic' },
        { label: 'U', title: 'Unterstrichen', command: 'underline', class: 'underline' },
        { label: 'S', title: 'Durchgestrichen', command: 'strikeThrough', class: 'line-through' },
    ],
    [
        { icon: 'las la-list-ul', title: 'Liste', command: 'insertUnorderedList' },
        { icon: 'las la-list-ol', title: 'Nummerierte Liste', command: 'insertOrderedList' },
        { icon: 'las la-quote-right', title: 'Zitat', block: 'blockquote' },
    ],
    [
        { icon: 'las la-align-left', title: 'Links', command: 'justifyLeft' },
        { icon: 'las la-align-center', title: 'Zentriert', command: 'justifyCenter' },
        { icon: 'las la-align-right', title: 'Rechts', command: 'justifyRight' },
    ],
]

const contentStyles = [
    ['', 'Textart wählen'],
    ['p', 'Absatz'],
    ['h2', 'Titel im Artikel'],
    ['lead', 'Untertitel / Lead'],
    ['h3', 'Abschnitt'],
    ['h4', 'Zwischenüberschrift'],
    ['blockquote', 'Zitat'],
    ['callout', 'Hinweisbox'],
    ['pre', 'Code / Notiz'],
]

const semanticInlineStyles = [
    ['', 'Farbe / Markierung'],
    ['blog-text-primary', 'Standardtext'],
    ['blog-text-secondary', 'Nebeninfo'],
    ['blog-text-accent', 'Akzent'],
    ['blog-text-success', 'Positiv'],
    ['blog-text-warning', 'Wichtig'],
    ['blog-text-danger', 'Warnung'],
    ['blog-mark', 'Markierung'],
]

const resetForm = () => {
    editingPost.value = null
    form.reset()
    form.clearErrors()
    form.status = 'draft'
    form._method = ''
    editorDirection.value = 'ltr'
    nextTick(() => {
        if (editorRef.value) editorRef.value.innerHTML = ''
    })
    if (coverUploadInput.value) coverUploadInput.value.value = null
}

const edit = (post) => {
    editingPost.value = post
    form.title = post.title || ''
    form.slug = post.slug || ''
    form.excerpt = post.excerpt || ''
    form.content = post.content || ''
    form.cover_image = post.cover_image || ''
    form.cover_image_upload = null
    form.category = post.category || ''
    form.blog_category_id = post.blog_category_id || post.blog_category?.id || ''
    form.tags = (post.tags || []).join(', ')
    form.meta_title = post.meta_title || ''
    form.meta_description = post.meta_description || ''
    form.status = props.can.publish ? post.status : (post.status === 'published' ? 'review' : post.status)
    form.published_at = post.published_at ? post.published_at.slice(0, 16) : ''
    form._method = ''
    if (coverUploadInput.value) coverUploadInput.value.value = null

    nextTick(() => {
        if (editorRef.value) {
            editorRef.value.innerHTML = form.content
            editorRef.value.focus()
        }
    })
}

const syncEditor = () => {
    form.content = editorRef.value?.innerHTML || ''
}

const runCommand = (command, value = null) => {
    editorRef.value?.focus()
    document.execCommand(command, false, value)
    syncEditor()
}

const applyBlock = (tag) => {
    runCommand('formatBlock', tag)
}

const selectedHtml = () => {
    const selection = window.getSelection()

    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        return ''
    }

    const container = document.createElement('div')
    container.appendChild(selection.getRangeAt(0).cloneContents())

    return container.innerHTML
}

const applyContentStyle = (style) => {
    if (!style) return

    if (['p', 'h2', 'h3', 'h4', 'blockquote', 'pre'].includes(style)) {
        applyBlock(style)
        return
    }

    editorRef.value?.focus()
    const html = selectedHtml() || 'Text eingeben...'

    if (style === 'lead') {
        document.execCommand('insertHTML', false, `<p class="blog-lead">${html}</p>`)
    }

    if (style === 'callout') {
        document.execCommand('insertHTML', false, `<div class="blog-callout"><strong>Hinweis</strong><p>${html}</p></div>`)
    }

    syncEditor()
}

const applySemanticInlineStyle = (styleClass) => {
    if (!styleClass) return

    editorRef.value?.focus()
    const html = selectedHtml() || 'Text'
    document.execCommand('insertHTML', false, `<span class="${styleClass}">${html}</span>`)
    syncEditor()
}

const setEditorDirection = (direction) => {
    editorDirection.value = direction
    editorRef.value?.focus()
    syncEditor()
}

const createLink = async () => {
    const url = await promptDialog({
        title: 'Link einfügen',
        message: 'Füge die vollständige URL ein, die im Artikel verlinkt werden soll.',
        inputLabel: 'URL',
        placeholder: 'https://airmius.com',
        confirmLabel: 'Einfügen',
        required: true,
    })

    if (!url) return

    runCommand('createLink', url)
}

const escapeHtml = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

const selectContentImage = () => {
    editorRef.value?.focus()
    contentImageInput.value?.click()
}

const uploadContentImage = async (event) => {
    const file = event.target.files?.[0] || null

    if (!file) return

    const alt = await promptDialog({
        title: 'Bildbeschreibung',
        message: 'Der Alt-Text hilft bei Barrierefreiheit und SEO.',
        inputLabel: 'Alt-Text',
        defaultValue: file.name.replace(/\.[^.]+$/, ''),
        confirmLabel: 'Bild hochladen',
    })

    if (alt === null) {
        if (contentImageInput.value) contentImageInput.value.value = null
        return
    }

    const payload = new FormData()
    payload.append('image', file)
    payload.append('alt', alt)
    contentImageUploading.value = true

    window.axios.post(route('blogs.content-images.store'), payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then((response) => {
        const url = response.data?.url

        if (!url) return

        editorRef.value?.focus()
        const safeAlt = escapeHtml(response.data?.alt || alt)
        document.execCommand('insertHTML', false, `<figure class="blog-image"><img src="${url}" alt="${safeAlt}"><figcaption>${safeAlt}</figcaption></figure><p><br></p>`)
        syncEditor()
    }).finally(() => {
        contentImageUploading.value = false
        if (contentImageInput.value) contentImageInput.value.value = null
    })
}

const stripHtml = (value = '') => value
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const plainContent = computed(() => stripHtml(form.content))
const contentWordCount = computed(() => plainContent.value ? plainContent.value.split(/\s+/).filter(Boolean).length : 0)
const effectiveMetaTitle = computed(() => form.meta_title || form.title)
const effectiveMetaDescription = computed(() => form.meta_description || form.excerpt)
const hasCoverImage = computed(() => Boolean(form.cover_image || form.cover_image_upload))

const seoChecks = computed(() => [
    {
        label: 'Titel ist suchfreundlich',
        passed: effectiveMetaTitle.value.length >= 35 && effectiveMetaTitle.value.length <= 65,
        hint: '35-65 Zeichen',
    },
    {
        label: 'Meta Description ist klickstark',
        passed: effectiveMetaDescription.value.length >= 110 && effectiveMetaDescription.value.length <= 160,
        hint: '110-160 Zeichen',
    },
    {
        label: 'Kurztext vorhanden',
        passed: form.excerpt.trim().length >= 80,
        hint: 'Mindestens 80 Zeichen',
    },
    {
        label: 'Artikel hat genug Tiefe',
        passed: contentWordCount.value >= 450,
        hint: `${contentWordCount.value} Woerter`,
    },
    {
        label: 'Kategorie gesetzt',
        passed: Boolean(form.blog_category_id || form.category),
        hint: 'Fuer Archiv, Breadcrumbs und Related Posts',
    },
    {
        label: 'Cover Bild gesetzt',
        passed: hasCoverImage.value,
        hint: '1600 x 900 px empfohlen',
    },
])

const seoScore = computed(() => {
    if (!seoChecks.value.length) return 0

    return Math.round((seoChecks.value.filter((check) => check.passed).length / seoChecks.value.length) * 100)
})

const seoScoreClass = computed(() => {
    if (seoScore.value >= 85) return 'text-air-green'
    if (seoScore.value >= 65) return 'text-air-orange'

    return 'text-error'
})

const publishBlocked = computed(() => form.status === 'published' && seoScore.value < 85)

const formatDateTime = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const selectCoverUpload = (event) => {
    form.cover_image_upload = event.target.files?.[0] || null
}

const submit = () => {
    syncEditor()

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetForm,
        onFinish: () => {
            form._method = ''
            if (coverUploadInput.value) coverUploadInput.value.value = null
        },
    }

    if (editingPost.value) {
        form._method = 'put'
        form.post(route('blogs.update', editingPost.value.id), options)

        return
    }

    form.post(route('blogs.store'), options)
}

const destroyPost = async (post) => {
    const confirmed = await confirmDialog({
        title: 'Blogbeitrag löschen',
        message: `Soll der Blogbeitrag "${post.title}" wirklich gelöscht werden?`,
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) {
        return
    }

    router.delete(route('blogs.destroy', post.id), { preserveScroll: true })
}

const applyFilters = () => {
    router.get(route('blogs.index'), {
        status: filterStatus.value === 'all' ? undefined : filterStatus.value,
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <Head title="Blogs" />

    <div class="space-y-6">
        <div class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Website CMS</p>
                    <h1 class="mt-1 text-3xl font-bold text-primary">Blog Studio</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                        Schreibe Beiträge mit Überschriften, Listen, Markierungen, Links, Zitaten und sauberer öffentlicher Darstellung.
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-[auto_160px_auto_auto]">
                    <Link
                        v-if="can.manageCategories"
                        :href="route('blog-categories.index')"
                        class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    >
                        Kategorien
                    </Link>
                    <input
                        v-model="search"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        placeholder="Suchen..."
                        @keydown.enter.prevent="applyFilters"
                    />
                    <select v-model="filterStatus" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="applyFilters">
                        <option value="all">Alle Status</option>
                        <option value="draft">Entwurf</option>
                        <option value="review">Review</option>
                        <option value="published">Veröffentlicht</option>
                        <option value="archived">Archiviert</option>
                    </select>
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="applyFilters">
                        Filtern
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_520px]">
            <section class="space-y-4">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="surface-card overflow-hidden transition hover:border-air-blue/50"
                >
                    <div class="grid gap-4 p-4 md:grid-cols-[190px_1fr]">
                        <div class="flex h-40 items-center justify-center overflow-hidden rounded-lg bg-inputBg">
                            <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                            <i v-else class="las la-newspaper text-5xl text-secondary"></i>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="[statusClasses[post.status], 'rounded-full px-3 py-1 text-xs font-semibold']">
                                    {{ post.status }}
                                </span>
                                <Link
                                    v-if="post.category && can.manageCategories"
                                    :href="route('blog-categories.index')"
                                    class="rounded-full border border-border px-3 py-1 text-xs text-secondary hover:border-air-blue hover:text-air-blue"
                                >
                                    {{ post.category }}
                                </Link>
                                <span v-else-if="post.category" class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    {{ post.category }}
                                </span>
                                <span class="text-xs text-secondary">
                                    {{ post.author?.name || 'Unbekannt' }}
                                </span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    SEO {{ post.seo_score || 0 }}%
                                </span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    {{ post.revisions_count || 0 }} Revisionen
                                </span>
                            </div>

                            <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-secondary">
                                {{ post.excerpt || stripHtml(post.content) }}
                            </p>
                            <p v-if="post.latest_revision" class="mt-2 text-xs text-secondary">
                                Letzte Sicherung: {{ formatDateTime(post.latest_revision.created_at) }} mit {{ post.latest_revision.seo_score }}% SEO
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    v-if="can.update"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="edit(post)"
                                >
                                    Bearbeiten
                                </button>
                                <Link
                                    v-if="post.status === 'published'"
                                    :href="route('guest.blog.show', post.slug)"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                >
                                    Anzeigen
                                </Link>
                                <Link
                                    v-if="can.update"
                                    :href="route('blogs.preview', post.id)"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                >
                                    Vorschau
                                </Link>
                                <button
                                    v-if="can.delete"
                                    class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white"
                                    @click="destroyPost(post)"
                                >
                                    Löschen
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="!posts.data.length" class="surface-card p-8 text-center text-secondary">
                    Noch keine Blogbeitraege vorhanden.
                </div>

                <div v-if="posts.links?.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in posts.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border border-border px-3 py-2 text-sm"
                        :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted'"
                        v-html="link.label"
                    />
                </div>
            </section>

            <aside class="surface-card h-fit overflow-hidden">
                <div class="border-b border-border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-secondary">
                                {{ editingPost ? 'Beitrag bearbeiten' : 'Neuer Beitrag' }}
                            </p>
                            <h2 class="mt-1 text-lg font-bold text-primary">
                                {{ editingPost ? editingPost.title : 'Schreiben' }}
                            </h2>
                        </div>
                        <button v-if="editingPost" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="resetForm">
                            Neu
                        </button>
                    </div>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submit">
                    <div>
                        <label class="text-sm font-semibold text-primary">Titel</label>
                        <input v-model="form.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                        <p v-if="form.errors.title" class="mt-1 text-sm text-error">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Slug</label>
                        <input v-model="form.slug" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="automatisch bei leerem Feld" />
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-error">{{ form.errors.slug }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <Link
                                    v-if="can.manageCategories"
                                    :href="route('blog-categories.index')"
                                    class="text-sm font-semibold text-primary hover:text-air-blue hover:underline"
                                >
                                    Kategorie
                                </Link>
                                <label v-else class="text-sm font-semibold text-primary">Kategorie</label>
                            </div>
                            <select v-model="form.blog_category_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">Kategorie waehlen</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.category" class="mt-1 text-sm text-error">{{ form.errors.category }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary">Status</label>
                            <select v-model="form.status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="[value, label] in statusOptions" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="can.publish">
                        <label class="text-sm font-semibold text-primary">Veröffentlichen am</label>
                        <input v-model="form.published_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Kurztext</label>
                        <textarea v-model="form.excerpt" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Inhalt</label>
                        <div class="mt-1 overflow-hidden rounded-lg border border-border bg-inputBg">
                            <div class="flex flex-wrap items-center gap-1 border-b border-border bg-card/70 p-2">
                                <select class="h-9 rounded-md border-border bg-inputBg text-xs font-semibold text-primary" @change="applyContentStyle($event.target.value)">
                                    <option v-for="[value, label] in contentStyles" :key="value" :value="value">{{ label }}</option>
                                </select>

                                <span v-for="(group, groupIndex) in toolbarGroups" :key="groupIndex" class="ml-1 flex gap-1 border-l border-border pl-1">
                                    <button
                                        v-for="tool in group"
                                        :key="tool.title"
                                        type="button"
                                        :title="tool.title"
                                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-semibold text-primary hover:bg-muted"
                                        :class="tool.class"
                                        @click="tool.block ? applyBlock(tool.block) : runCommand(tool.command)"
                                    >
                                        <i v-if="tool.icon" :class="tool.icon"></i>
                                        <span v-else>{{ tool.label }}</span>
                                    </button>
                                </span>

                                <span class="ml-1 flex gap-1 border-l border-border pl-1">
                                    <button
                                        type="button"
                                        title="Links nach rechts"
                                        class="inline-flex h-9 items-center justify-center rounded-md px-3 text-xs font-bold hover:bg-muted"
                                        :class="editorDirection === 'ltr' ? 'bg-air-blue/15 text-air-blue' : 'text-primary'"
                                        @click="setEditorDirection('ltr')"
                                    >
                                        LTR
                                    </button>
                                    <button
                                        type="button"
                                        title="Rechts nach links"
                                        class="inline-flex h-9 items-center justify-center rounded-md px-3 text-xs font-bold hover:bg-muted"
                                        :class="editorDirection === 'rtl' ? 'bg-air-blue/15 text-air-blue' : 'text-primary'"
                                        @click="setEditorDirection('rtl')"
                                    >
                                        RTL
                                    </button>
                                </span>

                                <select class="h-9 rounded-md border-border bg-inputBg text-xs font-semibold text-primary" @change="applySemanticInlineStyle($event.target.value)">
                                    <option v-for="[value, label] in semanticInlineStyles" :key="value" :value="value">{{ label }}</option>
                                </select>

                                <button type="button" title="Link" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted" @click="createLink">
                                    <i class="las la-link"></i>
                                </button>
                                <button
                                    type="button"
                                    title="Bild in Inhalt einfuegen"
                                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted disabled:opacity-60"
                                    :disabled="contentImageUploading"
                                    @click="selectContentImage"
                                >
                                    <i class="las la-image"></i>
                                </button>
                                <input
                                    ref="contentImageInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    @change="uploadContentImage"
                                />
                                <button type="button" title="Formatierung entfernen" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted" @click="runCommand('removeFormat')">
                                    <i class="las la-eraser"></i>
                                </button>
                            </div>

                            <div
                                ref="editorRef"
                                contenteditable="true"
                                :dir="editorDirection"
                                class="blog-editor min-h-[320px] max-h-[580px] overflow-y-auto px-4 py-3 text-primary outline-none"
                                :class="editorDirection === 'rtl' ? 'text-right' : 'text-left'"
                                @input="syncEditor"
                                @blur="syncEditor"
                            ></div>
                        </div>
                        <p v-if="form.errors.content" class="mt-1 text-sm text-error">{{ form.errors.content }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Cover Bild</label>
                        <input v-model="form.cover_image" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://..." />
                        <input
                            ref="coverUploadInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                            @change="selectCoverUpload"
                        />
                        <p class="mt-1 text-xs text-secondary">
                            Empfohlenes Format: 1600 x 900 px im Querformat. Link einfuegen oder Bild hochladen. Wenn beides gesetzt ist, wird der Upload verwendet.
                        </p>
                        <p v-if="form.errors.cover_image" class="mt-1 text-sm text-error">{{ form.errors.cover_image }}</p>
                        <p v-if="form.errors.cover_image_upload" class="mt-1 text-sm text-error">{{ form.errors.cover_image_upload }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Tags</label>
                        <input v-model="form.tags" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Training, Verein, Digital" />
                    </div>

                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-primary">SEO</p>
                            <span class="text-sm font-bold" :class="seoScoreClass">{{ seoScore }}%</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-green transition-all" :style="{ width: `${seoScore}%` }"></div>
                        </div>
                        <input v-model="form.meta_title" class="mt-3 w-full rounded-lg border-border bg-card text-primary" placeholder="Meta Title" />
                        <textarea v-model="form.meta_description" rows="2" class="mt-3 w-full rounded-lg border-border bg-card text-primary" placeholder="Meta Description"></textarea>
                        <p v-if="publishBlocked" class="mt-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">
                            Veroeffentlichen ist ab 85% SEO-Qualitaet moeglich.
                        </p>
                        <div class="mt-3 grid gap-2 text-xs">
                            <div v-for="check in seoChecks" :key="check.label" class="flex items-start gap-2">
                                <i :class="[check.passed ? 'las la-check-circle text-air-green' : 'las la-exclamation-circle text-air-orange', 'mt-0.5 text-base']"></i>
                                <div>
                                    <p class="font-semibold text-primary">{{ check.label }}</p>
                                    <p class="text-secondary">{{ check.hint }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        class="w-full rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="form.processing || (!editingPost && !can.create) || publishBlocked"
                    >
                        {{ editingPost ? 'Aktualisieren' : 'Erstellen' }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.blog-editor :deep(h2),
.blog-editor h2 {
    margin: 1.1rem 0 0.6rem;
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
}

.blog-editor :deep(h3),
.blog-editor h3 {
    margin: 1rem 0 0.5rem;
    font-size: 1.3rem;
    font-weight: 800;
}

.blog-editor :deep(h4),
.blog-editor h4 {
    margin: 0.9rem 0 0.4rem;
    font-size: 1.05rem;
    font-weight: 800;
}

.blog-editor :deep(p),
.blog-editor p {
    margin: 0.7rem 0;
    line-height: 1.75;
}

.blog-editor :deep(ul),
.blog-editor :deep(ol),
.blog-editor ul,
.blog-editor ol {
    margin: 0.8rem 0;
    padding-left: 1.5rem;
}

.blog-editor :deep(blockquote),
.blog-editor blockquote {
    margin: 1rem 0;
    border-left: 3px solid var(--accent);
    padding-left: 1rem;
    color: var(--secondary);
}

.blog-editor :deep(pre),
.blog-editor pre {
    overflow-x: auto;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--inputBg) 86%, var(--bg));
    padding: 0.85rem;
}

.blog-editor :deep(a),
.blog-editor a {
    color: var(--accent);
    text-decoration: underline;
}

.blog-editor :deep(.blog-lead),
.blog-editor .blog-lead {
    color: var(--secondary);
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.75;
}

.blog-editor :deep(.blog-callout),
.blog-editor .blog-callout {
    margin: 1rem 0;
    border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border));
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--accent) 12%, var(--card));
    padding: 1rem;
}

.blog-editor :deep(.blog-callout strong),
.blog-editor .blog-callout strong {
    display: block;
    margin-bottom: 0.35rem;
    color: var(--accent);
}

.blog-editor :deep(.blog-image),
.blog-editor .blog-image {
    margin: 1rem 0;
}

.blog-editor :deep(.blog-image img),
.blog-editor .blog-image img {
    display: block;
    width: 100%;
    max-height: 420px;
    border-radius: 0.75rem;
    object-fit: cover;
}

.blog-editor :deep(.blog-image figcaption),
.blog-editor .blog-image figcaption {
    margin-top: 0.45rem;
    color: var(--secondary);
    font-size: 0.8rem;
    text-align: center;
}

.blog-editor :deep(.blog-text-primary),
.blog-editor .blog-text-primary {
    color: var(--primary);
}

.blog-editor :deep(.blog-text-secondary),
.blog-editor .blog-text-secondary {
    color: var(--secondary);
}

.blog-editor :deep(.blog-text-accent),
.blog-editor .blog-text-accent {
    color: var(--accent);
}

.blog-editor :deep(.blog-text-success),
.blog-editor .blog-text-success {
    color: var(--success);
}

.blog-editor :deep(.blog-text-warning),
.blog-editor .blog-text-warning {
    color: var(--accent-3);
}

.blog-editor :deep(.blog-text-danger),
.blog-editor .blog-text-danger {
    color: var(--error);
}

.blog-editor :deep(.blog-mark),
.blog-editor .blog-mark {
    border-radius: 0.25rem;
    background: color-mix(in srgb, var(--accent-3) 22%, transparent);
    color: var(--primary);
    padding: 0.05rem 0.25rem;
}
</style>
