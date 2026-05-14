<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useTheme } from '@/services/useTheme'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sponsors: Object,
    clubs: { type: Array, default: () => [] },
})

const editingSponsor = ref(null)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const { isDark } = useTheme()

const form = useForm({
    scope: 'platform',
    club_id: '',
    name: '',
    contact_name: '',
    email: '',
    website: '',
    logo: '',
    logo_light: '',
    logo_dark: '',
    amount: '',
    starts_at: '',
    ends_at: '',
})

const selectedClub = computed(() => props.clubs.find((club) => Number(club.id) === Number(form.club_id)) || null)
const isClubSponsor = computed(() => form.scope === 'club')
const canManageSponsors = computed(() => !isClubSponsor.value || selectedClub.value?.capabilities?.sponsors !== false)

const scopeLabel = (sponsor) => ({
    platform: 'Airmius Plattform',
    outfit_subscription: 'Outfit-Abo',
    club: sponsor.club?.name || 'Verein',
}[sponsor.scope || (sponsor.club_id ? 'club' : 'platform')] || 'Airmius Plattform')

const scopeBadgeClass = (sponsor) => ({
    platform: 'bg-air-blue/10 text-air-blue',
    outfit_subscription: 'bg-accent/10 text-accent',
    club: 'bg-inputBg text-secondary',
}[sponsor.scope || (sponsor.club_id ? 'club' : 'platform')] || 'bg-air-blue/10 text-air-blue')

const resetForm = () => {
    editingSponsor.value = null
    form.reset()
    form.scope = 'platform'
    form.club_id = ''
}

const edit = (sponsor) => {
    editingSponsor.value = sponsor
    form.scope = sponsor.scope || (sponsor.club_id ? 'club' : 'platform')
    form.club_id = sponsor.club_id || ''
    form.name = sponsor.name
    form.contact_name = sponsor.contact_name || ''
    form.email = sponsor.email || ''
    form.website = sponsor.website || ''
    form.logo = sponsor.logo || ''
    form.logo_light = sponsor.logo_light || sponsor.logo || ''
    form.logo_dark = sponsor.logo_dark || sponsor.logo_light || sponsor.logo || ''
    form.amount = sponsor.amount || ''
    form.starts_at = sponsor.starts_at || ''
    form.ends_at = sponsor.ends_at || ''
}

const openDeleteModal = (sponsor) => {
    deleteTarget.value = sponsor
    deleteConfirmation.value = ''
}

const closeDeleteModal = () => {
    deleteTarget.value = null
    deleteConfirmation.value = ''
}

const confirmDelete = () => {
    if (!deleteTarget.value || deleteConfirmation.value !== 'delete') {
        return
    }

    router.delete(route('sponsors.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    })
}

const submit = () => {
    if (form.scope !== 'club') {
        form.club_id = ''
    }

    const options = { preserveScroll: true, onSuccess: resetForm }

    editingSponsor.value
        ? form.put(route('sponsors.update', editingSponsor.value.id), options)
        : form.post(route('sponsors.store'), options)
}

const sponsorLogoUrl = (sponsor) => isDark.value
    ? (sponsor.logo_dark_url || sponsor.logo_light_url || sponsor.logo_url || sponsor.logo)
    : (sponsor.logo_light_url || sponsor.logo_dark_url || sponsor.logo_url || sponsor.logo)

const previewUrl = (source) => {
    if (!source) return ''
    if (source.startsWith('http://') || source.startsWith('https://') || source.startsWith('/')) return source

    return `/storage/${source}`
}
</script>

<template>
    <Head title="Sponsoren" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Sponsoring</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Sponsoren verwalten</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    Lege Sponsoren fuer die Airmius-Plattform, Outfit-Abos oder einzelne Vereine an.
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card px-4 py-3">
                <p class="text-xs uppercase text-secondary">Sponsoren gesamt</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ sponsors.total || sponsors.data?.length || 0 }}</p>
            </div>
        </div>

        <div v-if="!canManageSponsors" class="rounded-lg border border-border bg-inputBg p-4 text-sm text-primary">
            Sponsorenverwaltung fuer diesen Verein ist ab dem Club-Plan verfuegbar. Waehle einen Verein mit passendem Plan oder eine Plattform-/Outfit-Abo-Zuordnung.
        </div>

        <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsor-Art</span>
                    <select v-model="form.scope" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="platform">Airmius Plattform</option>
                        <option value="outfit_subscription">Outfit-Abo</option>
                        <option value="club">Verein</option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        Plattform-Sponsoren unterstuetzen Airmius allgemein. Outfit-Abo-Sponsoren koennen bei Outfit-Abo-Plaenen genutzt werden.
                    </p>
                    <div v-if="form.errors.scope" class="mt-1 text-sm text-error">{{ form.errors.scope }}</div>
                </label>

                <label v-if="form.scope === 'club'" class="block">
                    <span class="text-sm font-semibold text-primary">Verein</span>
                    <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Verein auswaehlen</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">Der Sponsor wird nur diesem Verein zugeordnet.</p>
                    <div v-if="form.errors.club_id" class="mt-1 text-sm text-error">{{ form.errors.club_id }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsorname</span>
                    <input v-model="form.name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Nike, Stadtwerke, ..." required>
                    <div v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Kontaktperson</span>
                    <input v-model="form.contact_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Ansprechpartner">
                    <div v-if="form.errors.contact_name" class="mt-1 text-sm text-error">{{ form.errors.contact_name }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">E-Mail</span>
                    <input v-model="form.email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="sponsor@example.com" type="email">
                    <div v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Website</span>
                    <input v-model="form.website" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://..." type="url">
                    <div v-if="form.errors.website" class="mt-1 text-sm text-error">{{ form.errors.website }}</div>
                </label>

                <div class="rounded-lg border border-border bg-inputBg p-4 xl:col-span-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-primary">Sponsorlogos nach Farbflaeche</p>
                            <p class="mt-1 text-xs text-secondary">
                                Hinterlege immer zwei Varianten: dunkles Logo fuer helle Flaechen und helles Logo fuer dunkle Flaechen.
                            </p>
                        </div>
                        <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">
                            {{ isDark ? 'Aktuell: dunkle Palette' : 'Aktuell: helle Palette' }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block rounded-lg border border-border bg-card p-3">
                            <span class="text-sm font-semibold text-primary">Logo fuer helle Flaechen</span>
                            <input v-model="form.logo_light" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Dunkles/farbiges Logo, z.B. sponsors/nike-light.webp">
                            <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-white p-3">
                                <img v-if="form.logo_light" :src="previewUrl(form.logo_light)" alt="Logo fuer helle Flaechen" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-slate-500">Vorschau helle Flaeche</span>
                            </div>
                        </label>

                        <label class="block rounded-lg border border-border bg-card p-3">
                            <span class="text-sm font-semibold text-primary">Logo fuer dunkle Flaechen</span>
                            <input v-model="form.logo_dark" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Helles/weisses Logo, z.B. sponsors/nike-dark.webp">
                            <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-slate-950 p-3">
                                <img v-if="form.logo_dark" :src="previewUrl(form.logo_dark)" alt="Logo fuer dunkle Flaechen" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-slate-400">Vorschau dunkle Flaeche</span>
                            </div>
                        </label>
                    </div>

                    <input v-model="form.logo" type="hidden">
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Budget / Betrag</span>
                    <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="0,00" type="number" min="0" step="0.01">
                    <div v-if="form.errors.amount" class="mt-1 text-sm text-error">{{ form.errors.amount }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Start</span>
                    <input v-model="form.starts_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                    <div v-if="form.errors.starts_at" class="mt-1 text-sm text-error">{{ form.errors.starts_at }}</div>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Ende</span>
                    <input v-model="form.ends_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                    <div v-if="form.errors.ends_at" class="mt-1 text-sm text-error">{{ form.errors.ends_at }}</div>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.processing || !canManageSponsors">
                    {{ editingSponsor ? 'Sponsor speichern' : 'Sponsor erstellen' }}
                </button>
                <button v-if="editingSponsor" type="button" class="rounded-lg border border-border px-4 py-2 text-primary" @click="resetForm">
                    Abbrechen
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-border bg-card">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr class="text-left text-xs uppercase text-secondary">
                        <th class="px-4 py-3">Sponsor</th>
                        <th class="px-4 py-3">Zuordnung</th>
                        <th class="px-4 py-3">Betrag</th>
                        <th class="px-4 py-3">Laufzeit</th>
                        <th class="px-4 py-3">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="sponsor in sponsors.data" :key="sponsor.id">
                        <td class="px-4 py-3 text-sm font-semibold text-primary">
                            <div class="flex items-center gap-3">
                                <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" class="h-9 w-9 rounded-lg object-contain">
                                <div v-else class="flex h-9 w-9 items-center justify-center rounded-lg bg-inputBg text-xs font-bold text-primary">
                                    {{ sponsor.name?.slice(0, 2)?.toUpperCase() }}
                                </div>
                                <span>{{ sponsor.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-secondary">
                            <span :class="['rounded-full px-2 py-1 text-xs font-semibold', scopeBadgeClass(sponsor)]">
                                {{ scopeLabel(sponsor) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.amount || '-' }}</td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.starts_at || '-' }} bis {{ sponsor.ends_at || '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button class="rounded border border-border px-3 py-1 text-sm text-primary" @click="edit(sponsor)">Bearbeiten</button>
                                <button class="rounded bg-error px-3 py-1 text-sm text-white" @click="openDeleteModal(sponsor)">Loeschen</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sponsors.data.length">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-secondary">Noch keine Sponsoren vorhanden.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport to="body">
            <div
                v-if="deleteTarget"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 px-4"
                @click.self="closeDeleteModal"
            >
                <div class="w-full max-w-md rounded-xl border border-border bg-card p-5 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-error">Sponsor loeschen</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">{{ deleteTarget.name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                Dieser Sponsor wird dauerhaft geloescht. Gib zur Bestaetigung <span class="font-semibold text-primary">delete</span> ein.
                            </p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-1 text-secondary hover:text-primary" @click="closeDeleteModal">
                            x
                        </button>
                    </div>

                    <input
                        v-model="deleteConfirmation"
                        class="mt-4 w-full rounded-lg border-border bg-inputBg text-primary"
                        placeholder="delete"
                        autocomplete="off"
                    >

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="closeDeleteModal">
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="deleteConfirmation !== 'delete'"
                            @click="confirmDelete"
                        >
                            Endgueltig loeschen
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
