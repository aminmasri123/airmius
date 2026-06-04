<script setup>
defineProps({
    couponForm: {
        type: Object,
        required: true,
    },
    addonForm: {
        type: Object,
        required: true,
    },
    coupons: {
        type: Array,
        default: () => [],
    },
    addons: {
        type: Array,
        default: () => [],
    },
    moneyInputAttrs: {
        type: Object,
        required: true,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
    storeCoupon: {
        type: Function,
        required: true,
    },
    storeAddon: {
        type: Function,
        required: true,
    },
})
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-2">
        <article class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">Rabattcode erstellen</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeCoupon">
                <input v-model="couponForm.code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Code">
                <input v-model="couponForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                <select v-model="couponForm.type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="percent">Prozent</option>
                    <option value="fixed">Festbetrag</option>
                </select>
                <input v-if="couponForm.type === 'percent'" v-model="couponForm.percent_off" type="number" min="1" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Prozent">
                <input v-else v-model="couponForm.value_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Betrag in EUR">
                <input v-model="couponForm.max_redemptions" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Max. Nutzungen">
                <label class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="couponForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                    Aktiv
                </label>
                <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="coupon in coupons" :key="coupon.id">
                            <td class="py-3 font-semibold text-primary">{{ coupon.code }}</td>
                            <td class="py-3 text-secondary">{{ coupon.type === 'percent' ? `${coupon.percent_off}%` : formatMoney(coupon.value_cents) }}</td>
                            <td class="py-3 text-secondary">{{ coupon.redeemed_count }} genutzt</td>
                            <td class="py-3 text-right" :class="coupon.is_active ? 'text-success' : 'text-secondary'">{{ coupon.is_active ? 'Aktiv' : 'Inaktiv' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">Add-on erstellen</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeAddon">
                <input v-model="addonForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug">
                <input v-model="addonForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name">
                <input v-model="addonForm.monthly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Monat in EUR">
                <input v-model="addonForm.yearly_price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Jahr in EUR">
                <textarea v-model="addonForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>
                <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
            </form>
            <div class="mt-5 grid gap-3">
                <div v-for="addon in addons" :key="addon.id" class="rounded-lg border border-border p-3">
                    <p class="font-semibold text-primary">{{ addon.name }}</p>
                    <p class="text-sm text-secondary">{{ formatMoney(addon.monthly_price_cents) }} / Monat · {{ addon.purchases_count }} Käufe</p>
                </div>
            </div>
        </article>
    </section>
</template>


