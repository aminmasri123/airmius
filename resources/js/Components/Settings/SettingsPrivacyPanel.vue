<script setup>
defineProps({
    form: { type: Object, required: true },
    saveAddress: { type: Function, required: true },
    settingsText: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-5">
        <form class="space-y-5" @submit.prevent="saveAddress">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ settingsText('privacy.title', 'Privatsphäre') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('privacy.description', 'Lege fest, wer dein Profil sehen, dich direkt kontaktieren oder dir Freundschaftsanfragen senden darf.') }}
                </p>
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.profile_visibility', 'Profil-Sichtbarkeit') }}</span>
                <select v-model="form.profile_visibility" class="input">
                    <option value="public">{{ settingsText('privacy.options.public', 'Alle angemeldeten Personen') }}</option>
                    <option value="private">{{ settingsText('privacy.options.private', 'Nur ich, Freunde und Follower') }}</option>
                </select>
                <p class="mt-1 text-xs text-secondary">
                    {{ settingsText('privacy.profile_visibility_help', 'Diese Einstellung steuert, ob andere dein Profil und deine Profilinhalte sehen können.') }}
                </p>
                <p v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</p>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.direct_messages', 'Nachrichten erhalten') }}</span>
                <select v-model="form.direct_message_privacy" class="input">
                    <option value="everyone">{{ settingsText('privacy.options.everyone', 'Alle angemeldeten Personen') }}</option>
                    <option value="friends">{{ settingsText('privacy.options.friends', 'Nur Freunde') }}</option>
                </select>
                <p v-if="form.errors.direct_message_privacy" class="mt-1 text-sm text-error">{{ form.errors.direct_message_privacy }}</p>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.friend_requests', 'Freundschaftsanfragen erhalten') }}</span>
                <select v-model="form.friend_request_privacy" class="input">
                    <option value="everyone">{{ settingsText('privacy.options.everyone', 'Alle angemeldeten Personen') }}</option>
                    <option value="friends">{{ settingsText('privacy.options.friends', 'Nur Freunde') }}</option>
                </select>
                <p v-if="form.errors.friend_request_privacy" class="mt-1 text-sm text-error">{{ form.errors.friend_request_privacy }}</p>
            </label>

            <div class="rounded-lg border border-border bg-bg p-4">
                <h3 class="text-sm font-semibold text-primary">{{ settingsText('privacy.ads_title', 'Werbung & Messung') }}</h3>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('privacy.ads_description', 'Ohne Einwilligung zeigen wir nur kontextuelle Anzeigen und speichern keine personalisierten Retargeting-Signale.') }}
                </p>
                <label class="mt-4 flex items-start gap-3 text-sm text-primary">
                    <input v-model="form.ads_personalization_consent" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        <span class="block font-semibold">{{ settingsText('privacy.ads_personalization', 'Personalisierte Anzeigen erlauben') }}</span>
                        <span class="text-xs text-secondary">{{ settingsText('privacy.ads_personalization_help', 'Nutzt z. B. vorherige Marketplace-Interessen, um passendere Anzeigen zu zeigen.') }}</span>
                    </span>
                </label>
                <label class="mt-4 flex items-start gap-3 text-sm text-primary">
                    <input v-model="form.ads_measurement_consent" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        <span class="block font-semibold">{{ settingsText('privacy.ads_measurement', 'Conversion-Messung erlauben') }}</span>
                        <span class="text-xs text-secondary">{{ settingsText('privacy.ads_measurement_help', 'Ordnet Klicks anonymisierten Kampagnenereignissen wie Checkout oder Kauf zu.') }}</span>
                    </span>
                </label>
            </div>

            <button class="btn-primary" :disabled="form.processing">
                {{ settingsText('privacy.save_button', 'Privatsphäre speichern') }}
            </button>
        </form>
    </div>
</template>

<style scoped>
.input {
    @apply mt-1 w-full rounded-lg border-border bg-inputBg text-primary;
}

.btn-primary {
    @apply rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary;
}
</style>

