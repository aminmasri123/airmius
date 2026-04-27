<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sponsors: Object,
    clubs: Array,
})

const editingSponsor = ref(null)

const form = useForm({
    club_id: props.clubs?.[0]?.id || '',
    name: '',
    contact_name: '',
    email: '',
    website: '',
    logo: '',
    amount: '',
    starts_at: '',
    ends_at: '',
})

const resetForm = () => {
    editingSponsor.value = null
    form.reset()
    form.club_id = props.clubs?.[0]?.id || ''
}

const edit = (sponsor) => {
    editingSponsor.value = sponsor
    form.club_id = sponsor.club_id
    form.name = sponsor.name
    form.contact_name = sponsor.contact_name || ''
    form.email = sponsor.email || ''
    form.website = sponsor.website || ''
    form.logo = sponsor.logo || ''
    form.amount = sponsor.amount || ''
    form.starts_at = sponsor.starts_at || ''
    form.ends_at = sponsor.ends_at || ''
}

const submit = () => {
    const options = { preserveScroll: true, onSuccess: resetForm }

    editingSponsor.value
        ? form.put(route('sponsors.update', editingSponsor.value.id), options)
        : form.post(route('sponsors.store'), options)
}
</script>

<template>
    <Head title="Sponsors" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Sponsors</h1>
            <p class="mt-1 text-sm text-secondary">Sponsor management per organization.</p>
        </div>

        <form class="grid gap-3 rounded-lg border border-border bg-card p-4 md:grid-cols-3" @submit.prevent="submit">
            <select v-model="form.club_id" class="rounded-lg border-border bg-inputBg text-primary">
                <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
            </select>
            <input v-model="form.name" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Name" required />
            <input v-model="form.contact_name" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Contact" />
            <input v-model="form.email" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Email" />
            <input v-model="form.website" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Website" />
            <input v-model="form.amount" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Amount" type="number" min="0" step="0.01" />
            <input v-model="form.starts_at" class="rounded-lg border-border bg-inputBg text-primary" type="date" />
            <input v-model="form.ends_at" class="rounded-lg border-border bg-inputBg text-primary" type="date" />
            <div class="flex gap-2">
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" :disabled="form.processing">
                    {{ editingSponsor ? 'Update' : 'Create' }}
                </button>
                <button v-if="editingSponsor" type="button" class="rounded-lg border border-border px-4 py-2 text-primary" @click="resetForm">
                    Cancel
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-border bg-card">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr class="text-left text-xs uppercase text-secondary">
                        <th class="px-4 py-3">Sponsor</th>
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="sponsor in sponsors.data" :key="sponsor.id">
                        <td class="px-4 py-3 text-sm text-primary">{{ sponsor.name }}</td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.club?.name }}</td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.amount || '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button class="rounded border border-border px-3 py-1 text-sm text-primary" @click="edit(sponsor)">Edit</button>
                                <button class="rounded bg-error px-3 py-1 text-sm text-white" @click="router.delete(route('sponsors.destroy', sponsor.id))">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sponsors.data.length">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-secondary">No sponsors yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
