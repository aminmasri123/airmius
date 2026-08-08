<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    club: { type: Object, required: true },
    sports: { type: Array, default: () => [] },
    clubEditTabItems: { type: Array, default: () => [] },
    editingSponsorIds: { type: Object, default: () => ({}) },
    activeClubEditTab: { type: Function, required: true },
    setClubEditTab: { type: Function, required: true },
    clubEditFormFor: { type: Function, required: true },
    cancelClubEdit: { type: Function, required: true },
    updateClub: { type: Function, required: true },
    sponsorFormFor: { type: Function, required: true },
    resetSponsorForm: { type: Function, required: true },
    editSponsor: { type: Function, required: true },
    submitSponsor: { type: Function, required: true },
    deleteSponsor: { type: Function, required: true },
    sponsorLogoUrl: { type: Function, required: true },
})
</script>

<template>
    <form
        class="rounded-xl border border-border bg-bg p-4 sm:p-5"
        @submit.prevent="updateClub(club)"
    >
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-primary">{{ $t('Vereinsdaten bearbeiten') }}</h2>
                <p class="text-xs text-secondary">
                    {{ $t('Basisdaten, Adresse und Sportart pflegen. Offizielle Prüfung läuft separat über Admin.') }}
                </p>
            </div>
            <span
                class="w-fit rounded-full px-3 py-1 text-xs font-semibold"
                :class="club.verification_status === 'verified'
                    ? 'bg-success/10 text-success'
                    : club.verification_status === 'rejected'
                        ? 'bg-error/10 text-error'
                        : 'bg-warning/10 text-warning'"
            >
                {{ club.verification_status === 'verified' ? $t('Freigegeben') : club.verification_status === 'rejected' ? $t('Abgelehnt') : $t('Wartet auf Prüfung') }}
            </span>
        </div>

        <div class="mb-4 flex flex-wrap gap-2 border-b border-border pb-2">
            <button
                v-for="tab in clubEditTabItems"
                :key="tab.key"
                type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="activeClubEditTab(club) === tab.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-card hover:text-primary'"
                @click="setClubEditTab(club, tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <label v-if="activeClubEditTab(club) === 'basis'" class="block xl:col-span-2">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Vereinsname') }}</span>
                <input v-model="clubEditFormFor(club).name" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <label v-if="activeClubEditTab(club) === 'basis'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Sportart') }}</span>
                <SearchableSelect
                    v-model="clubEditFormFor(club).sport_type"
                    class="mt-1 w-full"
                    :options="sports"
                    value-key="slug"
                    translation-prefix="sports"
                    category-translation-prefix="sport_categories"
                    :placeholder="$t('Sportart suchen')"
                />
            </label>

            <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                <input
                    v-model="clubEditFormFor(club).is_listed"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ $t('Verein auflisten') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.') }}</span>
                </span>
            </label>

            <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                <input
                    v-model="clubEditFormFor(club).teams_are_listed"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ $t('Teams auflisten') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.') }}</span>
                </span>
            </label>

            <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                <input
                    v-model="clubEditFormFor(club).members_can_post_to_club"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ $t('Vereinsbeiträge erlauben') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Normale Mitglieder dürfen Beiträge für den Verein erstellen.') }}</span>
                </span>
            </label>

            <label v-if="activeClubEditTab(club) === 'sichtbarkeit'" class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                <input
                    v-model="clubEditFormFor(club).members_can_post_to_teams"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ $t('Teambeiträge erlauben') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.') }}</span>
                </span>
            </label>

            <label v-if="activeClubEditTab(club) === 'basis'" class="block xl:col-span-2">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Vereinsnummer zur Prüfung') }}</span>
                <input
                    v-model="clubEditFormFor(club).official_club_number"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="$t('z. B. Vereinsregister- oder Verbandsnummer')"
                >
                <span class="mt-1 block text-xs text-secondary">
                    {{ $t('Neue oder geänderte Nummern werden zur Admin-Prüfung vorgemerkt.') }}
                </span>
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Land') }}</span>
                <select v-model="clubEditFormFor(club).country" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option value="DE">{{ $t('Deutschland') }}</option>
                    <option value="AT">{{ $t('Österreich') }}</option>
                    <option value="CH">{{ $t('Schweiz') }}</option>
                    <option value="FR">{{ $t('Frankreich') }}</option>
                    <option value="NL">{{ $t('Niederlande') }}</option>
                    <option value="BE">{{ $t('Belgien') }}</option>
                    <option value="TR">{{ $t('Türkei') }}</option>
                    <option value="US">{{ $t('USA') }}</option>
                </select>
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Stadt') }}</span>
                <input v-model="clubEditFormFor(club).city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('PLZ') }}</span>
                <input v-model="clubEditFormFor(club).postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Region') }}</span>
                <input v-model="clubEditFormFor(club).state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Straße') }}</span>
                <input v-model="clubEditFormFor(club).street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <label v-if="activeClubEditTab(club) === 'adresse'" class="block">
                <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Hausnummer') }}</span>
                <input v-model="clubEditFormFor(club).house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </label>

            <div v-if="activeClubEditTab(club) === 'bank'" class="rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('Bankkonto für Mitglieder-Überweisungen') }}</p>
                <p class="mt-1 text-xs text-secondary">
                    {{ $t('Diese Daten werden Mitgliedern bei offenen Vereinsrechnungen angezeigt.') }}
                </p>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Kontoinhaber') }}</span>
                        <input
                            v-model="clubEditFormFor(club).sepa_account_holder"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :placeholder="$t('Name laut Bankkonto')"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('IBAN') }}</span>
                        <input
                            v-model="clubEditFormFor(club).sepa_iban"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :placeholder="$t('DE...')"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('BIC') }}</span>
                        <input
                            v-model="clubEditFormFor(club).sepa_bic"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :placeholder="$t('GENODE...')"
                        >
                    </label>
                </div>
            </div>

            <div v-if="activeClubEditTab(club) === 'sponsoren'" class="space-y-4 rounded-lg border border-border bg-card p-3 md:col-span-2 xl:col-span-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-secondary">{{ $t('Vereins-Sponsoren') }}</p>
                        <p class="mt-1 text-xs text-secondary">
                            {{ $t('Pflege Sponsoren, die öffentlich dem Verein zugeordnet werden.') }}
                        </p>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                        {{ club.sponsors?.length || 0 }} {{ $t('Sponsoren') }}
                    </span>
                </div>

                <div v-if="club.subscription_capabilities?.sponsors === false" class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                    {{ $t('Sponsorenverwaltung ist ab dem Club-Plan verfügbar.') }}
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Sponsorname') }}</span>
                        <input v-model="sponsorFormFor(club).name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="$t('Sponsorname')">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Kontaktperson') }}</span>
                        <input v-model="sponsorFormFor(club).contact_name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="$t('Ansprechpartner')">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('E-Mail') }}</span>
                        <input v-model="sponsorFormFor(club).email" type="email" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsor@example.com">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Website') }}</span>
                        <input v-model="sponsorFormFor(club).website" type="url" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" :placeholder="$t('commerce.ui.url_placeholder')">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Budget / Betrag') }}</span>
                        <input v-model="sponsorFormFor(club).amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="0,00">
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Start') }}</span>
                            <input v-model="sponsorFormFor(club).starts_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Ende') }}</span>
                            <input v-model="sponsorFormFor(club).ends_at" type="date" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                        </label>
                    </div>
                    <label class="block md:col-span-1 xl:col-span-3">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Logo für helle Flächen') }}</span>
                        <input v-model="sponsorFormFor(club).logo_light" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsors/logo-light.webp oder https://...">
                    </label>
                    <label class="block md:col-span-1 xl:col-span-3">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('Logo für dunkle Flächen') }}</span>
                        <input v-model="sponsorFormFor(club).logo_dark" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="sponsors/logo-dark.webp oder https://...">
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="club.subscription_capabilities?.sponsors === false"
                        @click="submitSponsor(club)"
                    >
                        {{ editingSponsorIds[club.id] ? $t('Sponsor speichern') : $t('Sponsor erstellen') }}
                    </button>
                    <button
                        v-if="editingSponsorIds[club.id]"
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                        @click="resetSponsorForm(club)"
                    >
                        {{ $t('Abbrechen') }}
                    </button>
                </div>

                <div class="divide-y divide-border overflow-hidden rounded-lg border border-border">
                    <div
                        v-for="sponsor in club.sponsors || []"
                        :key="sponsor.id"
                        class="flex flex-col gap-3 bg-bg p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-inputBg text-xs font-bold text-primary">
                                <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" class="h-full w-full object-contain p-1">
                                <span v-else>{{ sponsor.name?.slice(0, 2)?.toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ sponsor.name }}</p>
                                <p class="text-xs text-secondary">
                                    {{ sponsor.amount || '-' }} EUR · {{ sponsor.starts_at || '-' }} bis {{ sponsor.ends_at || '-' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary" @click="editSponsor(club, sponsor)">
                                {{ $t('Bearbeiten') }}
                            </button>
                            <button type="button" class="rounded-lg border border-error px-3 py-1 text-sm font-semibold text-error" @click="deleteSponsor(club, sponsor)">
                                {{ $t('Löschen') }}
                            </button>
                        </div>
                    </div>
                    <div v-if="!(club.sponsors || []).length" class="bg-bg p-4 text-sm text-secondary">
                        {{ $t('Noch keine Sponsoren für diesen Verein vorhanden.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="cancelClubEdit">
                {{ $t('Abbrechen') }}
            </button>
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                {{ $t('Speichern') }}
            </button>
        </div>
    </form>
</template>
