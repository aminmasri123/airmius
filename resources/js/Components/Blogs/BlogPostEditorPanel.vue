<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    editingPost: { type: Object, default: null },
    can: { type: Object, required: true },
    form: { type: Object, required: true },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    toolbarGroups: { type: Array, default: () => [] },
    contentStyles: { type: Array, default: () => [] },
    semanticInlineStyles: { type: Array, default: () => [] },
    editorDirection: { type: String, default: 'ltr' },
    contentImageUploading: { type: Boolean, default: false },
    seoChecks: { type: Array, default: () => [] },
    seoScore: { type: Number, default: 0 },
    seoScoreClass: { type: String, default: '' },
    publishBlocked: { type: Boolean, default: false },
    resetForm: { type: Function, required: true },
    submit: { type: Function, required: true },
    applyContentStyle: { type: Function, required: true },
    applyBlock: { type: Function, required: true },
    runCommand: { type: Function, required: true },
    setEditorDirection: { type: Function, required: true },
    applySemanticInlineStyle: { type: Function, required: true },
    createLink: { type: Function, required: true },
    selectContentImage: { type: Function, required: true },
    uploadContentImage: { type: Function, required: true },
    syncEditor: { type: Function, required: true },
    selectCoverUpload: { type: Function, required: true },
})

const emit = defineEmits([
    'set-cover-upload-input',
    'set-content-image-input',
    'set-editor',
])

const setCoverUploadInput = (element) => {
    emit('set-cover-upload-input', element)
}

const setContentImageInput = (element) => {
    emit('set-content-image-input', element)
}

const setEditor = (element) => {
    emit('set-editor', element)
}
</script>

<template>
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
                        <option value="">Kategorie wählen</option>
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
                            title="Bild in Inhalt einfügen"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm text-primary hover:bg-muted disabled:opacity-60"
                            :disabled="contentImageUploading"
                            @click="selectContentImage"
                        >
                            <i class="las la-image"></i>
                        </button>
                        <input
                            :ref="setContentImageInput"
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
                        :ref="setEditor"
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
                    :ref="setCoverUploadInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                    @change="selectCoverUpload"
                />
                <p class="mt-1 text-xs text-secondary">
                    Empfohlenes Format: 1600 x 900 px im Querformat. Link einfügen oder Bild hochladen. Wenn beides gesetzt ist, wird der Upload verwendet.
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
                    Veröffentlichen ist ab 85% SEO-Qualität möglich.
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

