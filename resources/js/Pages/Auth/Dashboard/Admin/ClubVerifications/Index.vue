<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const notice = ref(null)

const forms = reactive(Object.fromEntries(props.clubs.map((club) => [
    club.id,
    {
        official_club_number: club.requested_official_club_number || club.official_club_number || '',
        verification_notes: club.verification_notes || '',
        mark_official: Boolean(club.requested_official_club_number || club.official_club_number || club.is_official),
        processing: false,
    },
])))

const pendingCount = computed(() => props.clubs.filter((club) => club.verification_status === 'pending_verification').length)

const statusLabel = (status) => ({
    pending_verification: 'Wartet auf Prüfung',
    verified: 'Freigegeben',
    rejected: 'Abgelehnt',
}[status] || status)

const statusClass = (status) => ({
    pending_verification: 'border-warning/30 bg-warning/10 text-warning',
    verified: 'border-success/30 bg-success/10 text-success',
    rejected: 'border-error/30 bg-error/10 text-error',
}[status] || 'border-border bg-muted text-secondary')

const dateLabel = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const submit = (club, action) => {
    const form = forms[club.id]
    form.processing = true
    notice.value = null

    const routeName = action === 'approve'
        ? 'admin.club-verifications.approve'
        : 'admin.club-verifications.reject'

    router.put(route(routeName, club.id), {
        official_club_number: form.official_club_number,
        verification_notes: form.verification_notes,
        mark_official: form.mark_official,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = { type: 'success', message: page.props.flash?.success || 'Änderung gespeichert.' }
        },
        onError: () => {
            notice.value = { type: 'error', message: 'Die Änderung konnte nicht gespeichert werden. Bitte prüfe die Eingaben.' }
        },
        onFinish: () => {
            form.processing = false
        },
    })
}
</script>

<template>
    <Head title="Vereinsprüfung" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-primary">Vereinsprüfung</h1>
                    <p class="mt-1 text-sm text-secondary">
                        Neue Vereinsantraege freigeben, ablehnen und Vereinsnummern prüfen.
                    </p>
                </div>
                <span class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary">
                    {{ pendingCount }} offen
                </span>
            </div>
        </section>

        <div
            v-if="notice"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="notice.type === 'success' ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error'"
        >
            {{ notice.message }}
        </div>

        <section v-if="clubs.length" class="space-y-3">
            <article
                v-for="club in clubs"
                :key="club.id"
                class="surface-card p-5"
            >
                <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-primary">{{ club.name }}</h2>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass(club.verification_status)">
                                {{ statusLabel(club.verification_status) }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-secondary">Antragsteller</dt>
                                <dd class="text-primary">{{ club.owner?.name || '-' }}</dd>
                                <dd class="text-xs text-secondary">{{ club.owner?.email || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">Sportart</dt>
                                <dd class="text-primary">{{ club.sport_type || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">Ort</dt>
                                <dd class="text-primary">{{ [club.city, club.country].filter(Boolean).join(', ') || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">Beantragt am</dt>
                                <dd class="text-primary">{{ dateLabel(club.verification_requested_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">Beantragte Vereinsnummer</dt>
                                <dd class="text-primary">{{ club.requested_official_club_number || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">Aktuelle Vereinsnummer</dt>
                                <dd class="text-primary">{{ club.official_club_number || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="space-y-3 rounded-lg border border-border bg-bg p-4">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Geprüfte Vereinsnummer</span>
                            <input v-model="forms[club.id].official_club_number" class="input" placeholder="Optional" />
                        </label>

                        <label class="flex items-start gap-3 text-sm text-primary">
                            <input v-model="forms[club.id].mark_official" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                            <span>Als offiziellen Verein mit Badge markieren</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Notiz</span>
                            <textarea v-model="forms[club.id].verification_notes" class="input min-h-24" placeholder="Grund bei Ablehnung oder interner Hinweis"></textarea>
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                                :disabled="forms[club.id].processing"
                                @click="submit(club, 'approve')"
                            >
                                Freigeben
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-error px-4 py-2 text-sm font-semibold text-error disabled:opacity-50"
                                :disabled="forms[club.id].processing"
                                @click="submit(club, 'reject')"
                            >
                                Ablehnen
                            </button>
                            <Link :href="route('auth.clubs.show', club.id)" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                                Öffnen
                            </Link>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section v-else class="surface-card p-8 text-center text-sm text-secondary">
            Keine Vereinsantraege vorhanden.
        </section>
    </div>
</template>

