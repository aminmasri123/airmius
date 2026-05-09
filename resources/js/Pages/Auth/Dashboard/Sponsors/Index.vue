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
const { isDark } = useTheme()

const form = useForm({
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
const isPlatformSponsor = computed(() => !form.club_id)
const canManageSponsors = computed(() => isPlatformSponsor.value || selectedClub.value?.capabilities?.sponsors !== false)

const resetForm = () => {
    editingSponsor.value = null
    form.reset()
    form.club_id = ''
}

const edit = (sponsor) => {
    editingSponsor.value = sponsor
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

const submit = () => {
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
                    Lege Airmius-weite Sponsoren für Outfit-Abos an oder verwalte Sponsoren für einzelne Vereine.
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card px-4 py-3">
                <p class="text-xs uppercase text-secondary">Sponsoren gesamt</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ sponsors.total || sponsors.data?.length || 0 }}</p>
            </div>
        </div>

        <div v-if="!canManageSponsors" class="rounded-lg border border-border bg-inputBg p-4 text-sm text-primary">
            Sponsorenverwaltung für diesen Verein ist ab dem Club-Plan verfuegbar. Waehle einen Verein mit passendem Plan oder lege einen Airmius-Plattform-Sponsor ohne Verein an.
        </div>

        <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-semibold text-primary">Zuordnung</span>
                    <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Airmius Plattform / Outfit-Abo</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        Ohne Verein kann der Sponsor für Airmius-weite Outfit-Abo-Rabatte genutzt werden.
                    </p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Sponsorname</span>
                    <input v-model="form.name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Nike, Stadtwerke, ..." required>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Kontaktperson</span>
                    <input v-model="form.contact_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Ansprechpartner">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">E-Mail</span>
                    <input v-model="form.email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="sponsor@example.com" type="email">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Website</span>
                    <input v-model="form.website" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="https://..." type="url">
                </label>

                <div class="rounded-lg border border-border bg-inputBg p-4 xl:col-span-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-primary">Sponsorlogos nach Farbflaeche</p>
                            <p class="mt-1 text-xs text-secondary">
                                Hinterlege immer zwei Varianten: dunkles Logo für helle Flaechen und helles Logo für dunkle Flaechen.
                            </p>
                        </div>
                        <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">
                            {{ isDark ? 'Aktuell: dunkle Palette' : 'Aktuell: helle Palette' }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block rounded-lg border border-border bg-card p-3">
                            <span class="text-sm font-semibold text-primary">Logo für helle Flaechen</span>
                            <input v-model="form.logo_light" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Dunkles/farbiges Logo, z.B. sponsors/nike-light.webp">
                            <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-white p-3">
                                <img v-if="form.logo_light" :src="previewUrl(form.logo_light)" alt="Logo für helle Flaechen" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-slate-500">Vorschau helle Flaeche</span>
                            </div>
                        </label>

                        <label class="block rounded-lg border border-border bg-card p-3">
                            <span class="text-sm font-semibold text-primary">Logo für dunkle Flaechen</span>
                            <input v-model="form.logo_dark" class="mt-2 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Helles/weisses Logo, z.B. sponsors/nike-dark.webp">
                            <div class="mt-3 flex h-20 items-center justify-center rounded-lg border border-border bg-slate-950 p-3">
                                <img v-if="form.logo_dark" :src="previewUrl(form.logo_dark)" alt="Logo für dunkle Flaechen" class="max-h-full max-w-full object-contain">
                                <span v-else class="text-xs text-slate-400">Vorschau dunkle Flaeche</span>
                            </div>
                        </label>
                    </div>

                    <input v-model="form.logo" type="hidden">
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Budget / Betrag</span>
                    <input v-model="form.amount" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="0,00" type="number" min="0" step="0.01">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Start</span>
                    <input v-model="form.starts_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Ende</span>
                    <input v-model="form.ends_at" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" type="date">
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
                            <span v-if="sponsor.club?.name">{{ sponsor.club.name }}</span>
                            <span v-else class="rounded-full bg-air-blue/10 px-2 py-1 text-xs font-semibold text-air-blue">Airmius Plattform</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.amount || '-' }}</td>
                        <td class="px-4 py-3 text-sm text-secondary">{{ sponsor.starts_at || '-' }} bis {{ sponsor.ends_at || '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button class="rounded border border-border px-3 py-1 text-sm text-primary" @click="edit(sponsor)">Bearbeiten</button>
                                <button class="rounded bg-error px-3 py-1 text-sm text-white" @click="router.delete(route('sponsors.destroy', sponsor.id))">Loeschen</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sponsors.data.length">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-secondary">Noch keine Sponsoren vorhanden.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
