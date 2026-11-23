<script setup>
import { reactive, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
const props = defineProps({ accounts: Array, items: Object })
const references = reactive({})
const busy = ref(false)
const options = { preserveScroll: true, onStart: () => busy.value = true, onFinish: () => busy.value = false }
const accountFor = line => props.accounts.find(a => a.club_id === line.shop_club_id && a.status === 'verified')
function transfer(line, action) {
    router.post(route('admin.club-payouts.transfer', line.id), { action, account_id: accountFor(line)?.id, reference: references[line.id] }, options)
}
</script>
<template>
    <main class="mx-auto max-w-4xl space-y-5 p-5">
        <h1 class="text-2xl font-bold">Vereinsauszahlungen</h1>
        <section v-for="a in accounts.filter(a => a.status === 'pending')" :key="a.id" class="surface-card grid gap-2 p-4">
            <h2>{{ a.club_name }} · Kontoprüfung</h2><p>{{ a.holder }} · {{ a.iban }}</p>
            <p>Prüfe anhand des Banknachweises, dass der Verein Inhaber dieser IBAN ist.</p>
            <button :disabled="busy" @click="router.put(route('admin.club-payouts.review', a.id), {status:'verified'}, options)">Kontoinhaberschaft bestätigen</button>
            <button :disabled="busy" @click="router.put(route('admin.club-payouts.review', a.id), {status:'rejected'}, options)">Ablehnen</button>
        </section>
        <h2>Offene Verkaufspositionen</h2>
        <article v-for="line in items.data" :key="line.id" class="surface-card grid gap-2 p-4">
            <strong>{{ accountFor(line)?.club_name || 'Verein ' + line.shop_club_id }} · {{ line.title }} (#{{ line.id }})</strong>
            <template v-if="line.club_payout_account_id && accountFor(line)?.id === line.club_payout_account_id">
                <p>Überweise {{ (line.club_payout_cents / 100).toFixed(2) }} {{ line.currency }} an {{ accountFor(line).holder }} · {{ accountFor(line).iban }}. Verwendungszweck: AIR-V-{{ line.id }}</p>
                <label>Bankreferenz der ausgeführten Überweisung <input v-model="references[line.id]"></label>
                <button :disabled="busy || !references[line.id]" @click="transfer(line,'paid')">Überweisung als ausgeführt buchen</button>
                <button :disabled="busy" @click="transfer(line,'release')">Nicht ausgeführte Überweisung verwerfen</button>
            </template>
            <button v-else :disabled="busy || !accountFor(line)" @click="transfer(line,'prepare')">Überweisung vorbereiten</button>
        </article>
        <Link v-if="items.prev_page_url" :href="items.prev_page_url">Zurück</Link> <Link v-if="items.next_page_url" :href="items.next_page_url">Weiter</Link>
    </main>
</template>
