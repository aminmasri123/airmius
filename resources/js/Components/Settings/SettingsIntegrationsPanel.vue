<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    connectedAccountFor: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatDistance: { type: Function, required: true },
    formatDuration: { type: Function, required: true },
    formatProvider: { type: Function, required: true },
    integrationStatusLabel: { type: Function, required: true },
    manualActivityForm: { type: Object, required: true },
    manualActivityTypeLabel: { type: Function, required: true },
    manualActivityTypeOptions: { type: Array, default: () => [] },
    openDisconnectIntegrationModal: { type: Function, required: true },
    openSportActivityDeleteModal: { type: Function, required: true },
    openSportActivityEditModal: { type: Function, required: true },
    setManualActivityImage: { type: Function, required: true },
    settingsText: { type: Function, required: true },
    socialAccountFor: { type: Function, required: true },
    sportActivitySubtitle: { type: Function, required: true },
    sportActivityTime: { type: Function, required: true },
    sportActivityTitle: { type: Function, required: true },
    sportIntegrationProviderDescription: { type: Function, required: true },
    sportIntegrations: {
        type: Object,
        default: () => ({ providers: {}, accounts: [], activities: [] }),
    },
    storeManualActivity: { type: Function, required: true },
    syncIntegration: { type: Function, required: true },
})

const manualActivityImageInput = ref(null)

watch(
    () => props.manualActivityForm.image,
    (image) => {
        if (!image && manualActivityImageInput.value) {
            manualActivityImageInput.value.value = ''
        }
    },
)
</script>

<template>
    <div class="space-y-5">
        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.login_title', 'Login-Verknüpfungen') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ settingsText('integrations.login_description', 'Nutze Google oder Outlook für eine schnelle Anmeldung.') }}
            </p>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div
                    class="rounded-lg border p-4 transition"
                    :class="socialAccountFor('google') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-primary">Google</p>
                            <p class="text-sm text-secondary">
                                {{ socialAccountFor('google')?.email || settingsText('integrations.not_connected', 'Noch nicht verbunden') }}
                            </p>
                        </div>
                        <span
                            v-if="socialAccountFor('google')"
                            class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                        >
                            {{ settingsText('integrations.connected', 'Verbunden') }}
                        </span>
                        <a v-else :href="route('social-auth.redirect', 'google')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ settingsText('integrations.connect', 'Verbinden') }}
                        </a>
                    </div>
                </div>

                <div
                    class="rounded-lg border p-4 transition"
                    :class="socialAccountFor('microsoft') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-primary">Outlook / Microsoft</p>
                            <p class="text-sm text-secondary">
                                {{ socialAccountFor('microsoft')?.email || settingsText('integrations.not_connected', 'Noch nicht verbunden') }}
                            </p>
                        </div>
                        <span
                            v-if="socialAccountFor('microsoft')"
                            class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                        >
                            {{ settingsText('integrations.connected', 'Verbunden') }}
                        </span>
                        <a v-else :href="route('social-auth.redirect', 'microsoft')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ settingsText('integrations.connect', 'Verbinden') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.sport_apps_title', 'Sportprogramme synchronisieren') }}</h2>
            <p class="mt-1 text-sm text-secondary">
                {{ settingsText('integrations.sport_apps_description', 'Verknüpfe Sport-Apps, damit Trainingsdaten sicher in dein Airmius Profil synchronisiert werden.') }}
            </p>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <article
                    v-for="(provider, key) in sportIntegrations.providers"
                    :key="key"
                    class="rounded-lg border p-4 transition"
                    :class="connectedAccountFor(key) ? 'border-success/40 bg-bg' : 'border-border bg-bg'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-primary">{{ provider.label }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ sportIntegrationProviderDescription(key, provider) }}</p>
                        </div>
                        <span
                            v-if="connectedAccountFor(key)"
                            class="shrink-0 whitespace-nowrap rounded-full border border-success/40 bg-success/15 px-2.5 py-1 text-xs font-semibold text-success"
                        >
                            {{ integrationStatusLabel(connectedAccountFor(key).status) }}
                        </span>
                    </div>

                    <p v-if="connectedAccountFor(key)?.last_synced_at" class="mt-3 text-xs text-secondary">
                        {{ settingsText('integrations.last_synced', 'Zuletzt synchronisiert: {date}', { date: formatDate(connectedAccountFor(key).last_synced_at) }) }}
                    </p>
                    <p v-if="connectedAccountFor(key)?.sync_summary?.message" class="mt-2 text-xs text-secondary">
                        {{ connectedAccountFor(key).sync_summary.message }}
                    </p>
                    <dl v-if="connectedAccountFor(key)?.sync_summary?.google_status || connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="mt-2 space-y-1 text-xs text-secondary">
                        <div v-if="connectedAccountFor(key)?.sync_summary?.google_status" class="flex gap-2">
                            <dt>{{ settingsText('integrations.google_status', 'Google Status:') }}</dt>
                            <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_status }}</dd>
                        </div>
                        <div v-if="connectedAccountFor(key)?.sync_summary?.google_error" class="flex gap-2">
                            <dt>{{ settingsText('integrations.google_error', 'Google Fehler:') }}</dt>
                            <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_error }}</dd>
                        </div>
                        <div v-if="connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="flex gap-2">
                            <dt>{{ settingsText('integrations.day_ranges', 'Tagesbereiche:') }}</dt>
                            <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.bucket_count }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            v-if="!connectedAccountFor(key)"
                            :href="route('auth.sport-integrations.connect', provider.route_key || key)"
                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                        >
                            {{ provider.status === 'live_oauth' ? settingsText('integrations.connect', 'Verbinden') : settingsText('integrations.request', 'Vormerken') }}
                        </a>
                        <button
                            v-if="connectedAccountFor(key)"
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary"
                            @click="syncIntegration(connectedAccountFor(key))"
                        >
                            {{ settingsText('integrations.check_sync', 'Sync prüfen') }}
                        </button>
                        <button
                            v-if="connectedAccountFor(key)"
                            type="button"
                            class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                            @click="openDisconnectIntegrationModal(connectedAccountFor(key))"
                        >
                            {{ settingsText('actions.remove', 'Entfernen') }}
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.activities.title', 'Importierte Aktivitäten') }}</h2>
                <button
                    v-if="sportIntegrations.activities.length"
                    type="button"
                    class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                    @click="openSportActivityDeleteModal()"
                >
                    {{ settingsText('integrations.activities.delete_all', 'Alle löschen') }}
                </button>
            </div>
            <form class="mt-5 rounded-xl border border-border bg-muted/30 p-4" @submit.prevent="storeManualActivity">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ settingsText('integrations.manual_activity.title', 'Manuell eintragen') }}</h3>
                        <p class="mt-1 text-sm text-secondary">
                            {{ settingsText('integrations.manual_activity.description', 'Füge eigene Trainingseinheiten hinzu, auch wenn keine Sport-App verbunden ist.') }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="manualActivityForm.processing"
                    >
                        {{ settingsText('integrations.manual_activity.save', 'Training speichern') }}
                    </button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.name', 'Name') }}
                        <input
                            v-model="manualActivityForm.title"
                            type="text"
                            maxlength="120"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            :placeholder="settingsText('integrations.manual_activity.name_placeholder', 'z. B. Lauftraining')"
                            required
                        />
                        <span v-if="manualActivityForm.errors.title" class="mt-1 block text-xs text-danger">
                            {{ manualActivityForm.errors.title }}
                        </span>
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.sport_type', 'Sportart') }}
                        <select
                            v-model="manualActivityForm.activity_type"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                        >
                            <option v-for="type in manualActivityTypeOptions" :key="type" :value="type">
                                {{ manualActivityTypeLabel(type) }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.date_time', 'Datum und Zeit') }}
                        <input
                            v-model="manualActivityForm.started_at"
                            type="datetime-local"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            required
                        />
                        <span v-if="manualActivityForm.errors.started_at" class="mt-1 block text-xs text-danger">
                            {{ manualActivityForm.errors.started_at }}
                        </span>
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.image', 'Bild') }}
                        <input
                            ref="manualActivityImageInput"
                            type="file"
                            accept="image/*"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                            @change="setManualActivityImage"
                        />
                        <span v-if="manualActivityForm.errors.image" class="mt-1 block text-xs text-danger">
                            {{ manualActivityForm.errors.image }}
                        </span>
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.duration_minutes', 'Dauer in Minuten') }}
                        <input
                            v-model="manualActivityForm.duration_minutes"
                            type="number"
                            min="0"
                            max="14400"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            placeholder="60"
                        />
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.distance_km', 'Distanz in km') }}
                        <input
                            v-model="manualActivityForm.distance_km"
                            type="number"
                            min="0"
                            max="10000"
                            step="0.01"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            placeholder="5,00"
                        />
                    </label>

                    <label class="block text-sm font-semibold text-primary">
                        {{ settingsText('integrations.manual_activity.calories', 'Kalorien') }}
                        <input
                            v-model="manualActivityForm.calories"
                            type="number"
                            min="0"
                            max="200000"
                            class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            placeholder="450"
                        />
                    </label>
                </div>
            </form>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-secondary">
                        <tr>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.image', 'Bild') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.date', 'Datum') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.time', 'Zeit') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.source', 'Quelle') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.sport_type', 'Sportart') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.duration', 'Dauer') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.distance', 'Distanz') }}</th>
                            <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.calories', 'Kalorien') }}</th>
                            <th class="py-2 pr-4 text-right">{{ settingsText('integrations.activities.table.action', 'Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="activity in sportIntegrations.activities" :key="activity.id">
                            <td class="py-3 pr-4">
                                <img
                                    v-if="activity.image_url"
                                    :src="activity.image_url"
                                    alt=""
                                    class="h-12 w-12 rounded-lg border border-border object-cover"
                                />
                                <span v-else class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-border text-xs text-secondary">
                                    -
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-secondary">{{ formatDate(activity.started_at) }}</td>
                            <td class="py-3 pr-4 text-secondary">
                                {{ sportActivityTime(activity) }}
                            </td>
                            <td class="py-3 pr-4 text-secondary">{{ formatProvider(activity.provider) }}</td>
                            <td class="py-3 pr-4">
                                <p class="font-semibold text-primary">{{ sportActivityTitle(activity) }}</p>
                                <p v-if="sportActivitySubtitle(activity)" class="mt-1 text-xs text-secondary">
                                    {{ sportActivitySubtitle(activity) }}
                                </p>
                            </td>
                            <td class="py-3 pr-4 text-secondary">{{ formatDuration(activity.duration_seconds) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ formatDistance(activity.distance_meters) }}</td>
                            <td class="py-3 pr-4 text-secondary">{{ activity.calories || '-' }}</td>
                            <td class="py-3 pr-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                        @click="openSportActivityEditModal(activity)"
                                    >
                                        {{ settingsText('actions.rename', 'Umbenennen') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                        @click="openSportActivityDeleteModal(activity)"
                                    >
                                        {{ settingsText('actions.delete', 'Löschen') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!sportIntegrations.activities.length" class="py-6 text-sm text-secondary">
                    {{ settingsText('integrations.activities.empty', 'Noch keine Aktivitäten importiert.') }}
                </p>
            </div>
        </section>
    </div>
</template>
