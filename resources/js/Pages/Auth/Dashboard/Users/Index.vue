<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'
import { ref, watch } from 'vue'

const props = defineProps({
    users: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            from: null,
            to: null,
            total: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
        }),
    },
})

const searchQuery = ref(props.filters.search || '')
const showDeleteModal = ref(false)
const userToDelete = ref(null)

const createUser = () => {
    router.visit(route('members.create'))
}

const clearSearch = () => {
    searchQuery.value = ''
}

let searchTimeout = null
watch(searchQuery, (value) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(route('members.index'), { search: value || undefined }, {
            preserveState: true,
            replace: true,
            only: ['users', 'filters'],
        })
    }, 300)
})

const visitPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    })
}

const openDeleteModal = (user) => {
    userToDelete.value = user
    showDeleteModal.value = true
}

const handleDeleteConfirm = () => {
    if (userToDelete.value) {
        router.delete(route('members.destroy', userToDelete.value.id))
        showDeleteModal.value = false
        userToDelete.value = null
    }
}

const handleDeleteCancel = () => {
    showDeleteModal.value = false
    userToDelete.value = null
}
</script>

<template>
    <AppLayout>

        <Head title="Nutzerverwaltung" />

        <div class="space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-primary">Admin Dashboard – Nutzer</h1>
                <p class="mt-2 text-sm text-secondary">
                    Übersicht aller registrierten Nutzer.
                </p>
            </div>

            <!-- Suchfeld und Aktionen -->
            <div class="flex mb-4 ">
                <button @click="createUser"
                    class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-l-lg hover:bg-primary/90 transition">
                    <i class="las la-plus "></i>

                </button>

                <div class="relative flex-1">
                    <input v-model="searchQuery" type="text" placeholder="Suche nach Name oder E-Mail..."
                        class="w-full px-4 py-2 border border-border bg-card text-primary placeholder-secondary focus:ring-1 focus:ring-bg " />
                    
                </div>
                    <button @click="clearSearch"
                    class="inline-flex items-center justify-center px-4 py-2 bg-buttonPrimary text-buttonTextPrimary rounded-r-lg hover:bg-primary/90 transition">
                        <i class="las la-sync"></i>
                    </button>
            </div>

            <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-card">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                E-Mail</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Erstellt am</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary">
                                Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-table">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="px-4 py-4 text-sm text-primary">{{ user.id }}</td>
                            <td class="px-4 py-4 text-sm text-primary">{{ user.name }}</td>
                            <td class="px-4 py-4 text-sm text-primary">{{ user.email }}</td>
                            <td class="px-4 py-4 text-sm text-primary">{{ new
                                Date(user.created_at).toLocaleString('de-DE') }}</td>
                            <td class="px-4 py-4 text-sm">
                                <div class="flex space-x-2">
                                    <button @click="router.visit(route('members.edit', user.id))"
                                        class="px-3 py-1 bg-primary text-buttonTextPrimary rounded hover:bg-primary/80 transition-colors">
                                        Bearbeiten
                                    </button>
                                    <button @click="openDeleteModal(user)"
                                        class="px-3 py-1 bg-error text-buttonTextSecondary rounded hover:bg-error/80 transition-colors">
                                        Löschen
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-4 py-4 text-center text-sm text-secondary">
                                Keine Nutzer gefunden.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-secondary">
                    <span v-if="users.total > 0">
                        Zeige {{ users.from }} bis {{ users.to }} von {{ users.total }} Nutzern.
                    </span>
                    <span v-else>Keine Nutzer vorhanden.</span>
                </p>

                <div v-if="users.links.length > 3" class="flex flex-wrap gap-1">
                    <button
                        v-for="link in users.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        @click="visitPage(link.url)"
                        class="min-w-10 rounded border border-border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="link.active
                            ? 'bg-primary text-buttonTextPrimary'
                            : 'bg-card text-primary hover:bg-secondary/20'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Nutzer löschen"
            :message="`Sind Sie sicher, dass Sie ${userToDelete?.name} löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.`"
            confirm-text="delete"
            @confirm="handleDeleteConfirm"
            @cancel="handleDeleteCancel"
        />
    </AppLayout>
</template>
