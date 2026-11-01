<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { confirmDialog } from '@/services/dialogService'
import ClubAccessManager from '@/Components/ClubMemberships/ClubAccessManager.vue'

defineOptions({ layout: AppLayout })
const props = defineProps(['club', 'owner', 'fields', 'canManageSubscription', 'canManageRoles', 'subscription', 'planName', 'plans', 'members', 'teams', 'invoices', 'history'])
const tabs = ['Übersicht', 'Vereinsdaten', 'Mitglieder & Rollen', 'Teams', 'Abo & Abrechnung', 'Prüfung', 'Änderungsverlauf']
const tab = ref(tabs[0])
const selectedMember = ref(null)
const form = useForm(Object.fromEntries(Object.keys(props.fields).map(key => [key, props.club[key] ?? ''])))
const subscription = useForm({ subscription_plan_id: props.subscription?.subscription_plan_id ?? '', status: props.subscription?.status ?? 'active', current_period_ends_at: props.subscription?.current_period_ends_at?.slice(0, 10) ?? null, trial_ends_at: props.subscription?.trial_ends_at?.slice(0, 10) ?? null })
const status = useForm({ verification_status: props.club.verification_status })
const statusLabels = { active: 'Aktiv', trialing: 'Testphase', past_due: 'Zahlung überfällig', cancelled: 'Gekündigt', cancels_at_period_end: 'Kündigung zum Laufzeitende', verified: 'Verifiziert', rejected: 'Abgelehnt', pending_verification: 'Prüfung ausstehend' }
async function saveSubscription() {
    if (await confirmDialog({ title: 'Abo ändern', message: `Abo von ${props.club.name} ändern? Der Vereinsverantwortliche wird benachrichtigt.`, confirmLabel: 'Änderung speichern' })) subscription.put(route('admin.clubs.subscription.update', props.club.id), { preserveScroll: true })
}
async function verify() {
    if (await confirmDialog({ title: 'Vereinsprüfung', message: `Prüfstatus auf „${statusLabels[status.verification_status]}“ ändern?`, confirmLabel: 'Bestätigen' })) status.patch(route('admin.clubs.verification-status.update', props.club.id), { preserveScroll: true })
}
function page(key, value) { router.get(route('admin.clubs.show', props.club.id), { [key]: value }, { preserveState: true, preserveScroll: true }) }
</script>

<template>
    <Head :title="club.name" />
    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
        <Link :href="route('admin.clubs.index')" class="text-air-blue">← Alle Vereine</Link>
        <header class="rounded-2xl border border-default bg-surface p-6">
            <p class="text-sm text-secondary">Plattformverwaltung · Verein</p>
            <h1 class="mt-2 text-3xl font-bold text-primary">{{ club.name }}</h1>
            <p class="mt-2 text-secondary">{{ [club.city, club.country, planName].filter(Boolean).join(' · ') }}</p>
        </header>
        <nav aria-label="Vereinsbereiche" class="flex gap-2 overflow-x-auto pb-2">
            <button v-for="item in tabs" :key="item" type="button" :aria-pressed="tab === item" class="shrink-0 rounded-xl border px-4 py-3" :class="tab === item ? 'bg-air-blue text-white' : 'text-primary'" @click="tab = item">{{ item }}</button>
        </nav>
        <section class="rounded-2xl border border-default bg-surface p-6 text-primary">
            <h2 class="mb-5 text-xl font-bold">{{ tab }}</h2>
            <template v-if="tab === 'Übersicht'">
                <dl class="grid gap-6 sm:grid-cols-2"><div><dt>Verantwortlich</dt><dd class="font-semibold">{{ owner?.name ?? 'Nicht hinterlegt' }}</dd><dd>{{ owner?.email }}</dd></div><div><dt>Prüfstatus</dt><dd>{{ statusLabels[club.verification_status] }}</dd></div><div><dt>Mitglieder</dt><dd>{{ club.users_count }} App-Konten · {{ club.external_members_count }} externe Mitglieder</dd></div><div><dt>Teams</dt><dd>{{ club.teams_count }}</dd></div></dl>
                <Link class="mt-6 inline-block text-air-blue" :href="route('auth.clubs.show', club.id)">Vereinsprofil und weitere Vereinsfunktionen öffnen →</Link>
            </template>
            <form v-else-if="tab === 'Vereinsdaten'" @submit.prevent="form.patch(route('admin.clubs.update', club.id), { preserveScroll: true })">
                <div class="sticky top-0 z-10 mb-5 bg-surface py-3"><button :disabled="form.processing" class="rounded-xl bg-air-blue px-5 py-3 text-white">{{ form.processing ? 'Speichert …' : 'Vereinsdaten speichern' }}</button><span v-if="form.recentlySuccessful" role="status" class="ml-3">Gespeichert</span></div>
                <div class="grid gap-4 sm:grid-cols-2"><label v-for="(label, key) in fields" :key="key"><span class="mb-1 block">{{ label }}</span><input v-model="form[key]" :required="['name', 'country'].includes(key)" :type="key === 'contact_email' ? 'email' : key === 'website_url' ? 'url' : 'text'" class="w-full rounded-lg border bg-transparent p-3" /><span v-if="form.errors[key]" class="text-red-500">{{ form.errors[key] }}</span></label></div>
            </form>
            <template v-else-if="tab === 'Mitglieder & Rollen'">
                <p class="mb-4 text-secondary">Vereinszugehörige Konten und ihre Vereinsrolle. Weitere Mitgliederdaten und Rollen verwaltest du im Vereinsprofil.</p>
                <Link :href="route('auth.clubs.show', club.id)" class="text-air-blue">Mitgliederverwaltung öffnen →</Link>
                <div v-for="member in members.data" :key="member.id" class="border-b py-4"><p class="font-semibold">{{ member.name }}</p><p>{{ member.email }} · {{ member.role }}</p><button v-if="canManageRoles" class="mt-2 text-air-blue" @click="selectedMember = member">Rollen und Zugriff verwalten</button></div>
                <p v-if="!members.data.length">Keine Mitglieder vorhanden.</p>
                <div class="mt-4 flex gap-4"><button :disabled="members.current_page <= 1" @click="page('page', members.current_page - 1)">Zurück</button><span>{{ members.current_page }} / {{ members.last_page }}</span><button :disabled="members.current_page >= members.last_page" @click="page('page', members.current_page + 1)">Weiter</button></div>
            </template>
            <template v-else-if="tab === 'Teams'">
                <Link :href="route('auth.clubs.show', club.id)" class="text-air-blue">Teams im Vereinsprofil verwalten →</Link>
                <div v-for="team in teams.data" :key="team.id" class="border-b py-4"><p class="font-semibold">{{ team.name }}</p><p>{{ team.sport_type }}</p></div><p v-if="!teams.data.length">Keine Teams vorhanden.</p>
                <div class="mt-4 flex gap-4"><button :disabled="teams.current_page <= 1" @click="page('teams_page', teams.current_page - 1)">Zurück</button><span>{{ teams.current_page }} / {{ teams.last_page }}</span><button :disabled="teams.current_page >= teams.last_page" @click="page('teams_page', teams.current_page + 1)">Weiter</button></div>
            </template>
            <template v-else-if="tab === 'Abo & Abrechnung'">
                <p v-if="!canManageSubscription">Für die Abonnementverwaltung fehlt dir die Berechtigung.</p>
                <template v-else>
                    <form class="space-y-4" @submit.prevent="saveSubscription">
                        <label class="block">Tarif<select v-model="subscription.subscription_plan_id" required class="ml-3 rounded border bg-surface p-2"><option disabled value="">Tarif auswählen</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select></label>
                        <label class="block">Status<select v-model="subscription.status" class="ml-3 rounded border bg-surface p-2"><option v-for="value in ['active', 'trialing', 'past_due', 'cancelled', 'cancels_at_period_end']" :key="value" :value="value">{{ statusLabels[value] }}</option></select></label>
                        <label class="block">Laufzeitende <input v-model="subscription.current_period_ends_at" type="date" class="rounded border bg-transparent p-2" /></label>
                        <label class="block">Testphase endet <input v-model="subscription.trial_ends_at" type="date" class="rounded border bg-transparent p-2" /></label>
                        <p v-for="(error, key) in subscription.errors" :key="key" class="text-red-500">{{ error }}</p>
                        <button :disabled="subscription.processing" class="rounded-xl bg-air-blue px-5 py-3 text-white">Abo ändern</button><span v-if="subscription.recentlySuccessful" role="status"> Gespeichert</span>
                    </form>
                    <h3 class="mt-8 font-bold">Rechnungen</h3><p v-if="!invoices?.data.length">Keine Rechnungen vorhanden.</p>
                    <div v-for="invoice in invoices?.data" :key="invoice.id" class="border-b py-3">{{ invoice.number }} · {{ (invoice.amount_cents / 100).toFixed(2) }} {{ invoice.currency }} · {{ invoice.status }}</div>
                    <div v-if="invoices" class="mt-4 flex gap-4"><button :disabled="invoices.current_page <= 1" @click="page('invoices_page', invoices.current_page - 1)">Zurück</button><span>{{ invoices.current_page }} / {{ invoices.last_page }}</span><button :disabled="invoices.current_page >= invoices.last_page" @click="page('invoices_page', invoices.current_page + 1)">Weiter</button></div>
                </template>
            </template>
            <form v-else-if="tab === 'Prüfung'" class="space-y-4" @submit.prevent="verify">
                <p>Vereinsnummer: {{ club.official_club_number || 'Nicht hinterlegt' }}</p><p>{{ club.verification_notes }}</p>
                <select v-model="status.verification_status" class="rounded border bg-surface p-3"><option v-for="value in ['pending_verification', 'verified', 'rejected']" :key="value" :value="value">{{ statusLabels[value] }}</option></select>
                <p v-for="(error, key) in status.errors" :key="key" class="text-red-500">{{ error }}</p><button :disabled="status.processing" class="ml-3 rounded-xl bg-air-blue px-5 py-3 text-white">Prüfstatus ändern</button>
            </form>
            <template v-else><p class="mb-4 text-secondary">Die letzten 50 Vereinseinträge.</p><p v-if="!history.length">Noch keine Einträge.</p><article v-for="event in history" :key="event.id" class="border-b py-4"><p>{{ event.label }}</p><p class="text-sm text-secondary">{{ event.actor?.name ?? 'System' }} · {{ event.created_at }}</p></article></template>
        </section>
        <ClubAccessManager :show="!!selectedMember" :club="club" :member="selectedMember" :members="members.data" @close="selectedMember = null" />
    </div>
</template>
