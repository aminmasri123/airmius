<script setup>
import CountrySelect from "@/Components/CountrySelect.vue"
defineProps({
    addressNotice: { type: Object, default: null },
    form: { type: Object, required: true },
    saveAddress: { type: Function, required: true },
    settingsText: { type: Function, required: true },
    sports: { type: Array, default: () => [] },
    toggleDefaultSport: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-5">
        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAddress">
            <div class="md:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                    {{ settingsText('address.title', 'Adresse') }}
                </h2>
            </div>

            <div
                v-if="addressNotice"
                class="md:col-span-2 rounded-lg border px-4 py-3 text-sm"
                :class="addressNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ addressNotice.message }}
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">
                    {{ settingsText('address.country', 'Land') }} <span class="text-error">*</span>
                </label>
                <CountrySelect v-model="form.country" required :label="settingsText('address.country', 'Land')" />
                <p v-if="form.errors.country" class="mt-1 text-sm text-error">{{ form.errors.country }}</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.city', 'Stadt') }}</label>
                <input v-model="form.city" class="input" />
                <p v-if="form.errors.city" class="mt-1 text-sm text-error">{{ form.errors.city }}</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.postal_code', 'PLZ') }}</label>
                <input v-model="form.postal_code" class="input" />
                <p v-if="form.errors.postal_code" class="mt-1 text-sm text-error">{{ form.errors.postal_code }}</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.state', 'Bundesland') }}</label>
                <input v-model="form.state" class="input" />
                <p v-if="form.errors.state" class="mt-1 text-sm text-error">{{ form.errors.state }}</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.street', 'Straße') }}</label>
                <input v-model="form.street" class="input" />
                <p v-if="form.errors.street" class="mt-1 text-sm text-error">{{ form.errors.street }}</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.house_number', 'Hausnummer') }}</label>
                <input v-model="form.house_number" class="input" />
                <p v-if="form.errors.house_number" class="mt-1 text-sm text-error">{{ form.errors.house_number }}</p>
            </div>

            <div class="mt-4 border-t border-border pt-5 md:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                    {{ settingsText('address.event_defaults', 'Event-Defaults') }}
                </h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('address.event_defaults_description', 'Diese Werte werden automatisch für deine Eventliste genutzt, solange du dort keine eigenen Filter setzt.') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.event_zone', 'Eventzone') }}</label>
                <div class="mt-1 flex items-center gap-2">
                    <input
                        v-model="form.event_radius_km"
                        class="input"
                        min="1"
                        max="500"
                        type="number"
                    />
                    <span class="text-sm font-semibold text-secondary">km</span>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ settingsText('address.event_zone_help', 'Aktuell adressbasiert über PLZ/Stadt/Vereinsadresse.') }}
                </p>
                <p v-if="form.errors.event_radius_km" class="mt-1 text-sm text-error">{{ form.errors.event_radius_km }}</p>
            </div>

            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-primary">{{ settingsText('address.event_sports', 'Sportarten für Eventvorschläge') }}</label>
                <div class="mt-2 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-border bg-bg p-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="sport in sports"
                        :key="sport.id"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-left text-sm transition"
                        :class="(form.event_default_sport_ids || []).map(Number).includes(Number(sport.id))
                            ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary'
                            : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'"
                        @click="toggleDefaultSport(sport.id)"
                    >
                        <span class="block font-semibold">{{ sport.name }}</span>
                        <span class="text-xs">{{ sport.category || settingsText('address.sport_fallback', 'Sport') }}</span>
                    </button>
                </div>
                <p v-if="form.errors.event_default_sport_ids" class="mt-1 text-sm text-error">{{ form.errors.event_default_sport_ids }}</p>
            </div>

            <div class="md:col-span-2">
                <button class="btn-primary" :disabled="form.processing">
                    {{ settingsText('address.save_button', 'Adresse & Event-Defaults speichern') }}
                </button>
            </div>
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
