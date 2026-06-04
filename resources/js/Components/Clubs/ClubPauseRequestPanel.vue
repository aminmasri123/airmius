<script setup>
defineProps({
    clubProfile: { type: Object, required: true },
    pauseForm: { type: Object, required: true },
    viewer: { type: Object, required: true },
})

defineEmits(['request-pause'])
</script>

<template>
    <section v-if="viewer.is_member && clubProfile.member_pause_requests_enabled && !viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
        <h2 class="text-lg font-semibold text-primary">Mitgliedschaft pausieren</h2>
        <p class="mt-1 text-sm text-secondary">
            Dein Verein erlaubt Pausen-Anfragen. Die Pause wird erst nach Freigabe durch die Vereinsverwaltung aktiv.
        </p>
        <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="$emit('request-pause')">
            <input v-model="pauseForm.requested_pause_from" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" required>
            <input v-model="pauseForm.requested_pause_until" type="date" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            <input v-model="pauseForm.message" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Grund optional">
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="pauseForm.processing">
                Pause anfragen
            </button>
        </form>
    </section>
</template>
