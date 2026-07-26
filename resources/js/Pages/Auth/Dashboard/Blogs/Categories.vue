<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirmDialog } from '@/services/dialogService'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n({ useScope: 'global' })
const localeCode = computed(() => String(locale.value || 'de').replace('_', '-'))
const formatNumber = (value) => new Intl.NumberFormat(localeCode.value).format(Number(value || 0))
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const props = defineProps({
    categories: Object,
})

const editingCategory = ref(null)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
})

const resetForm = () => {
    editingCategory.value = null
    form.reset()
    form.clearErrors()
    form.sort_order = 0
    form.is_active = true
}

const edit = (category) => {
    editingCategory.value = category
    form.name = category.name || ''
    form.slug = category.slug || ''
    form.description = category.description || ''
    form.sort_order = category.sort_order ?? 0
    form.is_active = Boolean(category.is_active)
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetForm,
    }

    if (editingCategory.value) {
        form.put(route('blog-categories.update', editingCategory.value.id), options)

        return
    }

    form.post(route('blog-categories.store'), options)
}

const destroyCategory = async (category) => {
    const confirmed = await confirmDialog({
        title: t('blog_categories.delete_title'),
        message: t('blog_categories.delete_message', { name: category.name }),
        confirmLabel: t('blog_categories.actions.delete'),
        danger: true,
    })

    if (!confirmed) {
        return
    }

    router.delete(route('blog-categories.destroy', category.id), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('blog_categories.page_title')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ t('blog_categories.eyebrow') }}</p>
                <h1 class="mt-1 text-3xl font-bold text-primary">{{ t('blog_categories.title') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-secondary">
                    {{ t('blog_categories.intro') }}
                </p>
            </div>

            <Link :href="route('blogs.index')" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-muted">
                {{ t('blog_categories.back') }}
            </Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="surface-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/60 text-left text-xs uppercase tracking-wider text-secondary">
                            <tr>
                                <th class="px-4 py-3">{{ t('blog_categories.fields.name') }}</th>
                                <th class="px-4 py-3">{{ t('blog_categories.fields.slug') }}</th>
                                <th class="px-4 py-3">{{ t('blog_categories.fields.sort_order') }}</th>
                                <th class="px-4 py-3">{{ t('blog_categories.fields.status') }}</th>
                                <th class="px-4 py-3">{{ t('blog_categories.fields.posts') }}</th>
                                <th class="px-4 py-3 text-right">{{ t('blog_categories.fields.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="category in categories.data" :key="category.id">
                                <td class="px-4 py-3 font-semibold text-primary">{{ category.name }}</td>
                                <td class="px-4 py-3 text-secondary">{{ category.slug }}</td>
                                <td class="px-4 py-3 text-secondary">{{ category.sort_order }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="category.is_active ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                                    >
                                {{ category.is_active ? t('blog_categories.status.active') : t('blog_categories.status.inactive') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-secondary">{{ formatNumber(category.posts_count) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button class="rounded-lg border border-border px-3 py-2 text-primary hover:bg-muted" @click="edit(category)">
                                            {{ t('blog_categories.actions.edit') }}
                                        </button>
                                        <button class="rounded-lg bg-error px-3 py-2 text-white" @click="destroyCategory(category)">
                                            {{ t('blog_categories.actions.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!categories.data.length" class="p-8 text-center text-secondary">
                    {{ t('blog_categories.empty') }}
                </div>

                <div v-if="categories.links?.length > 3" class="flex flex-wrap gap-2 border-t border-border p-4">
                    <Link
                        v-for="link in categories.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border border-border px-3 py-2 text-sm"
                        :class="link.active ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary hover:bg-muted'"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                </div>
            </section>

            <aside class="surface-card h-fit p-5">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-secondary">
                            {{ editingCategory ? t('blog_categories.form.edit_eyebrow') : t('blog_categories.form.new_eyebrow') }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold text-primary">
                            {{ editingCategory ? editingCategory.name : t('blog_categories.form.create_title') }}
                        </h2>
                    </div>
                    <button v-if="editingCategory" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="resetForm">
                        {{ t('blog_categories.actions.new') }}
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blog_categories.fields.name') }}</label>
                        <input v-model="form.name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blog_categories.fields.slug') }}</label>
                        <input v-model="form.slug" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" :placeholder="t('blog_categories.form.slug_placeholder')" />
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-error">{{ form.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">{{ t('blog_categories.fields.description') }}</label>
                        <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></textarea>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-semibold text-primary">{{ t('blog_categories.fields.sort_order') }}</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        </div>
                        <label class="flex items-center gap-3 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-border text-air-blue" />
                            {{ t('blog_categories.status.active') }}
                        </label>
                    </div>

                    <p v-if="form.errors.category" class="text-sm text-error">{{ form.errors.category }}</p>

                    <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
                        {{ editingCategory ? t('blog_categories.actions.update') : t('blog_categories.actions.create') }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
