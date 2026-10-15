<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const copy = (de, en) => String(locale.value || 'de').startsWith('de') ? de : en
const orders = ref([])
const teams = ref([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const showCreate = ref(false)
const itemForms = reactive({})
const createForm = reactive({
    club_id: '',
    team_id: '',
    marketplace_product_id: '',
    title: '',
    order_deadline_at: '',
})

const managerClubs = computed(() => props.clubs.filter((club) => club.can_manage_shop_products))
const teamwearProducts = computed(() => props.products.filter((product) => (
    product.product_type === 'teamwear'
    && Number(product.club_id) === Number(createForm.club_id)
)))

const money = (cents, currency = 'EUR') => new Intl.NumberFormat(
    String(locale.value || 'de'),
    { style: 'currency', currency: currency || 'EUR' },
).format(Number(cents || 0) / 100)

const dateTime = (value) => value
    ? new Intl.DateTimeFormat(String(locale.value || 'de'), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '-'

const messageFrom = (failure) => failure?.response?.data?.message
    || Object.values(failure?.response?.data?.errors || {}).flat().join(' ')
    || copy('Die Aktion konnte nicht abgeschlossen werden.', 'The action could not be completed.')

const ensureItemForm = (order) => {
    if (itemForms[order.id]) return itemForms[order.id]
    const item = order.viewer?.item || {}
    const personalization = item.personalization || {}
    itemForms[order.id] = reactive({
        quantity: item.quantity || 1,
        size: personalization.size || '',
        name: personalization.name || '',
        number: personalization.number || '',
        open: false,
    })
    return itemForms[order.id]
}

const loadOrders = async () => {
    loading.value = true
    error.value = ''
    try {
        const response = await axios.get('/api/v1/commerce/team-bulk-orders', { params: { per_page: 50 } })
        orders.value = response.data?.data || []
        orders.value.forEach(ensureItemForm)
    } catch (failure) {
        error.value = messageFrom(failure)
    } finally {
        loading.value = false
    }
}

const loadTeams = async () => {
    teams.value = []
    createForm.team_id = ''
    createForm.marketplace_product_id = ''
    if (!createForm.club_id) return
    try {
        const response = await axios.get('/api/v1/teams', { params: { club_id: createForm.club_id, per_page: 100 } })
        teams.value = response.data?.data || []
    } catch (failure) {
        error.value = messageFrom(failure)
    }
}

watch(() => createForm.club_id, loadTeams)

const createOrder = async () => {
    if (!createForm.club_id || !createForm.team_id || !createForm.marketplace_product_id || !createForm.order_deadline_at) {
        error.value = copy('Bitte Verein, Team, Produkt und Frist auswählen.', 'Please select club, team, product and deadline.')
        return
    }
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        await axios.post('/api/v1/commerce/team-bulk-orders', {
            ...createForm,
            club_id: Number(createForm.club_id),
            team_id: Number(createForm.team_id),
            marketplace_product_id: Number(createForm.marketplace_product_id),
            title: createForm.title || null,
            order_deadline_at: new Date(createForm.order_deadline_at).toISOString(),
        })
        notice.value = copy('Sammelauftrag wurde angelegt.', 'Bulk order created.')
        showCreate.value = false
        Object.assign(createForm, { team_id: '', marketplace_product_id: '', title: '', order_deadline_at: '' })
        await loadOrders()
    } catch (failure) {
        error.value = messageFrom(failure)
    } finally {
        busy.value = false
    }
}

const saveItem = async (order) => {
    const form = ensureItemForm(order)
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        await axios.post(`/api/v1/commerce/team-bulk-orders/${order.id}/items`, {
            quantity: Number(form.quantity),
            personalization: {
                ...(form.size ? { size: form.size } : {}),
                ...(form.name ? { name: form.name } : {}),
                ...(form.number ? { number: form.number } : {}),
            },
        })
        notice.value = copy('Deine Position wurde gespeichert.', 'Your order line was saved.')
        form.open = false
        await loadOrders()
    } catch (failure) {
        error.value = messageFrom(failure)
    } finally {
        busy.value = false
    }
}

const exportSupplierData = async (order) => {
    busy.value = true
    error.value = ''
    try {
        const response = await axios.get(`/api/v1/commerce/team-bulk-orders/${order.id}/supplier-export`)
        const blob = new Blob([JSON.stringify(response.data?.data || {}, null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = `sammelbestellung-${order.id}.json`
        anchor.click()
        URL.revokeObjectURL(url)
        notice.value = copy('Lieferantenexport wurde erstellt.', 'Supplier export created.')
    } catch (failure) {
        error.value = messageFrom(failure)
    } finally {
        busy.value = false
    }
}

onMounted(loadOrders)
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-air-blue">{{ copy('Teamwear', 'Teamwear') }}</p>
                    <h2 class="mt-1 text-2xl font-bold text-primary">{{ copy('Teamsammelbestellungen', 'Team bulk orders') }}</h2>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        {{ copy('Mitglieder erfassen Größe, Personalisierung und Menge bis zur Frist. Der beim Anlegen gespeicherte Preis bleibt unverändert.', 'Members submit size, personalization and quantity before the deadline. The price captured at creation remains unchanged.') }}
                    </p>
                </div>
                <button
                    v-if="managerClubs.length"
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary"
                    @click="showCreate = !showCreate"
                >
                    <i class="las la-plus-circle mr-1"></i>
                    {{ copy('Sammelauftrag anlegen', 'Create bulk order') }}
                </button>
            </div>
        </div>

        <div v-if="showCreate" class="border-b border-border bg-bg p-5">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm font-semibold text-primary">
                    {{ copy('Verein', 'Club') }}
                    <select v-model="createForm.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">{{ copy('Bitte wählen', 'Select') }}</option>
                        <option v-for="club in managerClubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-primary">
                    Team
                    <select v-model="createForm.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">{{ copy('Bitte wählen', 'Select') }}</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-primary">
                    {{ copy('Teamwear-Produkt', 'Teamwear product') }}
                    <select v-model="createForm.marketplace_product_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">{{ copy('Bitte wählen', 'Select') }}</option>
                        <option v-for="product in teamwearProducts" :key="product.id" :value="product.id">{{ product.title }}</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-primary">
                    {{ copy('Bestellfrist', 'Order deadline') }}
                    <input v-model="createForm.order_deadline_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                </label>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="flex-1 text-sm font-semibold text-primary">
                    {{ copy('Titel (optional)', 'Title (optional)') }}
                    <input v-model="createForm.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" maxlength="160">
                </label>
                <button type="button" class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="busy" @click="createOrder">
                    {{ copy('Speichern', 'Save') }}
                </button>
            </div>
            <p v-if="createForm.club_id && !teamwearProducts.length" class="mt-3 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-sm text-warning">
                {{ copy('Für diesen Verein ist kein freigegebenes Teamwear-Produkt vorhanden.', 'No approved teamwear product exists for this club.') }}
            </p>
        </div>

        <div class="p-5">
            <p v-if="notice" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success" role="status">{{ notice }}</p>
            <p v-if="error" class="mb-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">{{ error }}</p>
            <div v-if="loading" class="py-12 text-center text-secondary">{{ copy('Wird geladen...', 'Loading...') }}</div>
            <div v-else-if="!orders.length" class="rounded-lg border border-dashed border-border p-8 text-center text-secondary">
                {{ copy('Noch keine Sammelbestellung für deine Vereine oder Teams.', 'No bulk order for your clubs or teams yet.') }}
            </div>
            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article v-for="order in orders" :key="order.id" class="rounded-lg border border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-lg font-bold text-primary">{{ order.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ order.team?.name || 'Team' }} · {{ order.product?.title || order.supplier_reference }}</p>
                        </div>
                        <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold" :class="new Date(order.order_deadline_at) > new Date() ? 'text-success' : 'text-secondary'">
                            {{ new Date(order.order_deadline_at) > new Date() ? copy('Offen', 'Open') : copy('Beendet', 'Closed') }}
                        </span>
                    </div>
                    <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <p class="rounded-lg bg-bg p-3 text-secondary">{{ copy('Dein Stückpreis', 'Your unit price') }}<br><strong class="text-primary">{{ money(order.payable_unit_price_cents, order.currency) }}</strong></p>
                        <p class="rounded-lg bg-bg p-3 text-secondary">{{ copy('Frist', 'Deadline') }}<br><strong class="text-primary">{{ dateTime(order.order_deadline_at) }}</strong></p>
                    </div>
                    <p v-if="order.viewer?.item" class="mt-3 text-sm font-semibold text-success">
                        {{ copy('Deine Position', 'Your line') }}: {{ order.viewer.item.quantity }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="order.viewer?.is_team_member"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="busy || new Date(order.order_deadline_at) <= new Date()"
                            @click="ensureItemForm(order).open = !ensureItemForm(order).open"
                        >
                            {{ order.viewer?.item ? copy('Position ändern', 'Edit line') : copy('Position erfassen', 'Add line') }}
                        </button>
                        <button v-if="order.viewer?.can_manage" type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:opacity-50" :disabled="busy" @click="exportSupplierData(order)">
                            <i class="las la-download mr-1"></i>{{ copy('Lieferantenexport', 'Supplier export') }}
                        </button>
                    </div>
                    <div v-if="ensureItemForm(order).open" class="mt-4 grid gap-3 rounded-lg border border-border bg-bg p-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold text-primary">{{ copy('Menge', 'Quantity') }}<input v-model.number="ensureItemForm(order).quantity" type="number" min="1" max="99" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm font-semibold text-primary">{{ copy('Größe', 'Size') }}<input v-model="ensureItemForm(order).size" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm font-semibold text-primary">{{ copy('Name (optional)', 'Name (optional)') }}<input v-model="ensureItemForm(order).name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="text-sm font-semibold text-primary">{{ copy('Nummer (optional)', 'Number (optional)') }}<input v-model="ensureItemForm(order).number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"></label>
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50 sm:col-span-2" :disabled="busy" @click="saveItem(order)">{{ copy('Position speichern', 'Save line') }}</button>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
