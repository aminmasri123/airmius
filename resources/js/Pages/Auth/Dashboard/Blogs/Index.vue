<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    posts: Object,
    filters: Object,
    can: Object,
})

const editingPost = ref(null)
const filterStatus = ref(props.filters?.status || 'all')
const search = ref(props.filters?.search || '')
const coverUploadInput = ref(null)

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    cover_image: '',
    cover_image_upload: null,
    category: '',
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

const statusClasses = {
    draft: 'bg-muted text-secondary',
    review: 'bg-air-orange/15 text-air-orange',
    published: 'bg-air-green/15 text-air-green',
    archived: 'bg-error/15 text-error',
}

const resetForm = () => {
    editingPost.value = null
    form.reset()
    form.clearErrors()
    form.status = 'draft'
    form._method = ''
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
    form.tags = (post.tags || []).join(', ')
    form.meta_title = post.meta_title || ''
    form.meta_description = post.meta_description || ''
    form.status = props.can.publish ? post.status : (post.status === 'published' ? 'review' : post.status)
    form.published_at = post.published_at ? post.published_at.slice(0, 16) : ''
    form._method = ''
    if (coverUploadInput.value) coverUploadInput.value.value = null
}

const selectCoverUpload = (event) => {
    form.cover_image_upload = event.target.files?.[0] || null
}

const submit = () => {
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

const destroyPost = (post) => {
    if (!confirm(`Blogbeitrag "${post.title}" wirklich löschen?`)) {
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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Website CMS</p>
                <h1 class="mt-1 text-3xl font-bold text-primary">Blogs</h1>
                <p class="mt-2 max-w-2xl text-sm text-secondary">
                    Redaktionsbereich für Websitepersonal: Entwürfe schreiben, Reviews vorbereiten und Beiträge veröffentlichen.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
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
                <button class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-muted" @click="applyFilters">
                    Filtern
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-4">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="surface-card overflow-hidden transition hover:border-air-blue/50"
                >
                    <div class="grid gap-4 p-4 md:grid-cols-[180px_1fr]">
                        <div class="flex h-36 items-center justify-center overflow-hidden rounded-lg bg-inputBg">
                            <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" class="h-full w-full object-cover" />
                            <i v-else class="las la-newspaper text-5xl text-secondary"></i>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="[statusClasses[post.status], 'rounded-full px-3 py-1 text-xs font-semibold']">
                                    {{ post.status }}
                                </span>
                                <span v-if="post.category" class="rounded-full border border-border px-3 py-1 text-xs text-secondary">
                                    {{ post.category }}
                                </span>
                                <span class="text-xs text-secondary">
                                    {{ post.author?.name || 'Unbekannt' }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-xl font-bold text-primary">{{ post.title }}</h2>
                            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-secondary">
                                {{ post.excerpt || post.content }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    v-if="can.update"
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                                    @click="edit(post)"
                                >
                                    Bearbeiten
                                </button>
                                <Link
                                    v-if="post.status === 'published'"
                                    :href="route('guest.blog.show', post.slug)"
                                    class="rounded-lg border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                                >
                                    Anzeigen
                                </Link>
                                <button
                                    v-if="can.delete"
                                    class="rounded-lg bg-error px-3 py-2 text-sm text-white"
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

            <aside class="surface-card h-fit p-5">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-secondary">
                            {{ editingPost ? 'Beitrag bearbeiten' : 'Neuer Beitrag' }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold text-primary">
                            {{ editingPost ? editingPost.title : 'Schreiben' }}
                        </h2>
                    </div>
                    <button v-if="editingPost" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="resetForm">
                        Neu
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
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
                            <label class="text-sm font-semibold text-primary">Kategorie</label>
                            <input v-model="form.category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
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
                        <textarea v-model="form.content" rows="10" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required></textarea>
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
                        <p class="text-sm font-semibold text-primary">SEO</p>
                        <input v-model="form.meta_title" class="mt-3 w-full rounded-lg border-border bg-card text-primary" placeholder="Meta Title" />
                        <textarea v-model="form.meta_description" rows="2" class="mt-3 w-full rounded-lg border-border bg-card text-primary" placeholder="Meta Description"></textarea>
                    </div>

                    <button
                        class="w-full rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="form.processing || (!editingPost && !can.create)"
                    >
                        {{ editingPost ? 'Aktualisieren' : 'Erstellen' }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
