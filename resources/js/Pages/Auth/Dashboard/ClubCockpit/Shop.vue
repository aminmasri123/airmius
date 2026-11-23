<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })
const props = defineProps({
    club: Object, active: Boolean, seller_can_sell: Boolean, products: Array,
    orders: Array, provider_profile: Object, provider_locations: Array,
    can_manage_bank: Boolean, bank_account: Object,
})
const context = { club_id: props.club.id, club_shop: true }
const bank = useForm({ holder: props.bank_account?.holder || props.club.name, iban: '' })
const bankStatus = { pending: 'In Prüfung', verified: 'Verifiziert', rejected: 'Abgelehnt' }
const editing = ref(null)
const product = useForm({ ...context, title: '', price_cents: 0, category: 'equipment', description: '', manages_stock: true, stock_quantity: 1 })
const profile = useForm({ ...context, display_name: props.provider_profile?.display_name || props.club.name, provider_type: 'club',
    support_email: props.provider_profile?.support_email || '', legal_country: props.provider_profile?.legal_country || 'DE' })
const location = useForm({ ...context, name: '', city: '', street: '', country: 'DE', type: 'pickup', pickup_enabled: true })
const editingLocation = ref(null)
function editLocation(place) {
    editingLocation.value = place?.id || null
    location.reset()
    if (place) Object.keys(location.data()).filter(key => key in place).forEach(key => location[key] = place[key])
}
function saveLocation() {
    const options = { preserveScroll: true, onSuccess: () => editLocation(null) }
    if (editingLocation.value) location.put(route('auth.commerce.provider-locations.update', editingLocation.value), options)
    else location.post(route('auth.commerce.provider-locations.store'), options)
}
function edit(item) {
    editing.value = item?.id || null
    product.reset()
    if (item) Object.keys(product.data()).filter(key => key in item).forEach(key => product[key] = item[key])
}
function saveProduct() {
    product.post(editing.value ? route('auth.commerce.my-products.update', editing.value) : route('auth.commerce.products.store'),
        { preserveScroll: true, onSuccess: () => edit(null) })
}
</script>
<template>
    <Head :title="'Shop-Verwaltung · ' + club.name" />
    <main class="mx-auto max-w-4xl space-y-6 p-5 text-primary">
        <Link :href="route('auth.club-cockpit.index', { club_id: club.id })">← Vereins-Cockpit</Link>
        <h1 class="text-2xl font-bold">Shop-Verwaltung · {{ club.name }}</h1>
        <form v-if="can_manage_bank" class="surface-card grid gap-3 p-5" @submit.prevent="bank.put(route('auth.club-shop.account', club.id), { preserveScroll: true, onSuccess: () => bank.reset('iban') })">
            <h2>Auszahlungskonto des Vereins</h2>
            <p v-if="bank_account">{{ bank_account.holder }} · IBAN endet auf {{ bank_account.last_four }} · {{ bankStatus[bank_account.status] }}</p>
            <label>Kontoinhaber laut Bank <input v-model="bank.holder" required></label>
            <label>IBAN des Vereins <input v-model="bank.iban" required autocomplete="off"></label>
            <p v-for="error in bank.errors" role="alert">{{ error }}</p>
            <button :disabled="bank.processing">Vereinskonto zur Prüfung einreichen</button>
        </form>
        <template v-if="active">
            <form class="surface-card grid gap-3 p-5" @submit.prevent="profile.post(route('auth.commerce.provider-profile.store'), { preserveScroll: true })">
                <h2>Anbieterprofil</h2>
                <label>Anzeigename <input v-model="profile.display_name" required></label>
                <label>Kontakt-E-Mail <input v-model="profile.support_email" type="email"></label>
                <label>Land (Ländercode) <input v-model="profile.legal_country" required maxlength="2"></label>
                <p v-for="error in profile.errors" role="alert">{{ error }}</p>
                <button :disabled="profile.processing">Anbieterprofil speichern</button>
            </form>
            <form class="surface-card grid gap-3 p-5" @submit.prevent="saveLocation">
                <h2>Abholorte</h2>
                <p v-for="place in provider_locations" :key="place.id">{{ place.name }} · {{ place.city }}
                    <button type="button" @click="editLocation(place)">Bearbeiten</button></p>
                <label>Name <input v-model="location.name" required></label>
                <label>Ort <input v-model="location.city"></label>
                <label>Straße <input v-model="location.street"></label>
                <label>Land (Ländercode) <input v-model="location.country" required maxlength="2"></label>
                <p v-for="error in location.errors" role="alert">{{ error }}</p>
                <button :disabled="location.processing">{{ editingLocation ? 'Abholort speichern' : 'Abholort hinzufügen' }}</button>
                <button v-if="editingLocation" type="button" @click="editLocation(null)">Abbrechen</button>
            </form>
            <section class="surface-card grid gap-3 p-5">
                <h2>Produkte</h2>
                <p v-for="item in products" :key="item.id">{{ item.title }} · {{ item.status }}
                    <button @click="edit(item)">Bearbeiten</button></p>
                <form v-if="seller_can_sell || editing" class="grid gap-3" @submit.prevent="saveProduct">
                    <label>Titel <input v-model="product.title" required></label>
                    <label>Beschreibung <textarea v-model="product.description" /></label>
                    <label>Preis in Cent <input type="number" min="0" v-model.number="product.price_cents" required></label>
                    <label v-if="!editing">Bestand <input type="number" min="0" v-model.number="product.stock_quantity"></label>
                    <p v-for="error in product.errors" role="alert">{{ error }}</p>
                    <button :disabled="product.processing">{{ editing ? 'Änderungen speichern' : 'Produkt anlegen' }}</button>
                    <button v-if="editing" type="button" @click="edit(null)">Abbrechen</button>
                </form>
                <p v-else>Zum Anlegen von Produkten wird eine Verkäuferfreigabe benötigt.
                    <Link :href="route('auth.commerce.index')">Zum Verkäuferantrag</Link></p>
            </section>
        </template>
        <section class="surface-card grid gap-3 p-5">
            <h2>Bestellungen</h2>
            <p v-if="!orders.length">Noch keine Bestellungen für diesen Verein.</p>
            <article v-for="order in orders" :key="order.id">
                <strong>{{ order.reference }}</strong> · {{ order.status }}
                <label v-for="item in order.items" :key="item.id" class="block">{{ item.quantity }} × {{ item.title }}
                    <select aria-label="Bearbeitungsstand" :value="item.club_fulfillment_status"
                        @change="router.patch(route('auth.club-shop.fulfill', [club.id, item.id]), { status: $event.target.value }, { preserveScroll: true })">
                        <option value="open">Offen</option><option value="prepared">Vorbereitet</option>
                        <option value="shipped">Versandt</option><option value="delivered">Zugestellt</option>
                    </select>
                </label>
            </article>
        </section>
    </main>
</template>
