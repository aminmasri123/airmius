<script setup>
defineProps({
    subscription: { type: Object, required: true },
    isPendingPayment: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
})

defineEmits(['close', 'confirm'])
</script>

<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-primary">
                        {{ isPendingPayment(subscription) ? 'Outfit-Abo-Anfrage abbrechen?' : 'Outfit-Abo kündigen?' }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ isPendingPayment(subscription)
                            ? 'Der Kaufvertrag ist noch nicht abgeschlossen. Die offene Anfrage wird abgebrochen und es wird keine Zahlung mehr erwartet.'
                            : 'Das Abo wird beendet. Bereits geplante interne Bearbeitungsschritte werden danach nicht weitergeführt.' }}
                    </p>
                </div>
                <button type="button" class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary" @click="$emit('close')">
                    <i class="las la-times text-xl"></i>
                </button>
            </div>

            <div class="mt-5 rounded-lg bg-inputBg p-4">
                <p class="text-sm font-semibold text-primary">{{ subscription.plan?.name || 'Outfit-Abo' }}</p>
                <p class="mt-1 text-sm text-secondary">
                    {{ formatMoney(subscription.monthly_price_cents, subscription.currency) }} / Monat
                </p>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="$emit('close')">
                    Abbrechen
                </button>
                <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500" @click="$emit('confirm')">
                    {{ isPendingPayment(subscription) ? 'Anfrage abbrechen' : 'Kündigen' }}
                </button>
            </div>
        </div>
    </div>
</template>

