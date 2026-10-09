<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SearchableSelect from '@/Components/SearchableSelect.vue'

const props = defineProps({ club: { type: Object, required: true } })
const emit = defineEmits(['recorded', 'cancel'])
const { locale } = useI18n()
const en = computed(() => locale.value !== 'de')
const text = (de, english) => en.value ? english : de
const type = ref('member')
const donor = ref('')
const name = ref('')
const email = ref('')
const address = ref('')
const amount = ref('')
const method = ref('cash')
const today = new Date().toLocaleDateString('sv-SE')
const paidAt = ref(today)
const reference = ref('')
const notes = ref('')
const saving = ref(false)
const error = ref('')
const groups = computed(() => [
    ['member', text('Internes Mitglied', 'Internal member')],
    ['external_member', text('Externes Mitglied', 'External member')],
    ['partner', text('Partner', 'Partner')],
    ['sponsor', text('Sponsor', 'Sponsor')],
    ['other', text('Andere Person / Organisation', 'Other person / organisation')],
])
const options = computed(() => (props.club.donor_options || []).filter(option => option.type === type.value)
    .map(option => ({ ...option, label: option.name + (option.email ? ` - ${option.email}` : '') })))
watch(type, () => { donor.value = ''; error.value = '' })

async function save() {
    if (saving.value) return
    saving.value = true
    error.value = ''
    try {
        const fields = { member: 'user_id', external_member: 'club_external_member_id', partner: 'club_business_partner_id', sponsor: 'sponsor_id' }
        const payload = {
            donor_type: type.value, amount: String(amount.value).replace(',', '.'), method: method.value,
            paid_at: paidAt.value, reference: reference.value || null, notes: notes.value || null,
            ...(type.value === 'other'
                ? { donor_name: name.value.trim(), donor_email: email.value.trim() || null, donor_address: address.value.trim() || null }
                : { [fields[type.value]]: Number(donor.value.split(':').pop()) || null }),
        }
        const response = await window.axios.post(`/api/v1/clubs/${props.club.id}/donations`, payload)
        emit('recorded', response.data)
    } catch (failure) {
        const errors = failure.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : text('Spende konnte nicht erfasst werden.', 'The donation could not be recorded.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="mt-5 space-y-4 border-t border-border pt-5" @submit.prevent="save">
        <h3 class="text-lg font-semibold text-primary">{{ text('Spende erfassen', 'Record donation') }}</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm text-primary">{{ text('Spendergruppe', 'Donor group') }}
                <select v-model="type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2">
                    <option v-for="[value, label] in groups" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
            <div v-if="type !== 'other'" class="text-sm text-primary">
                <label for="donation-donor">{{ text('Spender', 'Donor') }}</label>
                <SearchableSelect v-model="donor" :options="options" label-key="label" value-key="key" input-id="donation-donor" :allow-custom="false" :placeholder="text('Spender suchen', 'Search donors')" :empty-text="text('Keine passenden Einträge.', 'No matching entries.')" />
            </div>
            <template v-else>
                <label class="text-sm text-primary">{{ text('Name', 'Name') }}<input v-model="name" required maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></label>
                <label class="text-sm text-primary">{{ text('E-Mail (optional)', 'Email (optional)') }}<input v-model="email" type="email" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></label>
                <label class="text-sm text-primary">{{ text('Adresse (optional)', 'Address (optional)') }}<textarea v-model="address" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></textarea></label>
            </template>
            <label class="text-sm text-primary">{{ text('Betrag in EUR', 'Amount in EUR') }}<input v-model="amount" required inputmode="decimal" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></label>
            <label class="text-sm text-primary">{{ text('Zahlungsart', 'Payment method') }}<select v-model="method" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"><option value="cash">{{ text('Bar', 'Cash') }}</option><option value="bank_transfer">{{ text('Überweisung', 'Bank transfer') }}</option></select></label>
            <label class="text-sm text-primary">{{ text('Erhalten am', 'Received on') }}<input v-model="paidAt" required type="date" :max="today" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></label>
            <label class="text-sm text-primary">{{ text('Referenz (optional)', 'Reference (optional)') }}<input v-model="reference" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></label>
            <label class="text-sm text-primary md:col-span-2">{{ text('Notiz (optional)', 'Note (optional)') }}<textarea v-model="notes" maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2"></textarea></label>
        </div>
        <p v-if="error" class="text-sm font-semibold text-error" role="alert">{{ error }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" :disabled="saving" class="rounded-lg border border-border px-4 py-2 text-primary" @click="emit('cancel')">{{ text('Abbrechen', 'Cancel') }}</button>
            <button type="submit" :disabled="saving || (type !== 'other' && !donor)" class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary disabled:opacity-50"><i class="las la-save" aria-hidden="true"></i> {{ saving ? text('Wird gespeichert…', 'Saving…') : text('Speichern', 'Save') }}</button>
        </div>
    </form>
</template>
