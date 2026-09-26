<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import ClubMetadataSubjectEditor from '@/Components/Clubs/ClubMetadataSubjectEditor.vue'
import clubInventoryLocalization from '@/i18n/clubInventoryLocalization.json'
import { Head } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({ clubs: { type: Array, default: () => [] } })
const { t, locale, mergeLocaleMessage } = useI18n({ useScope: 'global' })
Object.entries(clubInventoryLocalization).forEach(([language, messages]) => {
    mergeLocaleMessage(language, { club_inventory: messages })
})
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

const selectedClubId = ref(props.clubs[0]?.id || null)
const selectedClub = computed(() => props.clubs.find((club) => club.id === selectedClubId.value) || null)
const activeTab = ref('items')
const inventory = ref({ items: [], loans: [], maintenance: [], can_manage: false, can_create: false })
const loading = ref(false)
const saving = ref('')
const errorMessage = ref('')
const successMessage = ref('')
const editingItemId = ref(null)
const qrItem = ref(null)

const emptyItemForm = () => ({ club_department_id: '', team_id: '', name: '', sku: '', category: '', location: '', description: '', quantity_total: 1, condition: 'good', status: 'active', requires_approval: false })
const itemForm = ref(emptyItemForm())
const checkoutForm = ref({ item_id: '', borrower_id: '', quantity: 1, due_at: '', notes: '' })
const maintenanceForm = ref({ item_id: '', title: '', description: '', cost: '' })

const activeLoans = computed(() => inventory.value.loans.filter((loan) => ['pending', 'active'].includes(loan.status)))
const openMaintenance = computed(() => inventory.value.maintenance.filter((record) => record.status !== 'completed'))
const editableItems = computed(() => inventory.value.items.filter((item) => item.can_edit))
const checkoutItems = computed(() => inventory.value.items.filter((item) => item.can_checkout))
const availableTeams = computed(() => (selectedClub.value?.teams || []).filter((team) => !itemForm.value.club_department_id || Number(team.club_department_id) === Number(itemForm.value.club_department_id)))
const tabs = computed(() => [
    { key: 'items', label: tx('club_inventory.tabs.items', 'Bestand'), count: inventory.value.items.length },
    { key: 'loans', label: tx('club_inventory.tabs.loans', 'Ausleihen'), count: activeLoans.value.length },
    { key: 'maintenance', label: tx('club_inventory.tabs.maintenance', 'Wartung'), count: openMaintenance.value.length },
])

const showError = (error, fallback) => {
    const values = error.response?.data?.errors ? Object.values(error.response.data.errors).flat() : []
    errorMessage.value = values[0] || error.response?.data?.message || fallback
}
const notify = (message) => {
    successMessage.value = message
    window.setTimeout(() => { if (successMessage.value === message) successMessage.value = '' }, 4000)
}

const loadInventory = async () => {
    if (!selectedClub.value || loading.value) return
    loading.value = true
    errorMessage.value = ''
    try {
        const response = await window.axios.get(route('api.v1.clubs.inventory.index', selectedClub.value.id), { headers: { Accept: 'application/json' } })
        inventory.value = response.data.data
    } catch (error) {
        showError(error, tx('club_inventory.errors.load', 'Das Inventar konnte nicht geladen werden.'))
    } finally {
        loading.value = false
    }
}

const resetItemForm = () => { editingItemId.value = null; itemForm.value = emptyItemForm() }
const editItem = (item) => {
    editingItemId.value = item.id
    itemForm.value = { ...emptyItemForm(), ...item }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}
const saveItem = async () => {
    if (!selectedClub.value || saving.value) return
    saving.value = 'item'; errorMessage.value = ''
    try {
        const response = editingItemId.value
            ? await window.axios.put(route('api.v1.clubs.inventory.update', [selectedClub.value.id, editingItemId.value]), itemForm.value, { headers: { Accept: 'application/json' } })
            : await window.axios.post(route('api.v1.clubs.inventory.store', selectedClub.value.id), itemForm.value, { headers: { Accept: 'application/json' } })
        notify(response.data.message); resetItemForm(); await loadInventory()
    } catch (error) { showError(error, tx('club_inventory.errors.save', 'Der Gegenstand konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}

const deleteItem = async (item) => {
    if (!selectedClub.value || saving.value || !window.confirm(tx('club_inventory.confirm_delete', 'Inventargegenstand wirklich löschen?'))) return
    saving.value = `delete-${item.id}`; errorMessage.value = ''
    try {
        const response = await window.axios.delete(route('api.v1.clubs.inventory.destroy', [selectedClub.value.id, item.id]), { headers: { Accept: 'application/json' } })
        notify(response.data.message); if (editingItemId.value === item.id) resetItemForm(); await loadInventory()
    } catch (error) { showError(error, tx('club_inventory.errors.delete', 'Der Gegenstand konnte nicht gelöscht werden.')) }
    finally { saving.value = '' }
}

const checkoutItem = async () => {
    if (!selectedClub.value || saving.value) return
    saving.value = 'checkout'; errorMessage.value = ''
    try {
        const response = await window.axios.post(route('api.v1.clubs.inventory.checkout', [selectedClub.value.id, checkoutForm.value.item_id]), { ...checkoutForm.value, borrower_id: checkoutForm.value.borrower_id || null, due_at: checkoutForm.value.due_at || null }, { headers: { Accept: 'application/json' } })
        notify(response.data.message)
        checkoutForm.value = { item_id: '', borrower_id: '', quantity: 1, due_at: '', notes: '' }
        await loadInventory(); activeTab.value = 'loans'
    } catch (error) { showError(error, tx('club_inventory.errors.checkout', 'Die Ausleihe konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}

const loanAction = async (loan, action) => {
    if (!selectedClub.value || saving.value) return
    saving.value = `${action}-${loan.id}`; errorMessage.value = ''
    try {
        const response = await window.axios.post(route(`api.v1.clubs.inventory.loans.${action}`, [selectedClub.value.id, loan.id]), action === 'return' ? { return_condition: 'good' } : {}, { headers: { Accept: 'application/json' } })
        notify(response.data.message); await loadInventory()
    } catch (error) { showError(error, tx('club_inventory.errors.loan_action', 'Die Ausleihe konnte nicht aktualisiert werden.')) }
    finally { saving.value = '' }
}

const createMaintenance = async () => {
    if (!selectedClub.value || saving.value) return
    saving.value = 'maintenance'; errorMessage.value = ''
    try {
        const response = await window.axios.post(route('api.v1.clubs.inventory.maintenance.store', [selectedClub.value.id, maintenanceForm.value.item_id]), { title: maintenanceForm.value.title, description: maintenanceForm.value.description || null, cost: maintenanceForm.value.cost || null }, { headers: { Accept: 'application/json' } })
        notify(response.data.message); maintenanceForm.value = { item_id: '', title: '', description: '', cost: '' }; await loadInventory()
    } catch (error) { showError(error, tx('club_inventory.errors.maintenance', 'Der Wartungsfall konnte nicht gespeichert werden.')) }
    finally { saving.value = '' }
}

const completeMaintenance = async (record) => {
    if (!selectedClub.value || saving.value) return
    saving.value = `maintenance-${record.id}`
    try {
        const response = await window.axios.put(route('api.v1.clubs.inventory.maintenance.update', [selectedClub.value.id, record.id]), { status: 'completed', description: record.description, cost: record.cost }, { headers: { Accept: 'application/json' } })
        notify(response.data.message); await loadInventory()
    } catch (error) { showError(error, tx('club_inventory.errors.maintenance', 'Der Wartungsfall konnte nicht aktualisiert werden.')) }
    finally { saving.value = '' }
}

const conditionLabel = (value) => tx(`club_inventory.condition.${value}`, value)
const loanStatusLabel = (value) => tx(`club_inventory.loan_status.${value}`, value)
const maintenanceStatusLabel = (value) => tx(`club_inventory.maintenance_status.${value}`, value)
const formatDate = (value) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value)) : '—'

watch(selectedClubId, () => {
    inventory.value = { items: [], loans: [], maintenance: [], can_manage: false, can_create: false }
    resetItemForm(); loadInventory()
}, { immediate: true })
</script>

<template>
    <Head :title="tx('club_inventory.title', 'Inventar & Ausleihe')" />
    <div class="space-y-6">
        <ClubWorkspaceNav active="inventory" :description="tx('club_inventory.nav_description', 'Geräte, Ausleihen, QR-Codes, Rückgaben und Wartung an einem Ort.')" />

        <section class="surface-card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('club_inventory.area', 'Vereinsbetrieb') }}</p><h1 class="mt-1 text-2xl font-bold text-primary">{{ tx('club_inventory.title', 'Inventar & Ausleihe') }}</h1><p class="mt-1 text-sm text-secondary">{{ tx('club_inventory.intro', 'Bestände in Echtzeit verwalten, Material ausgeben und Wartungen nachvollziehen.') }}</p></div>
                <div class="w-full lg:w-80"><label for="club-inventory-club" class="text-xs font-semibold uppercase text-secondary">{{ tx('auto.Verein', 'Verein') }}</label><select id="club-inventory-club" v-model="selectedClubId" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary"><option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option></select></div>
            </div>
            <div class="mt-5 flex gap-2 overflow-x-auto" role="tablist" :aria-label="tx('club_inventory.sections', 'Inventarbereiche')"><button v-for="tab in tabs" :key="tab.key" type="button" role="tab" :aria-selected="activeTab === tab.key" class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold" :class="activeTab === tab.key ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary hover:bg-inputBg'" @click="activeTab = tab.key">{{ tab.label }} <span class="rounded bg-black/10 px-1.5 py-0.5 text-xs">{{ tab.count }}</span></button></div>
        </section>

        <p v-if="errorMessage" class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">{{ errorMessage }}</p>
        <p v-if="successMessage" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success" role="status">{{ successMessage }}</p>
        <p v-if="loading" class="surface-card p-6 text-sm text-secondary" role="status">{{ tx('auto.Wird geladen …', 'Wird geladen …') }}</p>

        <section v-else-if="activeTab === 'items'" class="grid gap-6" :class="inventory.can_create ? 'xl:grid-cols-[minmax(20rem,0.7fr)_minmax(0,1.3fr)]' : ''">
            <form v-if="inventory.can_create" class="surface-card h-fit space-y-4 p-5" @submit.prevent="saveItem">
                <div><h2 class="text-lg font-semibold text-primary">{{ editingItemId ? tx('club_inventory.item_form.edit_title', 'Gegenstand bearbeiten') : tx('club_inventory.item_form.create_title', 'Gegenstand anlegen') }}</h2><p class="mt-1 text-sm text-secondary">{{ tx('club_inventory.item_form.hint', 'Änderungen erscheinen direkt im Bestand.') }}</p></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="inventory-name" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.name', 'Name') }}</label><input id="inventory-name" v-model="itemForm.name" required maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div>
                    <div><label for="inventory-sku" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.sku', 'Inventarnummer') }}</label><input id="inventory-sku" v-model="itemForm.sku" maxlength="100" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div>
                    <div><label for="inventory-category" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.category', 'Kategorie') }}</label><input id="inventory-category" v-model="itemForm.category" maxlength="120" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div>
                    <div><label for="inventory-location" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.location', 'Lagerort') }}</label><input id="inventory-location" v-model="itemForm.location" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div>
                    <div><label for="inventory-quantity" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.quantity_total', 'Gesamtmenge') }}</label><input id="inventory-quantity" v-model.number="itemForm.quantity_total" type="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div>
                    <div><label for="inventory-condition" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.condition', 'Zustand') }}</label><select id="inventory-condition" v-model="itemForm.condition" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="new">{{ tx('club_inventory.condition.new', 'Neu') }}</option><option value="good">{{ tx('club_inventory.condition.good', 'Gut') }}</option><option value="worn">{{ tx('club_inventory.condition.worn', 'Gebraucht') }}</option><option value="damaged">{{ tx('club_inventory.condition.damaged', 'Beschädigt') }}</option></select></div>
                    <div><label for="inventory-status" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.status', 'Status') }}</label><select id="inventory-status" v-model="itemForm.status" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="active">{{ tx('club_inventory.item_status.active', 'Aktiv') }}</option><option value="maintenance">{{ tx('club_inventory.item_status.maintenance', 'In Wartung') }}</option><option value="retired">{{ tx('club_inventory.item_status.retired', 'Ausgemustert') }}</option></select></div>
                    <div><label for="inventory-department" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.department', 'Abteilung') }}</label><select id="inventory-department" v-model="itemForm.club_department_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="" :disabled="!selectedClub?.can_edit_inventory_globally">{{ tx('club_inventory.scope.club', 'Gesamter Verein') }}</option><option v-for="department in selectedClub?.departments || []" :key="department.id" :value="department.id">{{ department.name }}</option></select></div>
                    <div><label for="inventory-team" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.team', 'Mannschaft') }}</label><select id="inventory-team" v-model="itemForm.team_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="">{{ tx('club_inventory.scope.no_team', 'Keine Mannschaft') }}</option><option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option></select></div>
                </div>
                <div><label for="inventory-description" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.description', 'Beschreibung') }}</label><textarea id="inventory-description" v-model="itemForm.description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></textarea></div>
                <label class="flex items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-primary"><input v-model="itemForm.requires_approval" type="checkbox" class="mt-1"><span>{{ tx('club_inventory.item_form.approval', 'Ausleihe muss freigegeben werden') }}<span class="block text-xs text-secondary">{{ tx('club_inventory.item_form.approval_hint', 'Der Bestand wird erst bei Freigabe reserviert.') }}</span></span></label>
                <div class="flex flex-wrap justify-end gap-2"><button v-if="editingItemId" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="resetItemForm">{{ tx('club_inventory.buttons.cancel', 'Abbrechen') }}</button><button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="Boolean(saving)">{{ saving === 'item' ? tx('club_inventory.buttons.saving', 'Wird gespeichert …') : tx('club_inventory.buttons.save', 'Speichern') }}</button></div>
            </form>
            <div class="space-y-4"><div v-if="!inventory.items.length" class="surface-card p-8 text-center text-sm text-secondary">{{ tx('club_inventory.empty.items', 'Noch kein Inventar vorhanden.') }}</div><article v-for="item in inventory.items" :key="item.id" class="surface-card p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-primary">{{ item.name }}</h2><span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">{{ conditionLabel(item.condition) }}</span></div><p class="mt-1 text-sm text-secondary">{{ [item.sku, item.category, item.location].filter(Boolean).join(' · ') || tx('club_inventory.no_details', 'Ohne Zusatzangaben') }}</p><p v-if="item.description" class="mt-2 text-sm text-secondary">{{ item.description }}</p></div><div class="text-left sm:text-right"><p class="text-2xl font-bold" :class="item.quantity_available ? 'text-success' : 'text-error'">{{ item.quantity_available }} / {{ item.quantity_total }}</p><p class="text-xs text-secondary">{{ tx('club_inventory.available', 'verfügbar') }}</p></div></div><div class="mt-4 flex flex-wrap gap-2"><button v-if="item.can_edit" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="editItem(item)">{{ tx('club_inventory.buttons.edit', 'Bearbeiten') }}</button><button v-if="item.qr_svg_data_uri" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="qrItem = item">{{ tx('club_inventory.buttons.qr_code', 'QR-Code') }}</button><button v-if="item.can_checkout" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary disabled:opacity-50" :disabled="!item.quantity_available || item.status !== 'active'" @click="checkoutForm.item_id = item.id; activeTab = 'loans'">{{ tx('club_inventory.buttons.checkout', 'Ausgeben') }}</button><button v-if="item.can_edit" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="maintenanceForm.item_id = item.id; activeTab = 'maintenance'">{{ tx('club_inventory.buttons.report_maintenance', 'Wartung melden') }}</button><button v-if="item.can_delete" type="button" class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error" :disabled="Boolean(saving)" @click="deleteItem(item)">{{ tx('club_inventory.buttons.delete', 'Löschen') }}</button></div><ClubMetadataSubjectEditor v-if="inventory.can_manage_metadata && item.can_edit" class="mt-3" :club-id="selectedClub.id" subject-type="inventory_item" :subject-id="item.id" :subject-label="item.name" /></article></div>
        </section>

        <section v-else-if="activeTab === 'loans'" class="grid gap-6 xl:grid-cols-[minmax(20rem,0.7fr)_minmax(0,1.3fr)]">
            <form v-if="checkoutItems.length" class="surface-card h-fit space-y-4 p-5" @submit.prevent="checkoutItem"><h2 class="text-lg font-semibold text-primary">{{ tx('club_inventory.checkout_title', 'Material ausgeben') }}</h2><div><label for="loan-item" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.item', 'Gegenstand') }}</label><select id="loan-item" v-model="checkoutForm.item_id" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="" disabled>{{ tx('club_inventory.select', 'Bitte wählen') }}</option><option v-for="item in checkoutItems" :key="item.id" :value="item.id" :disabled="!item.quantity_available || item.status !== 'active'">{{ item.name }} ({{ tx('club_inventory.free_count', '{available} frei', { available: item.quantity_available }) }})</option></select></div><div><label for="loan-member" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.member', 'Mitglied') }}</label><select id="loan-member" v-model="checkoutForm.borrower_id" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="" disabled>{{ tx('club_inventory.select', 'Bitte wählen') }}</option><option v-for="member in selectedClub?.members || []" :key="member.id" :value="member.id">{{ member.name }}</option></select></div><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1"><div><label for="loan-quantity" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.quantity', 'Menge') }}</label><input id="loan-quantity" v-model.number="checkoutForm.quantity" type="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div><div><label for="loan-due" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.due_at', 'Rückgabe bis') }}</label><input id="loan-due" v-model="checkoutForm.due_at" type="datetime-local" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div></div><div><label for="loan-notes" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.notes', 'Notiz') }}</label><textarea id="loan-notes" v-model="checkoutForm.notes" rows="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></textarea></div><button class="w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="Boolean(saving)">{{ tx('club_inventory.buttons.save_checkout', 'Ausleihe speichern') }}</button></form>
            <div class="space-y-3"><div v-if="!inventory.loans.length" class="surface-card p-8 text-center text-sm text-secondary">{{ tx('club_inventory.empty.loans', 'Noch keine Ausleihen vorhanden.') }}</div><article v-for="loan in inventory.loans" :key="loan.id" class="surface-card p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-semibold text-primary">{{ loan.item?.name }}</h2><p class="mt-1 text-sm text-secondary">{{ loan.borrower?.name }} · {{ loan.quantity }} {{ tx('club_inventory.piece', 'Stück') }} · {{ tx('club_inventory.return', 'Rückgabe') }} {{ formatDate(loan.due_at) }}</p></div><span class="self-start rounded-full bg-inputBg px-2.5 py-1 text-xs font-semibold text-secondary">{{ loanStatusLabel(loan.status) }}</span></div><div v-if="['pending', 'active'].includes(loan.status)" class="mt-4 flex flex-wrap gap-2"><button v-if="loan.status === 'pending' && loan.can_approve" type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="Boolean(saving)" @click="loanAction(loan, 'approve')">{{ tx('club_inventory.buttons.approve', 'Freigeben') }}</button><button v-if="loan.status === 'pending' && loan.can_approve" type="button" class="rounded-lg border border-error/40 px-3 py-2 text-sm font-semibold text-error" :disabled="Boolean(saving)" @click="loanAction(loan, 'reject')">{{ tx('club_inventory.buttons.reject', 'Ablehnen') }}</button><button v-if="loan.status === 'active' && loan.can_return" type="button" class="rounded-lg border border-success/40 px-3 py-2 text-sm font-semibold text-success" :disabled="Boolean(saving)" @click="loanAction(loan, 'return')">{{ tx('club_inventory.buttons.record_return', 'Rückgabe buchen') }}</button></div></article></div>
        </section>

        <section v-else class="grid gap-6 xl:grid-cols-[minmax(20rem,0.7fr)_minmax(0,1.3fr)]">
            <form v-if="editableItems.length" class="surface-card h-fit space-y-4 p-5" @submit.prevent="createMaintenance"><h2 class="text-lg font-semibold text-primary">{{ tx('club_inventory.maintenance_title', 'Wartung melden') }}</h2><div><label for="maintenance-item" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.item', 'Gegenstand') }}</label><select id="maintenance-item" v-model="maintenanceForm.item_id" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"><option value="" disabled>{{ tx('club_inventory.select', 'Bitte wählen') }}</option><option v-for="item in editableItems" :key="item.id" :value="item.id">{{ item.name }}</option></select></div><div><label for="maintenance-title" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.maintenance_title', 'Problem/Aufgabe') }}</label><input id="maintenance-title" v-model="maintenanceForm.title" required maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div><div><label for="maintenance-description" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.description', 'Beschreibung') }}</label><textarea id="maintenance-description" v-model="maintenanceForm.description" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></textarea></div><div><label for="maintenance-cost" class="text-xs font-semibold uppercase text-secondary">{{ tx('club_inventory.labels.cost', 'Kosten') }}</label><input id="maintenance-cost" v-model="maintenanceForm.cost" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"></div><button class="w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="Boolean(saving)">{{ tx('club_inventory.buttons.create_maintenance', 'Wartungsfall anlegen') }}</button></form>
            <div class="space-y-3"><div v-if="!inventory.maintenance.length" class="surface-card p-8 text-center text-sm text-secondary">{{ tx('club_inventory.empty.maintenance', 'Keine Wartungsfälle vorhanden.') }}</div><article v-for="record in inventory.maintenance" :key="record.id" class="surface-card p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-semibold text-primary">{{ record.title }}</h2><p class="mt-1 text-sm text-secondary">{{ record.item?.name }}<template v-if="record.description"> · {{ record.description }}</template></p></div><span class="self-start rounded-full bg-inputBg px-2.5 py-1 text-xs font-semibold text-secondary">{{ maintenanceStatusLabel(record.status) }}</span></div><button v-if="record.status !== 'completed'" type="button" class="mt-4 rounded-lg border border-success/40 px-3 py-2 text-sm font-semibold text-success" :disabled="Boolean(saving)" @click="completeMaintenance(record)">{{ tx('club_inventory.buttons.complete_maintenance', 'Als erledigt markieren') }}</button></article></div>
        </section>

        <div v-if="qrItem" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" :aria-label="tx('club_inventory.qr.dialog_label', 'QR-Code für {name}', { name: qrItem.name })" @click.self="qrItem = null"><div class="w-full max-w-sm rounded-xl bg-card p-5 text-center shadow-xl"><h2 class="text-lg font-semibold text-primary">{{ qrItem.name }}</h2><p class="mt-1 text-sm text-secondary">{{ tx('club_inventory.qr.hint', 'QR-Code am Gegenstand anbringen') }}</p><img :src="qrItem.qr_svg_data_uri" :alt="tx('club_inventory.qr.image_alt', 'Inventar-QR-Code für {name}', { name: qrItem.name })" width="256" height="256" class="mx-auto mt-4 aspect-square w-full max-w-64 rounded-lg bg-white p-3"><button type="button" class="mt-4 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="qrItem = null">{{ tx('club_inventory.buttons.close', 'Schließen') }}</button></div></div>
    </div>
</template>
