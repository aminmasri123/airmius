<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { computed } from 'vue'

const props = defineProps({
    clubCreateStep: {
        type: Number,
        default: 1,
    },
    clubCreateSteps: {
        type: Array,
        default: () => [],
    },
    clubForm: {
        type: Object,
        required: true,
    },
    clubModalNotice: {
        type: [String, Object, null],
        default: null,
    },
    show: {
        type: Boolean,
        default: false,
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    sports: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'close',
    'create',
    'next-step',
    'previous-step',
    'update:clubCreateStep',
])

const stepModel = computed({
    get: () => props.clubCreateStep,
    set: (value) => emit('update:clubCreateStep', value),
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60"
            @click.self="$emit('close')"
        >
            <div class="flex h-full w-full flex-col bg-card sm:h-auto sm:max-h-[92vh] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-border sm:shadow-xl">
                <div class="shrink-0 border-b border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-primary">
                                {{ $t('Verein registrieren') }}
                            </h2>

                            <p class="mt-1 text-sm text-secondary">
                                {{ $t('Schritt') }} {{ clubCreateStep }} {{ $t('von') }} {{ clubCreateSteps.length }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border text-secondary hover:border-borderHover hover:text-primary"
                            :aria-label="$t('common.close')"
                            @click="$emit('close')"
                        >
                            <i class="las la-times text-lg"></i>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            v-for="step in clubCreateSteps"
                            :key="step.number"
                            type="button"
                            class="rounded-full px-2 py-2 text-xs font-semibold transition"
                            :class="clubCreateStep === step.number
                                ? 'bg-buttonPrimary text-buttonTextPrimary'
                                : clubCreateStep > step.number
                                    ? 'bg-air-green/15 text-air-green'
                                    : 'bg-inputBg text-secondary'"
                            @click="stepModel = step.number"
                        >
                            {{ step.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div
                        v-if="clubModalNotice"
                        class="mb-4 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"
                    >
                        {{ clubModalNotice }}
                    </div>

                    <section v-if="clubCreateStep === 1" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ $t('Basisdaten') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ $t('Name, Sportart und Land des Vereins. Nach dem Absenden prüft Airmius den Antrag.') }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ $t('Vereinsname') }}
                            </label>

                            <input
                                v-model="clubForm.name"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.name ? 'border-error' : ''"
                                :placeholder="$t('Vereinsname')"
                                required
                            >
                            <p v-if="clubForm.errors.name" class="mt-1 text-xs text-error">{{ clubForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ $t('Sportart') }}
                            </label>

                            <SearchableSelect
                                v-model="clubForm.sport_type"
                                class="mt-1 w-full"
                                :options="sports"
                                value-key="slug"
                                translation-prefix="sports"
                                category-translation-prefix="sport_categories"
                                :placeholder="$t('Sportart suchen')"
                            />
                            <p v-if="clubForm.errors.sport_type" class="mt-1 text-xs text-error">{{ clubForm.errors.sport_type }}</p>
                        </div>

                        <label
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm text-primary transition',
                                clubForm.is_official ? 'border-air-blue bg-air-blue/10' : 'border-border bg-bg',
                            ]"
                        >
                            <input
                                v-model="clubForm.is_official"
                                type="checkbox"
                                class="sr-only"
                            >
                            <span
                                :class="[
                                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-sm transition',
                                    clubForm.is_official
                                        ? 'border-air-blue bg-air-blue text-white'
                                        : 'border-border bg-inputBg text-transparent',
                                ]"
                            >
                                <i class="las la-check"></i>
                            </span>
                            <span>
                                <span class="block font-semibold">{{ $t('Offizielle Prüfung beantragen') }}</span>
                                <span class="block text-secondary">{{ $t('Der Verein wird erst nach Admin-Freigabe öffentlich sichtbar und als offiziell markiert.') }}</span>
                            </span>
                        </label>

                        <div v-if="clubForm.is_official">
                            <label class="block text-sm font-semibold text-primary">
                                {{ $t('Vereinsnummer zur Prüfung') }}
                            </label>

                            <input
                                v-model="clubForm.official_club_number"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                :class="clubForm.errors.official_club_number ? 'border-error' : ''"
                                :placeholder="$t('z. B. Vereinsregister- oder Verbandsnummer')"
                            >
                            <p v-if="clubForm.errors.official_club_number" class="mt-1 text-xs text-error">{{ clubForm.errors.official_club_number }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-primary">
                                {{ $t('Land') }}
                            </label>

                            <select
                                v-model="clubForm.country"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                                required
                            >
                                <option value="DE">{{ $t('Deutschland') }}</option>
                                <option value="AT">{{ $t('Österreich') }}</option>
                                <option value="CH">{{ $t('Schweiz') }}</option>
                                <option value="FR">{{ $t('Frankreich') }}</option>
                                <option value="NL">{{ $t('Niederlande') }}</option>
                                <option value="BE">{{ $t('Belgien') }}</option>
                                <option value="TR">{{ $t('Türkei') }}</option>
                                <option value="US">USA</option>
                            </select>
                            <p v-if="clubForm.errors.country" class="mt-1 text-xs text-error">{{ clubForm.errors.country }}</p>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 2" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ $t('Adresse & Bankkonto') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ $t('Optional: Standort und Bankkonto für Mitglieder-Überweisungen eintragen.') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <input v-model="clubForm.city" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.city ? 'border-error' : ''" :placeholder="$t('Stadt')">
                                <p v-if="clubForm.errors.city" class="mt-1 text-xs text-error">{{ clubForm.errors.city }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.postal_code" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.postal_code ? 'border-error' : ''" :placeholder="$t('PLZ')">
                                <p v-if="clubForm.errors.postal_code" class="mt-1 text-xs text-error">{{ clubForm.errors.postal_code }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.state" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.state ? 'border-error' : ''" :placeholder="$t('Region')">
                                <p v-if="clubForm.errors.state" class="mt-1 text-xs text-error">{{ clubForm.errors.state }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.street" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.street ? 'border-error' : ''" :placeholder="$t('Straße')">
                                <p v-if="clubForm.errors.street" class="mt-1 text-xs text-error">{{ clubForm.errors.street }}</p>
                            </div>
                            <div>
                                <input v-model="clubForm.house_number" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.house_number ? 'border-error' : ''" :placeholder="$t('Hausnummer')">
                                <p v-if="clubForm.errors.house_number" class="mt-1 text-xs text-error">{{ clubForm.errors.house_number }}</p>
                            </div>
                            <div class="rounded-lg border border-border bg-card p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('Bankkonto für Vereinsrechnungen') }}</p>
                                <p class="mt-1 text-xs text-secondary">
                                    {{ $t('Diese Daten werden Mitgliedern angezeigt, wenn sie offene Vereinsrechnungen per Überweisung zahlen.') }}
                                </p>

                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <input v-model="clubForm.sepa_account_holder" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_account_holder ? 'border-error' : ''" :placeholder="$t('Kontoinhaber')">
                                        <p v-if="clubForm.errors.sepa_account_holder" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_account_holder }}</p>
                                    </div>
                                    <div>
                                        <input v-model="clubForm.sepa_iban" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_iban ? 'border-error' : ''" :placeholder="$t('IBAN')">
                                        <p v-if="clubForm.errors.sepa_iban" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_iban }}</p>
                                    </div>
                                    <div>
                                        <input v-model="clubForm.sepa_bic" class="w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary" :class="clubForm.errors.sepa_bic ? 'border-error' : ''" :placeholder="$t('BIC')">
                                        <p v-if="clubForm.errors.sepa_bic" class="mt-1 text-xs text-error">{{ clubForm.errors.sepa_bic }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="clubCreateStep === 3" class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-primary">
                                {{ $t('Prüfen') }}
                            </h3>

                            <p class="mt-1 text-sm text-secondary">
                                {{ $t('Kontrolliere die Angaben vor dem Absenden. Der Verein wird als Antrag gespeichert.') }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-inputBg p-4">
                            <div class="space-y-3 text-sm">
                                <p><strong>{{ $t('Verein:') }}</strong> {{ clubForm.name || '-' }}</p>
                                <p><strong>{{ $t('Sportart:') }}</strong> {{ sportLabel(clubForm.sport_type) }}</p>
                                <p><strong>{{ $t('Offizielle Prüfung:') }}</strong> {{ clubForm.is_official ? $t('Beantragt') : $t('Nicht beantragt') }}</p>
                                <p v-if="clubForm.is_official"><strong>{{ $t('Vereinsnummer zur Prüfung:') }}</strong> {{ clubForm.official_club_number || '-' }}</p>
                                <p><strong>{{ $t('Status nach Absenden:') }}</strong> {{ $t('Wartet auf Prüfung') }}</p>
                                <p><strong>{{ $t('Land:') }}</strong> {{ clubForm.country || '-' }}</p>
                                <p>
                                    <strong>{{ $t('Adresse:') }}</strong>
                                    {{ clubForm.street || '-' }}
                                    {{ clubForm.house_number || '' }},
                                    {{ clubForm.postal_code || '' }}
                                    {{ clubForm.city || '' }}
                                </p>
                                <p><strong>{{ $t('Region:') }}</strong> {{ clubForm.state || '-' }}</p>
                                <p><strong>{{ $t('Kontoinhaber:') }}</strong> {{ clubForm.sepa_account_holder || '-' }}</p>
                                <p><strong>{{ $t('IBAN') }}:</strong> {{ clubForm.sepa_iban || '-' }}</p>
                                <p><strong>{{ $t('BIC') }}:</strong> {{ clubForm.sepa_bic || '-' }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="shrink-0 border-t border-border bg-card p-4">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-4 py-3 font-semibold text-secondary hover:border-borderHover hover:text-primary disabled:opacity-50"
                            :disabled="clubCreateStep === 1"
                            @click="$emit('previous-step')"
                        >
                            {{ $t('Zurück') }}
                        </button>

                        <button
                            v-if="clubCreateStep < clubCreateSteps.length"
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                            @click="$emit('next-step')"
                        >
                            {{ $t('Weiter') }}
                        </button>

                        <button
                            v-else
                            type="button"
                            class="flex-1 rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="clubForm.processing"
                            @click="$emit('create')"
                        >
                            {{ clubForm.processing ? $t('Speichert...') : $t('Speichern') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
