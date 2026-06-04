import { router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'
import { confirmDialog, promptDialog } from '@/services/dialogService'

export function useBlogStudio(props) {
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
            hint: `${contentWordCount.value} Wörter`,
        },
        {
            label: 'Kategorie gesetzt',
            passed: Boolean(form.blog_category_id || form.category),
            hint: 'Für Archiv, Breadcrumbs und Related Posts',
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

    const setCoverUploadInputElement = (element) => {
        coverUploadInput.value = element
    }

    const setContentImageInputElement = (element) => {
        contentImageInput.value = element
    }

    const setEditorElement = (element) => {
        editorRef.value = element
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

    return {
        applyBlock,
        applyContentStyle,
        applyFilters,
        applySemanticInlineStyle,
        categoryOptions,
        contentImageUploading,
        contentStyles,
        createLink,
        destroyPost,
        edit,
        editingPost,
        editorDirection,
        filterStatus,
        form,
        formatDateTime,
        publishBlocked,
        resetForm,
        runCommand,
        search,
        selectContentImage,
        selectCoverUpload,
        semanticInlineStyles,
        seoChecks,
        seoScore,
        seoScoreClass,
        setContentImageInputElement,
        setCoverUploadInputElement,
        setEditorDirection,
        setEditorElement,
        statusClasses,
        statusOptions,
        stripHtml,
        submit,
        syncEditor,
        toolbarGroups,
        uploadContentImage,
    }
}

