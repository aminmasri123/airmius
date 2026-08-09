<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'
import { confirmDialog, promptDialog } from '@/services/dialogService'
import { useI18n } from 'vue-i18n'
import blogLocalizationCopy from '@/Pages/Blog/blogLocalizationCopy.json'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n({ useScope: 'global' })
const localeCode = computed(() => String(locale.value || 'de').replace('_', '-'))
const uiLocale = computed(() => ['de', 'en', 'fr', 'ar'].includes(localeCode.value.slice(0, 2)) ? localeCode.value.slice(0, 2) : 'de')
const lx = (key, params = {}) => {
    let value = blogLocalizationCopy[uiLocale.value]?.[key] ?? blogLocalizationCopy.de[key] ?? key

    Object.entries(params).forEach(([name, replacement]) => {
        value = String(value).replaceAll(`{${name}}`, String(replacement))
    })

    return value
}
const formatNumber = (value) => new Intl.NumberFormat(localeCode.value).format(Number(value || 0))
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const props = defineProps({
    posts: Object,
    filters: Object,
    can: Object,
    categories: {
        type: Array,
        default: () => [],
    },
    supportedLocales: {
        type: Array,
        default: () => ['de', 'en', 'fr', 'ar'],
    },
})

const editingPost = ref(null)
const filterStatus = ref(props.filters?.status || 'all')
const filterContentLocale = ref(props.filters?.content_locale || 'all')
const search = ref(props.filters?.search || '')
const translationSource = ref(null)
const coverUploadInput = ref(null)
const contentImageInput = ref(null)
const editorRef = ref(null)
const editorDirection = ref('ltr')
const contentImageUploading = ref(false)

const form = useForm({
    title: '',
    slug: '',
    content_locale: uiLocale.value,
    translation_of_id: '',
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
        ['draft', t('Entwurf')],
        ['review', t('Review')],
        ['archived', t('Archiviert')],
    ]

    if (props.can.publish) {
        options.splice(2, 0, ['published', t('Veröffentlicht')])
    }

    return options
})

const categoryOptions = computed(() => props.categories || [])
const languageName = (value) => blogLocalizationCopy[uiLocale.value]?.language_names?.[value]
    || blogLocalizationCopy.de.language_names[value]
    || String(value || '').toUpperCase()
const missingLocales = (post) => {
    const existing = new Set((post.translation_variants || []).map((variant) => variant.content_locale))

    return props.supportedLocales.filter((candidate) => !existing.has(candidate))
}

const statusClasses = {
    draft: 'bg-muted text-secondary',
    review: 'bg-air-orange/15 text-air-orange',
    published: 'bg-air-green/15 text-air-green',
    archived: 'bg-error/15 text-error',
}

const toolbarGroups = computed(() => [
    [
        { label: 'B', title: t('blogs_editor.toolbar.bold'), command: 'bold', class: 'font-black' },
        { label: 'I', title: t('blogs_editor.toolbar.italic'), command: 'italic', class: 'italic' },
        { label: 'U', title: t('blogs_editor.toolbar.underline'), command: 'underline', class: 'underline' },
        { label: 'S', title: t('blogs_editor.toolbar.strike'), command: 'strikeThrough', class: 'line-through' },
    ],
    [
        { icon: 'las la-list-ul', title: t('blogs_editor.toolbar.list'), command: 'insertUnorderedList' },
        { icon: 'las la-list-ol', title: t('blogs_editor.toolbar.numbered_list'), command: 'insertOrderedList' },
        { icon: 'las la-quote-right', title: t('blogs_editor.toolbar.quote'), block: 'blockquote' },
    ],
    [
        { icon: 'las la-align-left', title: t('blogs_editor.toolbar.left'), command: 'justifyLeft' },
        { icon: 'las la-align-center', title: t('blogs_editor.toolbar.center'), command: 'justifyCenter' },
        { icon: 'las la-align-right', title: t('blogs_editor.toolbar.right'), command: 'justifyRight' },
    ],
])

const contentStyles = computed(() => [
    ['', t('blogs_editor.styles.choose')],
    ['p', t('blogs_editor.styles.paragraph')],
    ['h2', t('blogs_editor.styles.article_title')],
    ['lead', t('blogs_editor.styles.lead')],
    ['h3', t('blogs_editor.styles.section')],
    ['h4', t('blogs_editor.styles.subheading')],
    ['blockquote', t('blogs_editor.styles.quote')],
    ['callout', t('blogs_editor.styles.callout')],
    ['pre', t('blogs_editor.styles.code')],
])

const semanticInlineStyles = computed(() => [
    ['', t('blogs_editor.inline.choose')],
    ['blog-text-primary', t('blogs_editor.inline.primary')],
    ['blog-text-secondary', t('blogs_editor.inline.secondary')],
    ['blog-text-accent', t('blogs_editor.inline.accent')],
    ['blog-text-success', t('blogs_editor.inline.success')],
    ['blog-text-warning', t('blogs_editor.inline.warning')],
    ['blog-text-danger', t('blogs_editor.inline.danger')],
    ['blog-mark', t('blogs_editor.inline.mark')],
])

const resetForm = () => {
    editingPost.value = null
    translationSource.value = null
    form.reset()
    form.clearErrors()
    form.status = 'draft'
    form.content_locale = uiLocale.value
    form.translation_of_id = ''
    form._method = ''
    editorDirection.value = form.content_locale === 'ar' ? 'rtl' : 'ltr'
    nextTick(() => {
        if (editorRef.value) editorRef.value.innerHTML = ''
    })
    if (coverUploadInput.value) coverUploadInput.value.value = null
}

const edit = (post) => {
    editingPost.value = post
    translationSource.value = null
    form.title = post.title || ''
    form.slug = post.slug || ''
    form.content_locale = post.content_locale || 'de'
    form.translation_of_id = ''
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
    editorDirection.value = form.content_locale === 'ar' ? 'rtl' : 'ltr'
    if (coverUploadInput.value) coverUploadInput.value.value = null

    nextTick(() => {
        if (editorRef.value) {
            editorRef.value.innerHTML = form.content
            editorRef.value.focus()
        }
    })
}

const createTranslation = (post, targetLocale) => {
    resetForm()
    translationSource.value = post
    form.translation_of_id = post.id
    form.content_locale = targetLocale
    form.cover_image = post.cover_image || ''
    form.blog_category_id = post.blog_category_id || post.blog_category?.id || ''
    form.category = post.category || ''
    form.tags = (post.tags || []).join(', ')
    editorDirection.value = targetLocale === 'ar' ? 'rtl' : 'ltr'

    nextTick(() => editorRef.value?.focus())
}

const syncDirectionWithLanguage = () => {
    editorDirection.value = form.content_locale === 'ar' ? 'rtl' : 'ltr'
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
    const html = selectedHtml() || t('blogs_editor.editor.text_placeholder')

    if (style === 'lead') {
        document.execCommand('insertHTML', false, `<p class="blog-lead">${html}</p>`)
    }

    if (style === 'callout') {
        document.execCommand('insertHTML', false, `<div class="blog-callout"><strong>${t('blogs_editor.editor.callout_label')}</strong><p>${html}</p></div>`)
    }

    syncEditor()
}

const applySemanticInlineStyle = (styleClass) => {
    if (!styleClass) return

    editorRef.value?.focus()
    const html = selectedHtml() || t('blogs_editor.editor.text_placeholder')
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
        title: t('blogs_editor.prompts.link_title'),
        message: t('blogs_editor.prompts.link_message'),
        inputLabel: t('blogs_editor.prompts.url'),
        placeholder: 'https://airmius.com',
        confirmLabel: t('blogs_editor.prompts.insert'),
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
        title: t('blogs_editor.prompts.image_title'),
        message: t('blogs_editor.prompts.image_message'),
        inputLabel: t('blogs_editor.prompts.alt_text'),
        defaultValue: file.name.replace(/\.[^.]+$/, ''),
        confirmLabel: t('blogs_editor.prompts.upload_image'),
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
        label: t('blogs_editor.seo.title_label'),
        passed: effectiveMetaTitle.value.length >= 35 && effectiveMetaTitle.value.length <= 65,
        hint: t('blogs_editor.seo.title_hint'),
    },
    {
        label: t('blogs_editor.seo.meta_label'),
        passed: effectiveMetaDescription.value.length >= 110 && effectiveMetaDescription.value.length <= 160,
        hint: t('blogs_editor.seo.meta_hint'),
    },
    {
        label: t('blogs_editor.seo.excerpt_label'),
        passed: form.excerpt.trim().length >= 80,
        hint: t('blogs_editor.seo.excerpt_hint'),
    },
    {
        label: t('blogs_editor.seo.depth_label'),
        passed: contentWordCount.value >= 450,
        hint: t('blogs_editor.seo.words_hint', { count: formatNumber(contentWordCount.value) }),
    },
    {
        label: t('blogs_editor.seo.category_label'),
        passed: Boolean(form.blog_category_id || form.category),
        hint: t('blogs_editor.seo.category_hint'),
    },
    {
        label: t('blogs_editor.seo.cover_label'),
        passed: hasCoverImage.value,
        hint: t('blogs_editor.seo.cover_hint'),
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

    return new Intl.DateTimeFormat(localeCode.value, {
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
        title: t('blogs_editor.delete_title'),
        message: t('blogs_editor.delete_message', { title: post.title }),
        confirmLabel: t('Löschen'),
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
        content_locale: filterContentLocale.value === 'all' ? undefined : filterContentLocale.value,
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <Head :title="t('blogs_editor.page_title')" />

    <div class="space-y-6">
        <div class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ t('blogs_editor.eyebrow') }}</p>
                    <h1 class="mt-1 text-3xl font-bold text-primary">{{ t('blogs_editor.title') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                        {{ t('blogs_editor.intro') }}
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[auto_180px_150px_170px_auto]">
                    <Link
                        v-if="can.manageCategories"
                        :href="route('blog-categories.index')"
                        class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    >
                        {{ t('blogs_editor.categories') }}
                    </Link>
                    <input
                        v-model="search"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        :placeholder="t('blogs_editor.search')"
                        @keydown.enter.prevent="applyFilters"
                    />
                    <select v-model="filterStatus" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="applyFilters">
                        <option value="all">{{ t('blogs_editor.filters.all') }}</option>
                        <option value="draft">{{ t('Entwurf') }}</option>
                        <option value="review">{{ t('Review') }}</option>
                        <option value="published">{{ t('Veröffentlicht') }}</option>
                        <option value="archived">{{ t('Archiviert') }}</option>
                    </select>
                    <select v-model="filterContentLocale" class="rounded-lg border-border bg-inputBg text-sm text-primary" :aria-label="lx('language')" @change="applyFilters">
                        <option value="all">{{ lx('all_languages') }}</option>
                        <option v-for="itemLocale in supportedLocales" :key="itemLocale" :value="itemLocale">
                            {{ languageName(itemLocale) }}
                        </option>
                    </select>
                    <button class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="applyFilters">
                        {{ t('blogs_editor.filter') }}
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
                                    {{ statusOptions.find(([value]) => value === post.status)?.[1] || post.status }}
                                </span>
                                <span class="rounded-full border border-air-blue/35 bg-air-blue/10 px-3 py-1 text-xs font-bold uppercase text-air-blue">
                                    {{ post.content_locale }} · {{ languageName(post.content_locale) }}
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
                                    {{ post.author?.name || t('Unbekannt') }}
                                </span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    SEO {{ post.seo_score || 0 }}%
                                </span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    {{ formatNumber(post.revisions_count) }} {{ t('blogs_editor.revisions') }}
                                </span>
                                <span class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    {{ lx('translation_coverage', { count: (post.translation_variants || []).length }) }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-secondary">
                                {{ post.excerpt || stripHtml(post.content) }}
                            </p>
                            <p v-if="post.latest_revision" class="mt-2 text-xs text-secondary">
                                {{ t('blogs_editor.latest_revision', { date: formatDateTime(post.latest_revision.created_at), score: post.latest_revision.seo_score }) }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    v-if="can.update"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                    @click="edit(post)"
                                >
                                    {{ t('Bearbeiten') }}
                                </button>
                                <Link
                                    v-if="post.status === 'published'"
                                    :href="route('guest.blog.show', { blogPost: post.slug, locale: post.content_locale === 'de' ? undefined : post.content_locale })"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                >
                                    {{ t('Anzeigen') }}
                                </Link>
                                <template v-if="can.create">
                                    <button
                                        v-for="targetLocale in missingLocales(post)"
                                        :key="targetLocale"
                                        type="button"
                                        class="rounded-lg border border-air-blue/40 px-3 py-2 text-sm font-semibold uppercase text-air-blue hover:bg-air-blue/10"
                                        :title="`${lx('create_translation')}: ${languageName(targetLocale)}`"
                                        @click="createTranslation(post, targetLocale)"
                                    >
                                        + {{ targetLocale }}
                                    </button>
                                </template>
                                <Link
                                    v-if="can.update"
                                    :href="route('blogs.preview', post.id)"
                                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                                >
                                    {{ t('Vorschau') }}
                                </Link>
                                <button
                                    v-if="can.delete"
                                    class="rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white"
                                    @click="destroyPost(post)"
                                >
                                    {{ t('Löschen') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="!posts.data.length" class="surface-card p-8 text-center text-secondary">
                    {{ t('blogs_editor.empty') }}
                </div>

                <div v-if="posts.links?.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in posts.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border border-border px-3 py-2 text-sm"
                        :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted'"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                </div>
            </section>

            <aside class="surface-card h-fit overflow-hidden">
                <div class="border-b border-border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-secondary">
                                {{ editingPost ? t('blogs_editor.form.edit_eyebrow') : t('blogs_editor.form.new_eyebrow') }}
                            </p>
                            <h2 class="mt-1 text-lg font-bold text-primary">
                                {{ editingPost ? editingPost.title : t('blogs_editor.form.write_title') }}
                            </h2>
                            <p v-if="translationSource" class="mt-2 text-xs leading-relaxed text-air-blue">
                                {{ lx('translation_source', { title: translationSource.title }) }}
                            </p>
                        </div>
                        <button v-if="editingPost" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="resetForm">
                            {{ t('blogs_editor.form.new_button') }}
                        </button>
                    </div>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submit">
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ lx('language') }}</label>
                        <select v-model="form.content_locale" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required @change="syncDirectionWithLanguage">
                            <option v-for="itemLocale in supportedLocales" :key="itemLocale" :value="itemLocale">
                                {{ languageName(itemLocale) }} ({{ itemLocale.toUpperCase() }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-secondary">{{ lx('language_help') }}</p>
                        <p v-if="form.errors.content_locale" class="mt-1 text-sm text-error">{{ form.errors.content_locale }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.title') }}</label>
                        <input v-model="form.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                        <p v-if="form.errors.title" class="mt-1 text-sm text-error">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.slug') }}</label>
                        <input v-model="form.slug" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('blogs_editor.fields.slug_placeholder')" />
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
                                    {{ t('blogs_editor.fields.category') }}
                                </Link>
                                <label v-else class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.category') }}</label>
                            </div>
                            <select v-model="form.blog_category_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option value="">{{ t('blogs_editor.fields.category_placeholder') }}</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.category" class="mt-1 text-sm text-error">{{ form.errors.category }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.status') }}</label>
                            <select v-model="form.status" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                                <option v-for="[value, label] in statusOptions" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="can.publish">
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.publish_at') }}</label>
                        <input v-model="form.published_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.excerpt') }}</label>
                        <textarea v-model="form.excerpt" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.content') }}</label>
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
                                        :title="t('blogs_editor.toolbar.ltr')"
                                        class="inline-flex h-9 items-center justify-center rounded-md px-3 text-xs font-bold hover:bg-muted"
                                        :class="editorDirection === 'ltr' ? 'bg-air-blue/15 text-air-blue' : 'text-primary'"
                                        @click="setEditorDirection('ltr')"
                                    >
                                        LTR
                                    </button>
                                    <button
                                        type="button"
                                        :title="t('blogs_editor.toolbar.rtl')"
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

                                <button type="button" :title="t('blogs_editor.toolbar.link')" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted" @click="createLink">
                                    <i class="las la-link"></i>
                                </button>
                                <button
                                    type="button"
                                    :title="t('blogs_editor.toolbar.insert_image')"
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
                                <button type="button" :title="t('blogs_editor.toolbar.remove_format')" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted" @click="runCommand('removeFormat')">
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
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.cover') }}</label>
                        <input v-model="form.cover_image" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('blogs_editor.fields.cover_placeholder')" />
                        <input
                            ref="coverUploadInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                            @change="selectCoverUpload"
                        />
                        <p class="mt-1 text-xs text-secondary">
                            {{ t('blogs_editor.fields.cover_help') }}
                        </p>
                        <p v-if="form.errors.cover_image" class="mt-1 text-sm text-error">{{ form.errors.cover_image }}</p>
                        <p v-if="form.errors.cover_image_upload" class="mt-1 text-sm text-error">{{ form.errors.cover_image_upload }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blogs_editor.fields.tags') }}</label>
                        <input v-model="form.tags" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('blogs_editor.fields.tags_placeholder')" />
                    </div>

                    <div class="rounded-lg border border-border bg-inputBg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-primary">{{ t('blogs_editor.seo.title') }}</p>
                            <span class="text-sm font-bold" :class="seoScoreClass">{{ seoScore }}%</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-air-green transition-all" :style="{ width: `${seoScore}%` }"></div>
                        </div>
                        <input v-model="form.meta_title" class="mt-3 w-full rounded-lg border-border bg-card text-primary" :placeholder="t('blogs_editor.seo.meta_title_placeholder')" />
                        <textarea v-model="form.meta_description" rows="2" class="mt-3 w-full rounded-lg border-border bg-card text-primary" :placeholder="t('blogs_editor.seo.meta_description_placeholder')"></textarea>
                        <p v-if="publishBlocked" class="mt-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs font-semibold text-error">
                            {{ t('blogs_editor.seo.publish_blocked') }}
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
                        {{ editingPost ? t('Aktualisieren') : t('Erstellen') }}
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
