<script setup>
defineProps({
    selectedCourse: { type: Object, required: true },
    couponForm: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
})

defineEmits(['createCoupon'])
</script>

<template>
    <article class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Landingpage, Gutscheine und Review</h2>
            <p class="mt-1 text-sm text-secondary">Alles, was Besucher vor dem Kauf brauchen: Nutzen, FAQ, Rabatte und Qualitätsstatus.</p>
        </div>
        <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="grid gap-4">
                <div class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-xs font-semibold uppercase text-secondary">Qualitätsreview</p>
                    <p class="mt-2 text-lg font-bold text-primary">{{ selectedCourse.quality_status || 'pending' }}</p>
                    <p v-if="selectedCourse.quality_note" class="mt-1 text-sm text-secondary">{{ selectedCourse.quality_note }}</p>
                </div>
                <div class="grid gap-3">
                    <div v-for="coupon in selectedCourse.coupons" :key="coupon.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-bold text-primary">{{ coupon.code }}</p>
                            <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ coupon.redeemed_count || 0 }} genutzt</span>
                        </div>
                        <p class="mt-1 text-sm text-secondary">{{ coupon.discount_type === 'fixed' ? formatMoney(coupon.discount_value) : `${coupon.discount_value}%` }} Rabatt</p>
                    </div>
                    <p v-if="!selectedCourse.coupons?.length" class="rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">Noch keine Gutscheine angelegt.</p>
                </div>
            </div>
            <form class="grid gap-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="$emit('createCoupon')">
                <h3 class="font-semibold text-primary">Gutschein anlegen</h3>
                <input v-model="couponForm.code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Code, z. B. TEAM20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <select v-model="couponForm.discount_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="percent">Prozent</option>
                        <option value="fixed">Fixbetrag in Cent</option>
                    </select>
                    <input v-model="couponForm.discount_value" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Wert">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="couponForm.max_redemptions" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Max. Nutzungen">
                    <input v-model="couponForm.expires_at" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                </div>
                <label class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-secondary">
                    <input v-model="couponForm.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                    Aktiv
                </label>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Gutschein speichern</button>
            </form>
        </div>
    </article>
</template>
