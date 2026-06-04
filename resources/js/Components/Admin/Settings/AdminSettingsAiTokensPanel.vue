<script setup>
defineProps({
    aiProviderTokens: { type: Array, default: () => [] },
    tokenSeverityClass: { type: Function, required: true },
    tokenDotClass: { type: Function, required: true },
    tokenExpiryLabel: { type: Function, required: true },
})
</script>

<template>
    <div class="rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">KI-Token & Anbieter</h2>
                <p class="mt-1 text-sm text-secondary">
                    Kontrolliere Ablaufdatum und Konfiguration deiner KI-Anbieter. Tokens werden aus Sicherheitsgründen nie angezeigt.
                </p>
            </div>
            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                {{ aiProviderTokens.length }} Anbieter
            </span>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div
                v-for="token in aiProviderTokens"
                :key="token.key"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-primary">{{ token.label }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ token.model || 'Kein Modell gesetzt' }}</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="tokenSeverityClass(token.severity)">
                        <span class="h-2 w-2 rounded-full" :class="tokenDotClass(token.severity)"></span>
                        {{ token.status_label }}
                    </span>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">API-Key</dt>
                        <dd class="font-semibold" :class="token.has_api_key ? 'text-success' : 'text-error'">
                            {{ token.has_api_key ? 'gesetzt' : 'fehlt' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Ablaufdatum</dt>
                        <dd class="text-right font-semibold text-primary">{{ tokenExpiryLabel(token) }}</dd>
                    </div>
                    <div v-if="token.days_remaining !== null" class="flex justify-between gap-3">
                        <dt class="text-secondary">Restzeit</dt>
                        <dd class="font-semibold text-primary">
                            {{ token.days_remaining > 0 ? `${token.days_remaining} Tage` : 'abgelaufen' }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 rounded-lg border px-3 py-2 text-xs leading-5" :class="tokenSeverityClass(token.severity)">
                    {{ token.message }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-xs text-secondary">
            Airmius prüft die Tokens stündlich per Scheduler und sendet Admin-Benachrichtigungen, wenn ein Anbieter abläuft oder nicht korrekt konfiguriert ist.
        </p>
    </div>
</template>

