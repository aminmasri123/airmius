<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    guidelines: { type: Array, default: () => [] },
})

const search = ref('')
const selectedCategory = ref('all')

const categories = computed(() => [
    'all',
    ...new Set(props.guidelines.map((item) => item.category)),
])

const filteredGuidelines = computed(() => {
    const term = search.value.trim().toLowerCase()

    return props.guidelines.filter((item) => {
        const matchesCategory = selectedCategory.value === 'all' || item.category === selectedCategory.value
        const matchesSearch = !term || [
            item.category,
            item.name,
            item.dimensions,
            item.ratio,
            item.formats,
            item.note,
        ].some((value) => String(value || '').toLowerCase().includes(term))

        return matchesCategory && matchesSearch
    })
})
</script>

<template>
    <Head title="Bildmasse" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Media & Content</p>
                <h1 class="mt-1 text-3xl font-bold text-primary">Empfohlene Bildmasse</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Zentrale Uebersicht fuer Redakteure, Admins und Vereine: welche Bildgroessen fuer Profile, Blog,
                    Posts, Videos und weitere Medien am besten funktionieren.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    v-model="search"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="Suchen..."
                />
                <select v-model="selectedCategory" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="all">Alle Bereiche</option>
                    <option v-for="category in categories.filter((category) => category !== 'all')" :key="category" :value="category">
                        {{ category }}
                    </option>
                </select>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Standard Upload</p>
                <p class="mt-2 text-2xl font-bold text-primary">JPG, PNG, WebP</p>
                <p class="mt-1 text-sm text-secondary">Diese Formate funktionieren fuer fast alle Bildbereiche.</p>
            </article>
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Login-Slider</p>
                <p class="mt-2 text-2xl font-bold text-primary">9:16</p>
                <p class="mt-1 text-sm text-secondary">Hochformat fuer die rechte Login-Seite, ideal 1080 x 1920 px.</p>
            </article>
            <article class="rounded-lg border border-border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-secondary">Profil & Produkte</p>
                <p class="mt-2 text-2xl font-bold text-primary">1:1</p>
                <p class="mt-1 text-sm text-secondary">Quadratische Bilder bleiben in Listen und Karten stabil.</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-inputBg text-left text-xs font-semibold uppercase tracking-wider text-secondary">
                        <tr>
                            <th class="px-4 py-3">Bereich</th>
                            <th class="px-4 py-3">Bildtyp</th>
                            <th class="px-4 py-3">Empfohlen</th>
                            <th class="px-4 py-3">Verhaeltnis</th>
                            <th class="px-4 py-3">Format</th>
                            <th class="px-4 py-3">Max.</th>
                            <th class="px-4 py-3">Hinweis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in filteredGuidelines" :key="`${item.category}-${item.name}`" class="align-top">
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                    {{ item.category }}
                                </span>
                            </td>
                            <td class="px-4 py-4 font-semibold text-primary">{{ item.name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-primary">{{ item.dimensions }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-secondary">{{ item.ratio }}</td>
                            <td class="px-4 py-4 text-secondary">{{ item.formats }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-secondary">{{ item.max_size }}</td>
                            <td class="min-w-72 px-4 py-4 leading-6 text-secondary">{{ item.note }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!filteredGuidelines.length" class="p-8 text-center text-sm text-secondary">
                Keine passenden Bildmasse gefunden.
            </div>
        </section>
    </div>
</template>
