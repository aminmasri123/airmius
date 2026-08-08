<script setup>
defineProps({
    selectedClub: { type: Object, required: true },
    membershipTypes: { type: Array, default: () => [] },
    contributionRules: { type: Array, default: () => [] },
    contributionIntervals: { type: Array, default: () => [] },
    membershipSettingsFor: { type: Function, required: true },
    saveMembershipSettings: { type: Function, required: true },
    membershipTypeForm: { type: Object, required: true },
    contributionRuleForm: { type: Object, required: true },
    storeMembershipType: { type: Function, required: true },
    storeContributionRule: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    intervalLabel: { type: Function, required: true },
})
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Online-Anfragen</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="saveMembershipSettings">
                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="membershipSettingsFor(selectedClub).membership_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Mitgliedsanfragen erlauben</span>
                            <span class="block text-secondary">Interessenten sehen die Beitragstypen und können eine Anfrage stellen.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                        <input v-model="membershipSettingsFor(selectedClub).member_pause_requests_enabled" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Pausen-Anfragen erlauben</span>
                            <span class="block text-secondary">Mitglieder können eine Pause beantragen; der Verein entscheidet.</span>
                        </span>
                    </label>
                    <div class="flex items-end">
                        <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                    </div>
                </form>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Historische Beitragsregeln</h2>
                <div class="mt-4 space-y-3">
                    <article v-for="rule in contributionRules" :key="rule.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="font-semibold text-primary">{{ rule.name }}</p>
                                <p class="text-sm text-secondary">
                                    {{ rule.membership_type_name || 'Alle Typen' }} / {{ formatMoney(rule.amount) }} / {{ intervalLabel(rule.billing_interval) }}
                                </p>
                                <p class="mt-1 text-xs text-secondary">
                                    Gilt {{ formatDate(rule.valid_from) }} bis {{ formatDate(rule.valid_until) }}
                                    <span v-if="rule.age_min || rule.age_max"> / Alter {{ rule.age_min || 0 }}-{{ rule.age_max || 'offen' }}</span>
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="rule.is_active ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'">
                                {{ rule.is_active ? 'aktiv' : 'inaktiv' }}
                            </span>
                        </div>
                    </article>
                    <p v-if="!contributionRules.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">Noch keine Beitragsregeln.</p>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Mitgliedschaftstyp</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeMembershipType">
                    <input v-model="membershipTypeForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Jugendmitglied" required>
                    <input v-model="membershipTypeForm.slug" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="slug optional">
                    <textarea v-model="membershipTypeForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('Beschreibung')"></textarea>
                    <label class="flex items-center gap-2 text-sm text-primary"><input v-model="membershipTypeForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> Öffentlich sichtbar</label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Typ speichern</button>
                </form>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span v-for="type in membershipTypes" :key="type.id" class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ type.name }}</span>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">Neue Beitragsregel</h2>
                <form class="mt-4 grid gap-3" @submit.prevent="storeContributionRule">
                    <select v-model="contributionRuleForm.club_membership_type_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="">Alle Typen</option>
                        <option v-for="type in membershipTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                    <input v-model="contributionRuleForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regelname" required>
                    <input v-model="contributionRuleForm.amount" type="number" min="0" step="0.01" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beitrag EUR" required>
                    <select v-model="contributionRuleForm.billing_interval" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="interval in contributionIntervals" :key="interval" :value="interval">{{ intervalLabel(interval) }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="contributionRuleForm.valid_from" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary" required>
                        <input v-model="contributionRuleForm.valid_until" type="date" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="contributionRuleForm.age_min" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                        <input v-model="contributionRuleForm.age_max" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                    </div>
                    <textarea v-model="contributionRuleForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Notiz"></textarea>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Regel speichern</button>
                </form>
            </section>
        </aside>
    </section>
</template>
