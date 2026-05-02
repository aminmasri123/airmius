<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    membershipStatuses: { type: Array, default: () => ['active', 'non_member', 'pending', 'former'] },
    contributionIntervals: { type: Array, default: () => ['none', 'monthly', 'quarterly', 'yearly', 'once'] },
    teamRoles: { type: Array, default: () => ['Coach', 'Captain', 'Player'] },
})

const selectedClubId = ref(props.clubs[0]?.id || null)
const editingMemberId = ref(null)
const invoiceMemberId = ref(null)
const showAddMemberModal = ref(false)
const showImportModal = ref(false)
const memberForms = ref({})
const createEmailMemberRow = () => ({
    name: '',
    email: '',
    membership_status: 'active',
    member_number: '',
    athlete_license_number: '',
    contribution_amount: '',
    contribution_interval: 'none',
})
const emailMemberForm = ref({
    send_invitation: true,
    members: [createEmailMemberRow()],
})
const importForm = useForm({
    file: null,
    send_invitation: false,
})

const selectedClub = computed(() => props.clubs.find((club) => club.id === selectedClubId.value) || props.clubs[0] || null)
const pendingRequests = computed(() => selectedClub.value?.pending_requests || [])

const statusLabel = (status) => ({
    active: 'Vereinsmitglied',
    non_member: 'Kein Vereinsmitglied',
    pending: 'In Pruefung',
    former: 'Ehemalig',
}[status] || status)

const statusClass = (status) => ({
    active: 'bg-air-green/15 text-air-green',
    non_member: 'bg-muted text-secondary',
    pending: 'bg-air-blue/15 text-air-blue',
    former: 'bg-error/10 text-error',
}[status] || 'bg-muted text-secondary')

const intervalLabel = (interval) => ({
    none: 'Kein Beitrag',
    monthly: 'Monatlich',
    quarterly: 'Quartal',
    yearly: 'Jaehrlich',
    once: 'Einmalig',
}[interval] || interval)

const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formFor = (member) => {
    memberForms.value[member.id] ??= {
        role: member.pivot.role || 'member',
        membership_status: member.pivot.membership_status || 'non_member',
        member_number: member.pivot.member_number || '',
        athlete_license_number: member.athlete_license_number || '',
        contribution_amount: member.pivot.contribution_amount || '',
        contribution_interval: member.pivot.contribution_interval || 'none',
        joined_on: member.pivot.joined_on || '',
        membership_notes: member.pivot.membership_notes || '',
    }

    return memberForms.value[member.id]
}

const invoiceForm = useForm({
    title: 'Mitgliedsbeitrag',
    description: '',
    amount: '',
    due_date: '',
})

const approveRequest = (request) => {
    router.post(route('auth.team-join-requests.approve', request.id), {
        role: 'Player',
    }, { preserveScroll: true })
}

const declineRequest = (request) => {
    router.post(route('auth.team-join-requests.decline', request.id), {}, { preserveScroll: true })
}

const saveMember = (member) => {
    router.put(route('auth.club-memberships.members.update', [selectedClub.value.id, member.id]), formFor(member), {
        preserveScroll: true,
        onSuccess: () => {
            editingMemberId.value = null
        },
    })
}

const generateMemberNumber = (member) => {
    router.post(route('auth.club-memberships.members.member-number', [selectedClub.value.id, member.id]), {}, {
        preserveScroll: true,
    })
}

const openInvoice = (member) => {
    invoiceMemberId.value = member.id
    invoiceForm.title = 'Mitgliedsbeitrag'
    invoiceForm.description = ''
    invoiceForm.amount = formFor(member).contribution_amount || ''
    invoiceForm.due_date = ''
}

const createInvoice = (member) => {
    invoiceForm.post(route('auth.club-memberships.invoices.store', [selectedClub.value.id, member.id]), {
        preserveScroll: true,
        onSuccess: () => {
            invoiceMemberId.value = null
            invoiceForm.reset()
            invoiceForm.title = 'Mitgliedsbeitrag'
        },
    })
}

const markPaid = (invoice) => {
    router.post(route('auth.club-memberships.invoices.payments.store', invoice.id), {
        amount: invoice.amount,
        method: 'manual',
    }, { preserveScroll: true })
}

const updateInvoiceStatus = (invoice, status) => {
    router.put(route('auth.club-memberships.invoices.update', invoice.id), { status }, { preserveScroll: true })
}

const sendReminder = (invoice) => {
    router.post(route('auth.club-memberships.invoices.reminder', invoice.id), {}, { preserveScroll: true })
}

const addEmailMember = () => {
    router.post(route('auth.club-memberships.email-members.store', selectedClub.value.id), emailMemberForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            emailMemberForm.value = {
                send_invitation: true,
                members: [createEmailMemberRow()],
            }
            showAddMemberModal.value = false
        },
    })
}

const addEmailMemberRow = () => {
    emailMemberForm.value.members.push(createEmailMemberRow())
}

const removeEmailMemberRow = (index) => {
    if (emailMemberForm.value.members.length === 1) {
        emailMemberForm.value.members = [createEmailMemberRow()]
        return
    }

    emailMemberForm.value.members.splice(index, 1)
}

const importEmailMembers = () => {
    importForm.post(route('auth.club-memberships.email-members.import', selectedClub.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset()
            showImportModal.value = false
        },
    })
}

const inviteExternalMember = (member) => {
    router.post(route('auth.club-memberships.email-members.invite', member.id), {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Mitgliederverwaltung" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Mitgliederverwaltung</h1>
                    <p class="mt-1 text-sm text-secondary">
                        Team-Anfragen, Vereinsmitgliedschaft, Beitraege, Rechnungen und Zahlungen verwalten.
                    </p>
                </div>

                <select v-if="clubs.length" v-model="selectedClubId" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option v-for="club in clubs" :key="club.id" :value="club.id">
                        {{ club.name }}
                    </option>
                </select>
            </div>
        </section>

        <section v-if="!clubs.length" class="surface-card p-8 text-center text-secondary">
            Du verwaltest aktuell keinen Verein.
        </section>

        <template v-else-if="selectedClub">
            <section class="grid gap-4 md:grid-cols-3">
                <div class="surface-card p-4">
                    <div class="text-2xl font-bold text-primary">{{ selectedClub.members.length }}</div>
                    <div class="text-sm text-secondary">Personen im Verein</div>
                </div>
                <div class="surface-card p-4">
                    <div class="text-2xl font-bold text-primary">{{ pendingRequests.length }}</div>
                    <div class="text-sm text-secondary">Offene Team-Anfragen</div>
                </div>
                <div class="surface-card p-4">
                    <div class="text-2xl font-bold text-primary">{{ selectedClub.invoices.length }}</div>
                    <div class="text-sm text-secondary">Letzte Rechnungen</div>
                </div>
            </section>

            <section class="surface-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Mitglieder hinzufuegen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Einzelne Personen per E-Mail erfassen oder bestehende Vereinslisten per Excel/CSV importieren.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="route('auth.club-memberships.import-template')"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        >
                            Excel-Vorlage
                        </a>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                            @click="showImportModal = true"
                        >
                            Importieren
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="showAddMemberModal = true"
                        >
                            Mitglied hinzufuegen
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="selectedClub.external_members?.length" class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Externe Mitglieder ohne Verknüpfung</h2>
                <p class="mt-1 text-sm text-secondary">
                    Diese Personen sind im Verein hinterlegt, aber noch nicht mit einem Airmius-Konto verbunden.
                </p>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article v-for="member in selectedClub.external_members" :key="member.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-primary">{{ member.name || member.email }}</h3>
                                <p class="text-sm text-secondary">{{ member.email }}</p>
                                <p class="mt-2 text-xs text-secondary">
                                    Mitgliedsnummer: {{ member.member_number || '-' }} · Lizenznummer: {{ member.athlete_license_number || '-' }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Einladung: {{ member.invitation_status === 'pending' ? 'gesendet' : member.invitation_status === 'linked' ? 'verknüpft' : 'nicht gesendet' }}
                                </p>
                            </div>

                            <button
                                v-if="member.invitation_status !== 'linked'"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                                @click="inviteExternalMember(member)"
                            >
                                Einladung/Verknüpfung
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="surface-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Offene Beitrittsanfragen</h2>
                        <p class="mt-1 text-sm text-secondary">
                            Personen treten erst nach Annahme dem Team und Verein bei.
                        </p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <article v-for="request in pendingRequests" :key="request.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-semibold text-primary">{{ request.user.name }}</p>
                                <p class="text-sm text-secondary">
                                    {{ request.user.email }} moechte zu {{ request.team.name }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="approveRequest(request)">
                                    Annehmen
                                </button>
                                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="declineRequest(request)">
                                    Ablehnen
                                </button>
                            </div>
                        </div>
                    </article>

                    <p v-if="!pendingRequests.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                        Keine offenen Anfragen.
                    </p>
                </div>
            </section>

            <section class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <h2 class="text-lg font-semibold text-primary">Mitglieder & Beitragsdaten</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Teammitglieder koennen als echte Vereinsmitglieder oder als reine Teamteilnehmer markiert werden.
                    </p>
                </div>

                <div class="divide-y divide-border">
                    <article v-for="member in selectedClub.members" :key="member.id" class="p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link :href="route('auth.users.show', member.id)" class="font-semibold text-primary hover:underline">
                                        {{ member.name }}
                                    </Link>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(formFor(member).membership_status)">
                                        {{ statusLabel(formFor(member).membership_status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-secondary">{{ member.email }}</p>
                                <p class="mt-2 text-xs text-secondary">
                                    Nr. {{ formFor(member).member_number || '-' }} · Beitrag {{ formatMoney(formFor(member).contribution_amount) }} · {{ intervalLabel(formFor(member).contribution_interval) }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Lizenznummer: {{ formFor(member).athlete_license_number || '-' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="editingMemberId = editingMemberId === member.id ? null : member.id">
                                    Bearbeiten
                                </button>
                                <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" @click="openInvoice(member)">
                                    Rechnung
                                </button>
                            </div>
                        </div>

                        <form v-if="editingMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3" @submit.prevent="saveMember(member)">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Rolle</label>
                                <select v-model="formFor(member).role" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option value="owner">Owner</option>
                                    <option value="admin">Verein-Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="member">Mitglied</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                                <select v-model="formFor(member).membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                                <div class="mt-1 flex gap-2">
                                    <input v-model="formFor(member).member_number" class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-inputBg" @click="generateMemberNumber(member)">
                                        Generieren
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                                <input
                                    v-model="formFor(member).athlete_license_number"
                                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                    placeholder="z. B. Spielerpass- oder Verbandsnummer"
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                                <input v-model="formFor(member).contribution_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                                <select v-model="formFor(member).contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                    <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">Eintritt</label>
                                <input v-model="formFor(member).joined_on" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </div>

                            <div class="md:col-span-3">
                                <label class="text-xs font-semibold uppercase text-secondary">Notiz</label>
                                <textarea v-model="formFor(member).membership_notes" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea>
                            </div>

                            <div class="md:col-span-3">
                                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                    Speichern
                                </button>
                            </div>
                        </form>

                        <form v-if="invoiceMemberId === member.id" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-[1fr_140px_170px_auto]" @submit.prevent="createInvoice(member)">
                            <input v-model="invoiceForm.title" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Titel">
                            <input v-model="invoiceForm.amount" type="number" min="0.01" step="0.01" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Betrag">
                            <input v-model="invoiceForm.due_date" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                                Erstellen
                            </button>
                            <textarea v-model="invoiceForm.description" rows="2" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary md:col-span-4" placeholder="Beschreibung optional"></textarea>
                        </form>
                    </article>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Rechnungen</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">Nr.</th>
                                <th class="py-2 pr-4">User</th>
                                <th class="py-2 pr-4">Titel</th>
                                <th class="py-2 pr-4">Betrag</th>
                                <th class="py-2 pr-4">Faellig</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in selectedClub.invoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.user?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4">
                                    <select :value="invoice.status" class="rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary" @change="updateInvoiceStatus(invoice, $event.target.value)">
                                        <option value="open">Offen</option>
                                        <option value="paid">Bezahlt</option>
                                        <option value="overdue">Ueberfaellig</option>
                                        <option value="cancelled">Storniert</option>
                                    </select>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2">
                                        <button v-if="invoice.status !== 'paid'" type="button" class="rounded bg-buttonPrimary px-2 py-1 text-xs text-buttonTextPrimary" @click="markPaid(invoice)">
                                            Bezahlt
                                        </button>
                                        <button v-if="invoice.status !== 'paid'" type="button" class="rounded border border-border px-2 py-1 text-xs text-primary" @click="sendReminder(invoice)">
                                            Mahnung
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!selectedClub.invoices.length" class="py-6 text-sm text-secondary">Noch keine Rechnungen.</p>
                </div>
            </section>
        </template>

        <Modal :show="showAddMemberModal" max-width="2xl" @close="showAddMemberModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder per E-Mail hinzufuegen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Erfasse mehrere Mitglieder auf einmal. Wenn eine Einladung aktiv ist, werden vorhandene Konten verknuepft, sonst geht eine Einladung per E-Mail raus.
                </p>

                <form class="mt-5 space-y-4" @submit.prevent="addEmailMember">
                    <label class="flex items-center gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="emailMemberForm.send_invitation" type="checkbox" class="rounded border-border bg-inputBg">
                        Einladung zu Airmius verschicken
                    </label>

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(member, index) in emailMemberForm.members"
                            :key="index"
                            class="rounded-lg border border-border bg-bg p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-primary">Mitglied {{ index + 1 }}</h3>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-inputBg"
                                    @click="removeEmailMemberRow(index)"
                                >
                                    Entfernen
                                </button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Name</label>
                                    <input
                                        v-model="member.name"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">E-Mail</label>
                                    <input
                                        v-model="member.email"
                                        type="email"
                                        required
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="mitglied@example.org"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedschaft</label>
                                    <select v-model="member.membership_status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="status in membershipStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Mitgliedsnummer</label>
                                    <input
                                        v-model="member.member_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Lizenznummer</label>
                                    <input
                                        v-model="member.athlete_license_number"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="Optional"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Beitrag</label>
                                    <input
                                        v-model="member.contribution_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                        placeholder="0,00"
                                    >
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase text-secondary">Intervall</label>
                                    <select v-model="member.contribution_interval" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                                    </select>
                                </div>
                            </div>
                        </article>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        @click="addEmailMemberRow"
                    >
                        Weiteres Mitglied
                    </button>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showAddMemberModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                            Mitglieder speichern
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showImportModal" max-width="2xl" @close="showImportModal = false">
            <div class="p-2">
                <h2 class="text-xl font-bold text-primary">Mitglieder importieren</h2>
                <p class="mt-1 text-sm text-secondary">
                    Importiere Excel- oder CSV-Listen mit Name, E-Mail, Mitgliedsnummer, Lizenznummer, Beitrag und Eintrittsdatum.
                </p>

                <div class="mt-4 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                    <p class="font-semibold text-primary">Empfohlen</p>
                    <p class="mt-1">
                        Lade zuerst die Airmius Excel-Vorlage herunter. Die erste Beispielzeile kannst du ersetzen oder entfernen.
                    </p>
                    <a
                        :href="route('auth.club-memberships.import-template')"
                        class="mt-3 inline-flex rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                    >
                        Vorlage herunterladen
                    </a>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="importEmailMembers">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Datei</label>
                        <input
                            type="file"
                            accept=".xlsx,.csv,.txt"
                            required
                            class="mt-1 block w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            @input="importForm.file = $event.target.files[0]"
                        >
                        <p v-if="importForm.errors.file" class="mt-2 text-sm text-error">{{ importForm.errors.file }}</p>
                    </div>

                    <label class="flex items-start gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-primary">
                        <input v-model="importForm.send_invitation" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            Einladung/Verknuepfung direkt aktivieren
                            <span class="block text-xs text-secondary">
                                Bestehende Airmius-Konten werden verbunden, sonst wird eine Einladung an die E-Mail-Adresse gesendet.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="showImportModal = false">
                            Abbrechen
                        </button>
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="importForm.processing">
                            Import starten
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
