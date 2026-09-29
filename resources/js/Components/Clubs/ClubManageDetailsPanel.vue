<script setup>
import CountrySelect from "@/Components/CountrySelect.vue"
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'

defineProps({
    clubForm: { type: Object, required: true },
    clubProfile: { type: Object, required: true },
    viewer: { type: Object, required: true },
})

defineEmits(['update-club-profile'])
</script>

<template>
    <section v-if="viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
        <h2 class="text-lg font-semibold text-primary">{{ $t('Vereinsdaten') }}</h2>
        <p class="mt-1 text-sm text-secondary">
            {{ $t('Offizielle Vereine müssen ihre Vereinsnummer hinterlegen. Nicht-offizielle Gruppen können das Feld leer lassen.') }}
        </p>

        <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="$emit('update-club-profile')">
            <div class="rounded-lg border border-border bg-bg p-4 text-sm md:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-primary">{{ $t('Prüfstatus:') }}</span>
                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                        {{ clubProfile.verification_status === 'pending_verification' ? $t('Wartet auf Prüfung') : clubProfile.verification_status === 'verified' ? $t('Freigegeben') : clubProfile.verification_status === 'rejected' ? $t('Abgelehnt') : clubProfile.verification_status }}
                    </span>
                    <span v-if="clubProfile.is_official" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">
                        {{ $t('Offiziell') }}
                    </span>
                </div>
                <p v-if="clubProfile.official_club_number" class="mt-2 text-secondary">
                    {{ $t('Vereinsnummer:') }} {{ clubProfile.official_club_number }}
                </p>
                <p v-else-if="clubProfile.requested_official_club_number" class="mt-2 text-secondary">
                    {{ $t('Beantragte Vereinsnummer:') }} {{ clubProfile.requested_official_club_number }}
                </p>
                <p v-if="clubProfile.verification_notes" class="mt-2 text-secondary">
                    {{ $t('Hinweis:') }} {{ clubProfile.verification_notes }}
                </p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Vereinsname') }}</label>
                <input v-model="clubForm.name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Sportart') }}</label>
                <input v-model="clubForm.sport_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input v-model="clubForm.is_listed" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    <span class="block font-semibold">{{ $t('Verein auflisten') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Der Verein darf in Vereinslisten und Auswahlfeldern sichtbar sein.') }}</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input v-model="clubForm.teams_are_listed" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    <span class="block font-semibold">{{ $t('Teams auflisten') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Teams dürfen außerhalb des internen Vereinsbereichs sichtbar sein.') }}</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input v-model="clubForm.members_can_post_to_club" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    <span class="block font-semibold">{{ $t('Vereinsbeiträge erlauben') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Normale Mitglieder dürfen Beiträge für den Verein erstellen.') }}</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input v-model="clubForm.members_can_post_to_teams" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                <span>
                    <span class="block font-semibold">{{ $t('Teambeiträge erlauben') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('Normale Teammitglieder dürfen Beiträge für ihre Teams erstellen.') }}</span>
                </span>
            </label>

            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-primary">{{ $t('Vereinsnummer zur Prüfung') }}</label>
                <input
                    v-model="clubForm.official_club_number"
                    class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                    :placeholder="$t('z. B. Vereinsregister- oder Verbandsnummer')"
                >
                <p class="mt-1 text-xs text-secondary">
                    {{ $t('Wenn die Nummer neu oder geändert ist, wird sie zur Admin-Prüfung vorgemerkt.') }}
                </p>
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Land') }}</label>
                <CountrySelect v-model="clubForm.country" label="Land" required />
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Stadt') }}</label>
                <input v-model="clubForm.city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('PLZ') }}</label>
                <input v-model="clubForm.postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Region') }}</label>
                <input v-model="clubForm.state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Straße') }}</label>
                <input v-model="clubForm.street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div>
                <label class="text-sm font-semibold text-primary">{{ $t('Hausnummer') }}</label>
                <input v-model="clubForm.house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            </div>

            <div class="md:col-span-2">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <AppButton type="submit" :loading="clubForm.processing" :disabled="clubForm.processing">
                        {{ clubForm.processing ? $t('Speichert...') : $t('Vereinsdaten speichern') }}
                    </AppButton>
                    <AppLoadingState v-if="clubForm.processing" :label="$t('Vereinsdaten werden gespeichert...')" inline />
                </div>
            </div>
        </form>
    </section>
</template>
