<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { usePermissions } from '@/composables/usePermissions'
import ClubDeletionPanel from '@/Components/Clubs/ClubDeletionPanel.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
})

const page = usePage()
const { t } = useI18n()
const { can } = usePermissions()
const requestedClubId = Number(new URLSearchParams(String(page.url || '').split('?')[1] || '').get('club_id') || 0)
const selectedClubId = ref(props.clubs.some((club) => Number(club.id) === requestedClubId) ? requestedClubId : (props.clubs[0]?.id || null))

watch(() => props.clubs, (clubs) => {
    if (!clubs.some((club) => club.id === selectedClubId.value)) {
        selectedClubId.value = clubs[0]?.id || null
    }
})

const selectedClub = computed(() => props.clubs.find((club) => club.id === selectedClubId.value) || props.clubs[0] || null)
const onboarding = computed(() => selectedClub.value?.onboarding || null)
const locale = computed(() => page.props.locale || 'de')
const currency = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }))
const numberFormat = computed(() => new Intl.NumberFormat(locale.value))
const clubTasks = ref([])
const clubTaskMeta = ref({ members: [], teams: [] })
const clubTasksLoading = ref(false)
const clubTasksSaving = ref(false)
const clubTaskError = ref('')
const clubTaskDraft = ref({ title: '', description: '', priority: 'normal', status: 'open', visibility: 'club', participant_ids: [], team_id: '', due_at: '', checklist_text: '', attachment_links_text: '' })
const clubTaskDraftFiles = ref([])
const clubTaskComments = ref({})
const activeWorkPanel = ref(String(page.url || '').includes('panel=calendar') ? 'calendar' : 'tasks')
const clubCalendarView = ref('week')
const clubCalendarCursor = ref(new Date())

const formatMoney = (value) => currency.value.format(Number(value || 0))
const formatNumber = (value) => numberFormat.value.format(Number(value || 0))
const formatBytes = (bytes) => {
    const value = Number(bytes || 0)

    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}
const formatDateTime = (value) => {
    if (!value) return t('Noch kein Termin')

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}
const formatDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(value instanceof Date ? value : new Date(`${value}T00:00:00`))
}

const statCards = computed(() => {
    const stats = selectedClub.value?.stats || {}

    return [
        { key: 'members', label: 'Mitglieder', value: `${formatNumber(stats.members)} / ${stats.member_limit ? formatNumber(stats.member_limit) : t('Unbegrenzt')}`, icon: 'las la-users', tone: 'from-sky-500/20 to-sky-500/5' },
        { key: 'teams', label: 'Teams', value: `${formatNumber(stats.teams)} / ${stats.team_limit ? formatNumber(stats.team_limit) : t('Unbegrenzt')}`, icon: 'las la-sitemap', tone: 'from-emerald-500/20 to-emerald-500/5' },
        { key: 'open', label: 'Offene Beträge', value: formatMoney(stats.open_invoice_amount), sub: t('{count} offene Rechnungen', { count: formatNumber(stats.open_invoice_count) }), icon: 'las la-file-invoice-dollar', tone: 'from-amber-500/20 to-amber-500/5' },
        { key: 'requests', label: 'Anfragen', value: formatNumber(stats.pending_requests), sub: t('Mitgliedschaft und Teams'), icon: 'las la-user-check', tone: 'from-violet-500/20 to-violet-500/5' },
        { key: 'events', label: 'Nächste Termine', value: formatNumber(stats.upcoming_events), sub: t('Geplante Vereins- und Teamtermine'), icon: 'las la-calendar-check', tone: 'from-cyan-500/20 to-cyan-500/5' },
        { key: 'storage', label: 'Speicher', value: stats.storage_gb ? `${formatBytes(stats.storage_bytes)} / ${formatNumber(stats.storage_gb)} GB` : formatBytes(stats.storage_bytes), sub: stats.storage_gb ? t('Verwendet / Planlimit') : t('Plan ohne festes Limit'), icon: 'las la-database', tone: 'from-rose-500/20 to-rose-500/5' },
    ]
})

const actionText = {
    requests: {
        title: 'Anfragen prüfen',
        description: 'Offene Mitglieds- oder Teambeitritte sauber entscheiden.',
    },
    billing: {
        title: 'Rechnungen klären',
        description: 'Offene Beiträge prüfen, Zahlung erfassen oder erinnern.',
    },
    sepa: {
        title: 'SEPA vervollständigen',
        description: 'Mandate und IBANs für automatische Abbuchungen nachziehen.',
    },
    structure: {
        title: 'Vereinsstruktur pflegen',
        description: 'Teams, Rollen und Vereinsdaten aktuell halten.',
    },
    events: {
        title: 'Termine steuern',
        description: 'Trainings, Spiele und Meetings im Blick behalten.',
    },
}

const taskStatusLabels = {
    open: 'Offen',
    read: 'Gelesen',
    in_progress: 'In Arbeit',
    waiting: 'Wartet',
    done: 'Erledigt',
}

const taskPriorityLabels = {
    low: 'Niedrig',
    normal: 'Normal',
    high: 'Hoch',
    urgent: 'Dringend',
}

const taskResponsibleNames = (task) => {
    if (task.assignee?.name) return task.assignee.name

    return (task.participants || [])
        .map((member) => member.name)
        .filter(Boolean)
        .join(', ')
}

const openClubTasks = computed(() => clubTasks.value.filter((task) => task.status !== 'done'))
const doneClubTasks = computed(() => clubTasks.value.filter((task) => task.status === 'done'))
const clubCalendarItems = computed(() => [
    ...clubTasks.value
        .filter((task) => task.due_at)
        .map((task) => ({
            id: `task-${task.id}`,
            type: 'task',
            title: task.title,
            date: new Date(`${task.due_at}T00:00:00`),
            label: t('Aufgabe'),
            icon: 'las la-check-circle',
        })),
    ...(clubTaskMeta.value.calendar_events || [])
        .filter((event) => event.start_time)
        .map((event) => ({
            id: `event-${event.id}`,
            type: 'event',
            title: event.title,
            date: new Date(event.start_time),
            label: t('Termin'),
            icon: 'las la-calendar-check',
        })),
].filter((item) => !Number.isNaN(item.date.getTime())).sort((a, b) => a.date - b.date))

const calendarBuckets = computed(() => {
    if (clubCalendarView.value === 'year') {
        return Array.from({ length: 12 }, (_, index) => {
            const date = new Date(clubCalendarCursor.value.getFullYear(), index, 1)
            return {
                key: `${date.getFullYear()}-${index + 1}`,
                label: new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(date),
                items: clubCalendarItems.value.filter((item) => item.date.getFullYear() === date.getFullYear() && item.date.getMonth() === index),
            }
        })
    }

    const days = clubCalendarView.value === 'day'
        ? [startOfDay(clubCalendarCursor.value)]
        : clubCalendarView.value === 'month'
            ? monthDays(clubCalendarCursor.value)
            : weekDays(clubCalendarCursor.value)
    return days.map((date) => ({
        key: date.toISOString(),
        label: new Intl.DateTimeFormat(locale.value, { dateStyle: 'full' }).format(date),
        items: clubCalendarItems.value.filter((item) => sameDay(item.date, date)),
    }))
})

const calendarTitle = computed(() => {
    if (clubCalendarView.value === 'year') return `${clubCalendarCursor.value.getFullYear()}`
    if (clubCalendarView.value === 'month') return new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(clubCalendarCursor.value)
    if (clubCalendarView.value === 'day') return new Intl.DateTimeFormat(locale.value, { dateStyle: 'full' }).format(clubCalendarCursor.value)
    const days = weekDays(clubCalendarCursor.value)
    const format = new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' })
    return `${format.format(days[0])} - ${format.format(days[6])}`
})

watch(selectedClubId, () => {
    loadClubTasks()
}, { immediate: true })

async function loadClubTasks() {
    if (!selectedClubId.value) return
    clubTasksLoading.value = true
    clubTaskError.value = ''
    try {
        const response = await window.axios.get(`/api/v1/clubs/${selectedClubId.value}/tasks`)
        clubTasks.value = response.data.data || []
        clubTaskMeta.value = response.data.meta || { members: [], teams: [] }
    } catch (error) {
        clubTaskError.value = error?.response?.data?.message || t('Aufgaben konnten nicht geladen werden.')
    } finally {
        clubTasksLoading.value = false
    }
}

function startOfDay(value) {
    return new Date(value.getFullYear(), value.getMonth(), value.getDate())
}

function sameDay(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

function weekDays(value) {
    const start = startOfDay(value)
    const day = start.getDay() || 7
    start.setDate(start.getDate() - day + 1)
    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        return date
    })
}

function monthDays(value) {
    const count = new Date(value.getFullYear(), value.getMonth() + 1, 0).getDate()
    return Array.from({ length: count }, (_, index) => new Date(value.getFullYear(), value.getMonth(), index + 1))
}

function moveClubCalendar(delta) {
    const next = startOfDay(clubCalendarCursor.value)
    if (clubCalendarView.value === 'day') next.setDate(next.getDate() + delta)
    if (clubCalendarView.value === 'week') next.setDate(next.getDate() + (delta * 7))
    if (clubCalendarView.value === 'month' || clubCalendarView.value === 'year') {
        const day = next.getDate()
        next.setDate(1)
        next.setMonth(next.getMonth() + delta * (clubCalendarView.value === 'year' ? 12 : 1))
        const lastDay = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate()
        next.setDate(Math.min(day, lastDay))
    }
    clubCalendarCursor.value = next
}

function resetTaskDraft() {
    clubTaskDraft.value = { title: '', description: '', priority: 'normal', status: 'open', visibility: 'club', participant_ids: [], team_id: '', due_at: '', checklist_text: '', attachment_links_text: '' }
    clubTaskDraftFiles.value = []
}

function checklistFromText(value) {
    return String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const done = line.startsWith('[x]') || line.startsWith('[X]')

            return { title: line.replace(/^\[[ xX]\]\s*/, '').trim(), done }
        })
        .filter((item) => item.title)
}

function linksFromText(value) {
    return String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const separator = line.indexOf('|')

            return separator > 0
                ? { title: line.slice(0, separator).trim(), url: line.slice(separator + 1).trim() }
                : { url: line }
        })
        .filter((item) => item.url)
}

function updateTaskDraftFiles(event) {
    clubTaskDraftFiles.value = Array.from(event.target.files || [])
}

async function createClubTask() {
    if (!selectedClubId.value || !clubTaskDraft.value.title.trim()) return
    clubTasksSaving.value = true
    try {
        const participantIds = (clubTaskDraft.value.participant_ids || []).map((id) => Number(id)).filter(Boolean)
        const payload = {
            title: clubTaskDraft.value.title.trim(),
            description: clubTaskDraft.value.description.trim(),
            priority: clubTaskDraft.value.priority,
            status: clubTaskDraft.value.status,
            visibility: clubTaskDraft.value.visibility,
            assigned_to: participantIds.length === 1 ? participantIds[0] : null,
            team_id: clubTaskDraft.value.team_id || null,
            due_at: clubTaskDraft.value.due_at || null,
            participant_ids: participantIds,
            checklist: checklistFromText(clubTaskDraft.value.checklist_text),
            attachment_links: linksFromText(clubTaskDraft.value.attachment_links_text),
        }
        const response = await window.axios.post(`/api/v1/clubs/${selectedClubId.value}/tasks`, payload)
        if (clubTaskDraftFiles.value.length) {
            const form = new FormData()
            clubTaskDraftFiles.value.forEach((file) => form.append('attachments[]', file))
            await window.axios.post(`/api/v1/clubs/${selectedClubId.value}/tasks/${response.data.data.id}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
            await loadClubTasks()
        } else {
            clubTasks.value = [response.data.data, ...clubTasks.value]
        }
        resetTaskDraft()
    } catch (error) {
        clubTaskError.value = error?.response?.data?.message || t('Aufgabe konnte nicht gespeichert werden.')
    } finally {
        clubTasksSaving.value = false
    }
}

async function updateClubTask(task, payload) {
    if (!selectedClubId.value) return
    clubTasksSaving.value = true
    try {
        const response = await window.axios.put(`/api/v1/clubs/${selectedClubId.value}/tasks/${task.id}`, payload)
        clubTasks.value = clubTasks.value.map((item) => item.id === task.id ? response.data.data : item)
    } catch (error) {
        clubTaskError.value = error?.response?.data?.message || t('Aufgabe konnte nicht aktualisiert werden.')
    } finally {
        clubTasksSaving.value = false
    }
}

async function addClubTaskComment(task) {
    const body = String(clubTaskComments.value[task.id] || '').trim()
    if (!body || !selectedClubId.value) return
    clubTasksSaving.value = true
    try {
        await window.axios.post(`/api/v1/clubs/${selectedClubId.value}/tasks/${task.id}/comments`, { body })
        clubTaskComments.value = { ...clubTaskComments.value, [task.id]: '' }
        await loadClubTasks()
    } catch (error) {
        clubTaskError.value = error?.response?.data?.message || t('Kommentar konnte nicht gespeichert werden.')
    } finally {
        clubTasksSaving.value = false
    }
}

async function uploadClubTaskAttachments(task, event) {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (!files.length || !selectedClubId.value) return
    const form = new FormData()
    files.forEach((file) => form.append('attachments[]', file))
    clubTasksSaving.value = true
    try {
        await window.axios.post(`/api/v1/clubs/${selectedClubId.value}/tasks/${task.id}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
        await loadClubTasks()
    } catch (error) {
        clubTaskError.value = error?.response?.data?.message || t('Anhang konnte nicht hochgeladen werden.')
    } finally {
        clubTasksSaving.value = false
    }
}
</script>

<template>
    <Head :title="t('Vereins-Cockpit')" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_340px] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ t('Vereinsführung') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-primary md:text-3xl">{{ t('Vereins-Cockpit') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        {{ t('Alle wichtigen Vereinssignale an einem Ort: Anfragen, Beiträge, Termine, Speicher und nächste Aufgaben.') }}
                    </p>
                </div>

                <label v-if="clubs.length > 1" class="block">
                    <span class="mb-2 block text-xs font-bold uppercase text-secondary">{{ t('Verein') }}</span>
                    <select v-model="selectedClubId" class="w-full rounded-lg border-border bg-inputBg text-sm font-semibold text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                </label>
            </div>

            <div v-if="selectedClub" class="border-t border-border px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">{{ selectedClub.name }}</h2>
                        <p class="text-sm text-secondary">
                            {{ t('Aktueller Plan') }}: {{ selectedClub.plan?.name || t('Free') }}
                        </p>
                    </div>
                    <Link
                        :href="route('guest.pricing', { audience: 'verein' })"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-bold text-primary transition hover:border-borderHover"
                    >
                        <i class="las la-rocket"></i>
                        {{ selectedClub.locked ? t('Club-Plan aktivieren') : t('Plan prüfen') }}
                    </Link>
                </div>
            </div>
        </section>

        <ClubDeletionPanel v-if="selectedClub?.can_delete" :key="selectedClub.id" :club-id="selectedClub.id" :club-name="selectedClub.name" />

        <section v-if="!selectedClub" class="surface-card overflow-hidden">
            <div class="grid gap-5 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-buttonPrimary/10 text-buttonPrimary"><i class="las la-building text-2xl"></i></span>
                    <h2 class="mt-4 text-xl font-bold text-primary">{{ t('Richte deinen Vereinsbereich ein') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                        {{ can('club.create')
                            ? t('Registriere zuerst deinen Verein. Danach führst du Teams, Mitglieder, Termine und Beiträge Schritt für Schritt zusammen.')
                            : t('Dein Konto hat eine Vereinsrolle, ist aber noch keinem verwaltbaren Verein zugeordnet. Bitte den Vereinsinhaber um eine Einladung mit der passenden Rolle.') }}
                    </p>
                </div>
                <Link
                    v-if="can('club.create')"
                    :href="route('auth.teams.index', { create_club: 1 })"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary"
                >
                    <i class="las la-plus"></i>
                    {{ t('Verein registrieren') }}
                </Link>
                <Link
                    v-else
                    :href="route('auth.teams.index')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-5 py-3 text-sm font-bold text-primary"
                >
                    <i class="las la-envelope-open-text"></i>
                    {{ t('Einladungen prüfen') }}
                </Link>
            </div>

            <div class="grid gap-px border-t border-border bg-border sm:grid-cols-3">
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">1. {{ t('Verein') }}</p><p class="mt-1 text-sm text-primary">{{ t('Basisdaten und Sichtbarkeit festlegen') }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">2. {{ t('Team') }}</p><p class="mt-1 text-sm text-primary">{{ t('Erstes Team erstellen und Trainer zuordnen') }}</p></div>
                <div class="bg-card p-4"><p class="text-xs font-bold uppercase text-secondary">3. {{ t('Mitglieder') }}</p><p class="mt-1 text-sm text-primary">{{ t('Einladen oder bestehende Daten importieren') }}</p></div>
            </div>
        </section>

        <template v-else>
            <section v-if="selectedClub.locked" class="surface-card border-amber-400/60 bg-amber-500/10 p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-amber-200">{{ t('Premium-Funktion') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ t('Vereins-Cockpit ist im Club-Plan enthalten') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ t('Du siehst die Vorschau. Operative Steuerung, Warnungen und Priorisierung werden mit dem Club-Plan freigeschaltet.') }}
                        </p>
                    </div>
                    <Link :href="route('guest.pricing', { audience: 'verein' })" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary">
                        {{ t('Upgrade ansehen') }}
                    </Link>
                </div>
            </section>

            <section v-if="onboarding" class="surface-card overflow-hidden">
                <div class="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ onboarding.title }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ onboarding.progress_label }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-secondary">{{ onboarding.subtitle }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-2.5 w-40 overflow-hidden rounded-full bg-inputBg" role="progressbar" :aria-valuenow="onboarding.completion_percent" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-buttonPrimary transition-all" :style="{ width: `${onboarding.completion_percent}%` }"></div>
                        </div>
                        <strong class="text-lg text-primary">{{ onboarding.completion_percent }}%</strong>
                    </div>
                </div>

                <div class="grid gap-px border-t border-border bg-border md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="step in onboarding.steps" :key="step.key" class="flex flex-col bg-card p-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                :class="step.done ? 'bg-emerald-500/15 text-emerald-300' : 'bg-buttonPrimary/10 text-buttonPrimary'"
                                aria-hidden="true"
                            >
                                <i :class="step.done ? 'las la-check' : 'las la-arrow-right'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-primary">{{ step.title }}</h3>
                                    <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-[11px] font-bold text-secondary">{{ step.status_label }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-secondary">{{ step.description }}</p>
                            </div>
                        </div>
                        <Link
                            v-if="!step.done"
                            :href="step.action_path"
                            class="mt-3 inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary transition hover:border-borderHover"
                        >
                            {{ step.action_label }}
                            <i class="las la-arrow-right rtl:rotate-180"></i>
                        </Link>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.key"
                    class="surface-card bg-gradient-to-br p-4"
                    :class="card.tone"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-secondary">{{ t(card.label) }}</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ card.value }}</p>
                            <p v-if="card.sub" class="mt-1 text-xs text-secondary">{{ card.sub }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-inputBg text-buttonPrimary">
                            <i :class="[card.icon, 'text-xl']"></i>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                <div class="surface-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('Heute wichtig') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Nächste Aufgaben') }}</h2>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">
                            {{ selectedClub.stats.pending_requests + selectedClub.stats.overdue_invoice_count + selectedClub.stats.sepa_missing }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="action in selectedClub.actions"
                            :key="action.key"
                            :href="action.href"
                            class="flex items-center gap-3 rounded-lg border border-border bg-inputBg/50 p-3 transition hover:border-borderHover"
                        >
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-card text-buttonPrimary">
                                <i :class="[action.icon, 'text-xl']"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-primary">{{ t(actionText[action.key]?.title || action.key) }}</span>
                                <span class="block text-xs leading-5 text-secondary">{{ t(actionText[action.key]?.description || '') }}</span>
                            </span>
                            <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-primary">{{ formatNumber(action.count) }}</span>
                        </Link>
                    </div>
                </div>

                <div class="surface-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('Kalender') }}</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ t('Nächste Termine') }}</h2>
                        </div>
                        <Link :href="route('auth.events.index')" class="text-sm font-bold text-buttonPrimary">{{ t('Öffnen') }}</Link>
                    </div>

                    <div v-if="selectedClub.upcoming_events.length" class="mt-4 space-y-3">
                        <article v-for="event in selectedClub.upcoming_events" :key="event.id" class="rounded-lg border border-border bg-inputBg/50 p-3">
                            <p class="font-bold text-primary">{{ event.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ formatDateTime(event.start_time) }}</p>
                            <p v-if="event.location" class="mt-1 text-xs text-secondary">{{ event.location }}</p>
                        </article>
                    </div>

                    <div v-else class="mt-4 rounded-lg border border-dashed border-border p-6 text-center text-sm text-secondary">
                        {{ t('Noch keine kommenden Termine geplant.') }}
                    </div>
                </div>
            </section>

            <section class="surface-card p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-buttonPrimary">{{ t('Vereins-To-dos') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ activeWorkPanel === 'calendar' ? t('Kalender & Fristen') : t('Aufgaben gemeinsam steuern') }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-secondary">
                            {{ activeWorkPanel === 'calendar'
                                ? t('Sieh Termine und To-do-Fristen täglich, wöchentlich oder jährlich.')
                                : t('Teile Aufgaben im Verein, weise Verantwortliche zu, verfolge Status, Kommentare, Checklisten und Anhänge.') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="inline-flex rounded-lg border border-border bg-inputBg p-1">
                            <button type="button" :class="['rounded-md px-3 py-2 text-sm font-bold', activeWorkPanel === 'tasks' ? 'bg-card text-primary shadow-sm' : 'text-secondary']" @click="activeWorkPanel = 'tasks'">
                                <i class="las la-tasks mr-1"></i>{{ t('To-dos') }}
                            </button>
                            <button type="button" :class="['rounded-md px-3 py-2 text-sm font-bold', activeWorkPanel === 'calendar' ? 'bg-card text-primary shadow-sm' : 'text-secondary']" @click="activeWorkPanel = 'calendar'">
                                <i class="las la-calendar-alt mr-1"></i>{{ t('Kalender') }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border px-4 text-sm font-bold text-primary transition hover:border-borderHover"
                            :disabled="clubTasksLoading"
                            @click="loadClubTasks"
                        >
                            <i class="las la-sync"></i>
                            {{ t('Aktualisieren') }}
                        </button>
                    </div>
                </div>

                <div v-if="clubTaskError" class="mt-4 rounded-lg border border-red-400/40 bg-red-500/10 p-3 text-sm font-semibold text-red-200">
                    {{ clubTaskError }}
                </div>

                <div v-if="activeWorkPanel === 'calendar'" class="mt-5">
                    <div class="flex flex-col gap-3 rounded-lg border border-border bg-inputBg/40 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="inline-flex flex-wrap rounded-lg border border-border bg-card p-1">
                            <button v-for="view in ['day', 'week', 'month', 'year']" :key="view" type="button" :aria-pressed="clubCalendarView === view" :class="['rounded-md px-3 py-2 text-sm font-bold', clubCalendarView === view ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary']" @click="clubCalendarView = view">
                                {{ view === 'day' ? t('Tag') : view === 'week' ? t('Woche') : view === 'month' ? t('Monat') : t('Jahr') }}
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" :aria-label="t('Zurück')" :title="t('Zurück')" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border text-primary" @click="moveClubCalendar(-1)">
                                <i class="las la-angle-left"></i>
                            </button>
                            <strong class="min-w-0 text-center text-primary">{{ calendarTitle }}</strong>
                            <button type="button" :aria-label="t('Weiter')" :title="t('Weiter')" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border text-primary" @click="moveClubCalendar(1)">
                                <i class="las la-angle-right"></i>
                            </button>
                        </div>
                    </div>

                    <div :class="['mt-4 grid gap-3', ['month', 'year'].includes(clubCalendarView) ? 'md:grid-cols-2 xl:grid-cols-3' : '']">
                        <article v-for="bucket in calendarBuckets" :key="bucket.key" class="rounded-lg border border-border bg-inputBg/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-bold text-primary">{{ bucket.label }}</h3>
                                <span class="rounded-full bg-card px-2 py-1 text-xs font-bold text-secondary">{{ bucket.items.length }}</span>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="item in bucket.items.slice(0, 8)" :key="item.id" class="flex items-start gap-3 rounded-lg bg-card px-3 py-2">
                                    <i :class="[item.icon, item.type === 'task' ? 'text-emerald-300' : 'text-buttonPrimary', 'mt-0.5 text-lg']"></i>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-primary">{{ item.title }}</p>
                                        <p class="text-xs text-secondary">{{ item.label }} · {{ formatDate(item.date) }}</p>
                                    </div>
                                </div>
                                <p v-if="!bucket.items.length" class="rounded-lg border border-dashed border-border p-4 text-center text-sm text-secondary">{{ t('Keine Termine oder Fristen.') }}</p>
                                <p v-if="bucket.items.length > 8" class="text-xs font-bold text-secondary">{{ t('{count} weitere', { count: bucket.items.length - 8 }) }}</p>
                            </div>
                        </article>
                    </div>
                </div>

                <form v-if="activeWorkPanel === 'tasks'" class="mt-5 grid gap-3 rounded-lg border border-border bg-inputBg/40 p-4 lg:grid-cols-12" @submit.prevent="createClubTask">
                    <label class="lg:col-span-4">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Aufgabe') }}</span>
                        <input v-model="clubTaskDraft.title" required maxlength="255" class="w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="t('z.B. Sommerfest Helferplan finalisieren')" />
                    </label>
                    <label class="lg:col-span-4">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Beschreibung') }}</span>
                        <input v-model="clubTaskDraft.description" class="w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="t('Kurznotiz für alle Beteiligten')" />
                    </label>
                    <label class="lg:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Verantwortliche') }}</span>
                        <select v-model="clubTaskDraft.participant_ids" multiple class="min-h-24 w-full rounded-lg border-border bg-card text-sm text-primary">
                            <option v-for="member in clubTaskMeta.members" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                    </label>
                    <label class="lg:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Fällig') }}</span>
                        <input v-model="clubTaskDraft.due_at" type="date" class="w-full rounded-lg border-border bg-card text-sm text-primary" />
                    </label>
                    <label class="lg:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Priorität') }}</span>
                        <select v-model="clubTaskDraft.priority" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                            <option v-for="(label, key) in taskPriorityLabels" :key="key" :value="key">{{ t(label) }}</option>
                        </select>
                    </label>
                    <label class="lg:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Status') }}</span>
                        <select v-model="clubTaskDraft.status" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                            <option v-for="(label, key) in taskStatusLabels" :key="key" :value="key">{{ t(label) }}</option>
                        </select>
                    </label>
                    <label class="lg:col-span-2">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Team') }}</span>
                        <select v-model="clubTaskDraft.team_id" class="w-full rounded-lg border-border bg-card text-sm text-primary">
                            <option value="">{{ t('Kein Team') }}</option>
                            <option v-for="team in clubTaskMeta.teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </label>
                    <label class="lg:col-span-4">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Checkliste') }}</span>
                        <input v-model="clubTaskDraft.checklist_text" class="w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="t('[ ] Aufgabe pro Zeile')" />
                    </label>
                    <label class="lg:col-span-4">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Links') }}</span>
                        <textarea v-model="clubTaskDraft.attachment_links_text" rows="2" class="w-full rounded-lg border-border bg-card text-sm text-primary" :placeholder="t('Ein Link pro Zeile')" />
                    </label>
                    <label class="lg:col-span-4">
                        <span class="mb-1 block text-xs font-bold uppercase text-secondary">{{ t('Dateien') }}</span>
                        <input type="file" multiple class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" @change="updateTaskDraftFiles" />
                    </label>
                    <div class="flex items-end lg:col-span-2">
                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="clubTasksSaving || !clubTaskDraft.title.trim()">
                            <i class="las la-plus"></i>
                            {{ t('Anlegen') }}
                        </button>
                    </div>
                </form>

                <div v-if="clubTasksLoading" class="mt-5 h-1 overflow-hidden rounded-full bg-inputBg">
                    <div class="h-full w-1/2 animate-pulse rounded-full bg-buttonPrimary"></div>
                </div>

                <div v-if="activeWorkPanel === 'tasks'" class="mt-5 grid gap-4 xl:grid-cols-2">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-bold text-primary">{{ t('Offen') }}</h3>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-bold text-secondary">{{ openClubTasks.length }}</span>
                        </div>
                        <div class="space-y-3">
                            <article v-for="task in openClubTasks" :key="task.id" class="rounded-lg border border-border bg-inputBg/40 p-4">
                                <div class="flex items-start gap-3">
                                    <button type="button" class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg border border-border text-secondary hover:text-buttonPrimary" :disabled="clubTasksSaving" @click="updateClubTask(task, { completed: true })">
                                        <i class="las la-check"></i>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-bold text-primary">{{ task.title }}</h4>
                                            <span class="rounded-full bg-card px-2 py-1 text-[11px] font-bold text-secondary">{{ t(taskPriorityLabels[task.priority] || task.priority) }}</span>
                                            <span class="rounded-full bg-card px-2 py-1 text-[11px] font-bold text-secondary">{{ t(taskStatusLabels[task.status] || task.status) }}</span>
                                        </div>
                                        <p v-if="task.description" class="mt-1 text-sm leading-5 text-secondary">{{ task.description }}</p>
                                        <p class="mt-2 text-xs font-semibold text-secondary">
                                            <span v-if="taskResponsibleNames(task)">{{ t('Verantwortlich') }}: {{ taskResponsibleNames(task) }}</span>
                                            <span v-if="task.due_at" class="ml-2">{{ t('Fällig') }}: {{ formatDate(task.due_at) }}</span>
                                            <span v-if="task.team" class="ml-2">{{ task.team.name }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div v-if="task.checklist?.length" class="mt-3 space-y-1">
                                    <div v-for="item in task.checklist" :key="item.title" class="flex items-center gap-2 text-xs text-secondary">
                                        <i :class="item.done ? 'las la-check-square text-emerald-300' : 'lar la-square'"></i>
                                        <span>{{ item.title }}</span>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a v-for="file in task.attachments || []" :key="file.id" :href="file.preview_url || file.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-border px-2 py-1 text-xs font-bold text-primary">
                                        <i class="las la-paperclip"></i>
                                        {{ file.display_name || file.path }}
                                    </a>
                                    <a v-for="link in task.attachment_links || []" :key="link.url" :href="link.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-border px-2 py-1 text-xs font-bold text-primary">
                                        <i class="las la-link"></i>
                                        {{ link.title || link.url }}
                                    </a>
                                    <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border px-2 py-1 text-xs font-bold text-primary">
                                        <i class="las la-paperclip"></i>
                                        {{ t('Anhang') }}
                                        <input type="file" class="hidden" multiple @change="uploadClubTaskAttachments(task, $event)" />
                                    </label>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <div v-for="comment in task.comments || []" :key="comment.id" class="rounded-lg bg-card px-3 py-2 text-sm">
                                        <p class="font-bold text-primary">{{ comment.user?.name || t('Unbekannt') }}</p>
                                        <p class="mt-1 text-secondary">{{ comment.body }}</p>
                                    </div>
                                    <form class="flex gap-2" @submit.prevent="addClubTaskComment(task)">
                                        <input v-model="clubTaskComments[task.id]" class="min-w-0 flex-1 rounded-lg border-border bg-card text-sm text-primary" :placeholder="t('Kommentar schreiben')" />
                                        <button class="rounded-lg bg-buttonPrimary px-3 text-sm font-bold text-buttonTextPrimary" :disabled="clubTasksSaving">
                                            <i class="las la-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </article>
                            <p v-if="!openClubTasks.length && !clubTasksLoading" class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-secondary">{{ t('Keine offenen Vereinsaufgaben.') }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-bold text-primary">{{ t('Erledigt') }}</h3>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-bold text-secondary">{{ doneClubTasks.length }}</span>
                        </div>
                        <div class="space-y-3">
                            <article v-for="task in doneClubTasks" :key="task.id" class="rounded-lg border border-border bg-inputBg/30 p-4 opacity-80">
                                <div class="flex items-start gap-3">
                                    <button type="button" class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-400/50 text-emerald-300" :disabled="clubTasksSaving" @click="updateClubTask(task, { completed: false })">
                                        <i class="las la-undo"></i>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-primary line-through">{{ task.title }}</h4>
                                        <p class="mt-1 text-xs text-secondary">{{ taskResponsibleNames(task) || t('Nicht zugewiesen') }}</p>
                                    </div>
                                </div>
                            </article>
                            <p v-if="!doneClubTasks.length && !clubTasksLoading" class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-secondary">{{ t('Noch keine erledigten Aufgaben.') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
