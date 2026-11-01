<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
const props = defineProps({ removal: Object })
const form = useForm({ decision: 'object', message: '' })
function respond(decision) {
    if (decision === 'confirm' && !window.confirm('Die Vereinszuordnung jetzt endgültig entfernen?')) return
    form.decision = decision
    form.post(`/member-removals/${props.removal.id}`)
}
</script>
<template>
    <Head title="Entfernung aus dem Verein" />
    <section class="mx-auto max-w-2xl space-y-5 rounded-2xl border p-6">
        <h1 class="text-2xl font-bold">Entfernung aus dem Verein</h1>
        <p class="whitespace-pre-wrap">{{ removal.reason }}</p>
        <template v-if="removal.status === 'pending'">
            <p>Ohne Reaktion wird deine Vereinszuordnung am {{ new Date(removal.expires_at).toLocaleString('de-DE') }} entfernt. Ein Widerspruch stoppt die automatische Entfernung.</p>
            <label class="block">Begründung für deinen Widerspruch (optional)
                <textarea v-model="form.message" maxlength="2000" class="mt-2 w-full rounded-lg border bg-inputBg p-3" />
            </label>
            <p v-for="error in form.errors" :key="error" role="alert">{{ error }}</p>
            <div class="flex flex-wrap gap-4">
                <button :disabled="form.processing" class="rounded-lg bg-air-blue p-3 text-white" @click="respond('object')">Widersprechen</button>
                <button :disabled="form.processing" class="rounded-lg border p-3" @click="respond('confirm')">Entfernung bestätigen</button>
            </div>
        </template>
        <p v-else-if="removal.status === 'objected'">Dein Widerspruch wurde erfasst. Die automatische Entfernung ist gestoppt.</p>
        <p v-else-if="removal.status === 'cancelled'">Die Entfernung wurde zurückgenommen.</p>
        <p v-else>Die Vereinszuordnung wurde beendet.</p>
    </section>
</template>
