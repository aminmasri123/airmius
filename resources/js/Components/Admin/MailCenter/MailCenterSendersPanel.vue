<script setup>
defineProps({
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    preferenceForm: {
        type: Object,
        required: true,
    },
    senderForms: {
        type: Object,
        required: true,
    },
    senders: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['save-preferences', 'save-sender', 'test-sender'])

const readyLabel = (sender) => {
    if (sender.resolved?.mailer === 'log') {
        return 'Lokal: Log'
    }

    return sender.ready ? 'Bereit' : 'Unvollständig'
}
</script>

<template>
    <div class="surface-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Absender-Regeln</h2>
                <p class="mt-1 text-sm text-secondary">
                    Für Rechnungen kannst du Haupt- und Ersatz-Absender ohne Code-Änderung wechseln.
                </p>
            </div>
            <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="$emit('save-preferences')">
                <label class="text-sm font-semibold text-primary">
                    Haupt
                    <select v-model="preferenceForm.invoice_primary_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-primary">
                    Ersatz
                    <select v-model="preferenceForm.invoice_fallback_category" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Kein Ersatz</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </label>
                <button
                    type="submit"
                    class="self-end rounded-lg border border-buttonPrimary bg-buttonPrimary px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                    :disabled="preferenceForm.processing"
                >
                    Speichern
                </button>
            </form>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <div v-for="sender in senders" :key="sender.category" class="rounded-lg border border-border bg-bg p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-primary">{{ sender.category }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ sender.address }}</p>
                    </div>
                    <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="sender.ready || sender.resolved?.mailer === 'log' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300'">
                        {{ sender.disabled ? 'Deaktiviert' : readyLabel(sender) }}
                    </span>
                </div>
                <p class="mt-3 text-xs text-secondary">
                    Mailer: {{ sender.resolved?.mailer || sender.mailer || '-' }}
                </p>
                <div v-if="canManageSecrets" class="mt-4 space-y-3 border-t border-border pt-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-secondary">
                            Absenderadresse
                            <input v-model="senderForms[sender.category].from_address" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold text-secondary">
                            Anzeigename
                            <input v-model="senderForms[sender.category].from_name" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold text-secondary">
                            SMTP Host
                            <input v-model="senderForms[sender.category].host" type="text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold text-secondary">
                            Port
                            <input v-model="senderForms[sender.category].port" type="number" min="1" max="65535" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold text-secondary">
                            SMTP Benutzer
                            <input v-model="senderForms[sender.category].username" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        </label>
                        <label class="text-xs font-semibold text-secondary">
                            Verschlüsselung
                            <select v-model="senderForms[sender.category].scheme" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="">Standard</option>
                                <option value="smtp">smtp</option>
                                <option value="smtps">smtps</option>
                            </select>
                        </label>
                    </div>

                    <label class="block text-xs font-semibold text-secondary">
                        Neues Passwort setzen
                        <input
                            v-model="senderForms[sender.category].new_password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Leer lassen, um Passwort nicht zu ändern"
                            class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary"
                        >
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-secondary">
                            <input v-model="senderForms[sender.category].active" type="checkbox" class="h-4 w-4 rounded border-border bg-inputBg text-buttonPrimary accent-buttonPrimary">
                            Mailbox aktiv
                        </label>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-muted" @click="$emit('test-sender', sender)">
                                Testmail
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-buttonPrimary bg-buttonPrimary px-3 py-2 text-xs font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-secondary disabled:hover:brightness-100"
                                :disabled="senderForms[sender.category].processing"
                                @click="$emit('save-sender', sender)"
                            >
                                Speichern
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-secondary">
                        Passwort ist {{ sender.has_password ? 'gesetzt' : 'nicht gesetzt' }}<span v-if="sender.password_updated_at"> · zuletzt aktualisiert {{ sender.password_updated_at }}</span>. Es wird nie angezeigt.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>


