<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    providerProfile: { type: Object, default: null },
    providerProfileForm: { type: Object, required: true },
    providerLocationForm: { type: Object, required: true },
    editingProviderLocation: { type: Object, default: null },
    providerLocations: { type: Array, default: () => [] },
})

const emit = defineEmits([
    'store-provider-profile',
    'save-provider-location',
    'reset-provider-location-form',
    'edit-provider-location',
    'destroy-provider-location',
])
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <article class="surface-card p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ t('commerce.ui.marketplace_provider') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">Sitzadresse und öffentliche Anbieterangaben</h2>
                    <p class="mt-1 text-sm text-secondary">
                        Diese Daten steuern, wie Airmius als Anbieter im Marketplace sichtbar ist. Die Sitzadresse wird nur öffentlich angezeigt, wenn du sie freigibst.
                    </p>
                </div>
                <span class="rounded-full border border-border bg-bg px-3 py-1 text-xs font-semibold text-secondary">
                    {{ providerProfile?.status || 'draft' }}
                </span>
            </div>

            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="emit('store-provider-profile')">
                <label class="grid gap-1 text-sm font-semibold text-primary md:col-span-2">
                    Anzeigename im Marketplace
                    <input v-model="providerProfileForm.display_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Airmius Shop">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Anbieterart
                    <select v-model="providerProfileForm.provider_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="private">Privatperson</option>
                        <option value="business">Unternehmen / Shop</option>
                        <option value="club">Verein</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Rechtlicher Name
                    <input v-model="providerProfileForm.legal_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Firma, Verein oder Vor- und Nachname">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Support-E-Mail
                    <input v-model="providerProfileForm.support_email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="support@example.com">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Telefon
                    <input v-model="providerProfileForm.phone" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="+49 ...">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Website
                    <input v-model="providerProfileForm.website" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.url_placeholder')">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary">
                    Logo-URL
                    <input v-model="providerProfileForm.logo_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.logo_url_placeholder')">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-primary md:col-span-2">
                    Öffentliche Beschreibung
                    <textarea v-model="providerProfileForm.public_description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurz erklären, was Kunden bei dir kaufen, abholen oder buchen können."></textarea>
                </label>

                <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
                    <h3 class="font-semibold text-primary">Sitzadresse</h3>
                    <p class="mt-1 text-xs text-secondary">Die Sitzadresse ist deine rechtliche Anbieteradresse. Sie ist unabhängig von Filialen, Boutiquen und Abholstationen.</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-6">
                        <input v-model="providerProfileForm.legal_country" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" placeholder="DE" maxlength="2">
                        <input v-model="providerProfileForm.legal_postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="PLZ">
                        <input v-model="providerProfileForm.legal_city" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-3" placeholder="Stadt">
                        <input v-model="providerProfileForm.legal_street" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-4" placeholder="Straße">
                        <input v-model="providerProfileForm.legal_house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Hausnummer">
                        <input v-model="providerProfileForm.legal_state" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-6" placeholder="Bundesland / Region">
                    </div>
                </div>

                <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-4 md:grid-cols-3">
                    <label class="flex items-start gap-3 text-sm text-secondary">
                        <input v-model="providerProfileForm.show_public_address" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span><strong class="block text-primary">Sitzadresse anzeigen</strong>Nur aktivieren, wenn Kunden diese Adresse sehen sollen.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-secondary">
                        <input v-model="providerProfileForm.show_support_email" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span><strong class="block text-primary">E-Mail anzeigen</strong>Für Rückfragen im Marketplace sichtbar.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-secondary">
                        <input v-model="providerProfileForm.show_phone" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span><strong class="block text-primary">Telefon anzeigen</strong>Optional für lokale Shops oder Abholung.</span>
                    </label>
                </div>

                <button class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary md:col-span-2" :disabled="providerProfileForm.processing">
                    Anbieterprofil speichern
                </button>
            </form>
        </article>

        <aside class="space-y-6">
            <article class="surface-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Filialen</p>
                <h2 class="mt-1 text-lg font-bold text-primary">Boutique, Filiale oder Abholstation</h2>
                <p class="mt-1 text-sm text-secondary">Öffentliche Standorte können Kunden auf Anbieter- und Produktseiten sehen.</p>

                <form class="mt-4 grid gap-3" @submit.prevent="emit('save-provider-location')">
                    <input v-model="providerLocationForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Name, z. B. Airmius Store Saarbrücken">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select v-model="providerLocationForm.type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="pickup">Abholstation</option>
                            <option value="boutique">Boutique</option>
                            <option value="branch">Filiale</option>
                            <option value="warehouse">Lager</option>
                            <option value="partner">Partnerstandort</option>
                        </select>
                        <input v-model="providerLocationForm.country" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="DE" maxlength="2">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="providerLocationForm.postal_code" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="PLZ">
                        <input v-model="providerLocationForm.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Stadt">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                        <input v-model="providerLocationForm.street" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Straße">
                        <input v-model="providerLocationForm.house_number" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Nr.">
                    </div>
                    <textarea v-model="providerLocationForm.opening_hours" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Öffnungszeiten, z. B. Mo-Fr 10-18 Uhr"></textarea>
                    <textarea v-model="providerLocationForm.note" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Hinweis für Kunden, z. B. Abholung am Empfang"></textarea>
                    <input v-model="providerLocationForm.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="providerLocationForm.phone" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Telefon optional">
                        <input v-model="providerLocationForm.email" type="email" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="E-Mail optional">
                    </div>
                    <div class="grid gap-2 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <label class="flex items-center gap-2"><input v-model="providerLocationForm.is_public" type="checkbox" class="rounded border-border bg-inputBg"> Öffentlich anzeigen</label>
                        <label class="flex items-center gap-2"><input v-model="providerLocationForm.pickup_enabled" type="checkbox" class="rounded border-border bg-inputBg"> Abholung möglich</label>
                        <label class="flex items-center gap-2"><input v-model="providerLocationForm.returns_enabled" type="checkbox" class="rounded border-border bg-inputBg"> Rückgabe möglich</label>
                    </div>
                    <div v-if="Object.keys(providerLocationForm.errors || {}).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                        Bitte prüfe die Angaben zum Standort.
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="providerLocationForm.processing">
                            {{ editingProviderLocation ? 'Standort aktualisieren' : 'Standort hinzufügen' }}
                        </button>
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('reset-provider-location-form')">
                            Zurücksetzen
                        </button>
                    </div>
                </form>
            </article>

            <article class="surface-card p-5">
                <h2 class="text-lg font-bold text-primary">Gespeicherte Standorte</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="location in providerLocations" :key="location.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-primary">{{ location.name }}</h3>
                                <p class="mt-1 text-sm text-secondary">{{ location.address }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ location.type }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                            <span v-if="location.is_public" class="rounded-full bg-success/10 px-2 py-1 text-success">Öffentlich</span>
                            <span v-if="location.pickup_enabled" class="rounded-full bg-air-blue/10 px-2 py-1 text-air-blue">Abholung</span>
                            <span v-if="location.returns_enabled" class="rounded-full bg-warning/10 px-2 py-1 text-warning">Rückgabe</span>
                        </div>
                        <p v-if="location.opening_hours" class="mt-3 text-sm text-secondary">{{ location.opening_hours }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('edit-provider-location', location)">Bearbeiten</button>
                            <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger" @click="emit('destroy-provider-location', location)">Löschen</button>
                        </div>
                    </div>
                    <p v-if="!providerLocations.length" class="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                        Noch keine Filiale, Boutique oder Abholstation gespeichert.
                    </p>
                </div>
            </article>
        </aside>
    </section>
</template>



