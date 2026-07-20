<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    club: { type: Object, required: true },
    user: { type: Object, default: null },
    teamRoles: { type: Array, default: () => [] },
    inviteNotices: { type: Object, default: () => ({}) },
    joinRequestNotices: { type: Object, default: () => ({}) },
    processingJoinTeamIds: { type: Object, default: () => new Set() },
    processingJoinRequestIds: { type: Object, default: () => new Set() },
    teamInsights: { type: Object, default: () => ({}) },
    teamInsightsLoading: { type: Object, default: () => new Set() },
    initials: { type: Function, required: true },
    teamRoleLabel: { type: Function, required: true },
    updateTeamMemberRole: { type: Function, required: true },
    removeTeamMember: { type: Function, required: true },
    deleteTeam: { type: Function, required: true },
    requestJoinTeam: { type: Function, required: true },
    approveJoinRequest: { type: Function, required: true },
    declineJoinRequest: { type: Function, required: true },
    inviteFormFor: { type: Function, required: true },
    teamMemberFormFor: { type: Function, required: true },
    availableTeamMemberOptions: { type: Function, required: true },
    loadTeamInsights: { type: Function, required: true },
    inviteUser: { type: Function, required: true },
    addTeamMember: { type: Function, required: true },
})

const { locale } = useI18n()

const copy = {
    de: {
        open: 'Team-Alltag',
        loading: 'Wird geladen...',
        nextEvent: 'Nächstes Event',
        noEvent: 'Kein Event geplant',
        response: 'Rückmeldung',
        attendance: 'Zusagequote',
        missing: 'Fehlend',
        actions: 'Nächste Aktionen',
        reliability: 'Verlässlichkeit',
        noMissing: 'Alle haben geantwortet.',
        error: 'Team-Alltag konnte nicht geladen werden.',
        actionLabels: {
            invite_members: 'Mitglieder einladen',
            assign_coach: 'Trainerrolle vergeben',
            schedule_event: 'Termin planen',
            remind_missing_responses: 'Fehlende Rückmeldungen erinnern',
            check_availability: 'Verfügbarkeit prüfen',
            review_open_fees: 'Offene Beiträge prüfen',
            team_routine_stable: 'Teamroutine ist stabil',
        },
    },
    en: {
        open: 'Team routine',
        loading: 'Loading...',
        nextEvent: 'Next event',
        noEvent: 'No event planned',
        response: 'Responses',
        attendance: 'Attendance',
        missing: 'Missing',
        actions: 'Next actions',
        reliability: 'Reliability',
        noMissing: 'Everyone has responded.',
        error: 'Team routine could not be loaded.',
        actionLabels: {
            invite_members: 'Invite members',
            assign_coach: 'Assign coach role',
            schedule_event: 'Schedule an event',
            remind_missing_responses: 'Remind missing responses',
            check_availability: 'Check availability',
            review_open_fees: 'Review open fees',
            team_routine_stable: 'Team routine is stable',
        },
    },
    fr: {
        open: 'Routine équipe',
        loading: 'Chargement...',
        nextEvent: 'Prochain événement',
        noEvent: 'Aucun événement prévu',
        response: 'Réponses',
        attendance: 'Présence',
        missing: 'Manquants',
        actions: 'Actions suivantes',
        reliability: 'Fiabilité',
        noMissing: 'Tout le monde a répondu.',
        error: "La routine d'équipe n'a pas pu être chargée.",
        actionLabels: {
            invite_members: 'Inviter des membres',
            assign_coach: 'Attribuer le rôle coach',
            schedule_event: 'Planifier un événement',
            remind_missing_responses: 'Relancer les réponses manquantes',
            check_availability: 'Vérifier la disponibilité',
            review_open_fees: 'Vérifier les cotisations ouvertes',
            team_routine_stable: "La routine d'équipe est stable",
        },
    },
    ar: {
        open: 'ر�^ت�S�? ا�"فر�S�,',
        loading: 'جار ا�"تح�.�S�"...',
        nextEvent: 'ا�"�.�^عد ا�"�,اد�.',
        noEvent: '�"ا �S�^جد �.�^عد �.خطط',
        response: 'ا�"رد�^د',
        attendance: 'ا�"حض�^ر',
        missing: '�?ا�,ص',
        actions: 'ا�"إجراءات ا�"تا�"�Sة',
        reliability: 'ا�"ا�"تزا�.',
        noMissing: 'ا�"ج�.�Sع أجاب.',
        error: 'تعذر تح�.�S�" ر�^ت�S�? ا�"فر�S�,.',
        actionLabels: {
            invite_members: 'دع�^ة أعضاء',
            assign_coach: 'تع�S�S�? د�^ر ا�"�.درب',
            schedule_event: 'تخط�Sط �.�^عد',
            remind_missing_responses: 'تذ�f�Sر ا�"رد�^د ا�"�?ا�,صة',
            check_availability: 'فحص ا�"ت�^فر',
            review_open_fees: '�.راجعة ا�"رس�^�. ا�"�.فت�^حة',
            team_routine_stable: 'ر�^ت�S�? ا�"فر�S�, �.ست�,ر',
        },
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const insightFor = (team) => props.teamInsights[team.id] || null
const isLoadingInsights = (team) => props.teamInsightsLoading.has(team.id)
const percent = (value) => `${Number(value || 0).toLocaleString(locale.value)}%`
const actionLabel = (key) => labels.value.actionLabels[key] || key
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="team in club.teams"
            :key="team.id"
            class="space-y-4 rounded-xl border border-border bg-bg p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                        {{ initials(team.name) }}
                    </div>

                    <div class="min-w-0">
                        <Link
                            :href="route('auth.teams.show', team.id)"
                            class="font-semibold text-primary hover:underline"
                        >
                            {{ team.name }}
                        </Link>

                        <p class="text-xs text-secondary">
                            {{ team.users?.length || 0 }} Mitglieder
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                        Aktiv
                    </span>

                    <button
                        v-if="team.can_delete"
                        type="button"
                        class="rounded bg-error px-2 py-1 text-xs font-semibold text-white hover:opacity-90"
                        @click="deleteTeam(team)"
                    >
                        Löschen
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="member in team.users"
                    :key="member.id"
                    class="flex items-center gap-3 rounded-lg p-2 hover:bg-muted"
                >
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {{ initials(member.name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-primary">
                            {{ member.name }}
                        </p>

                        <p class="text-xs text-secondary">
                            {{ member.pivot.role }}
                        </p>
                    </div>

                    <select
                        v-if="team.can_manage"
                        v-model="member.pivot.role"
                        class="rounded border border-border bg-card px-2 py-1 text-xs text-primary"
                        @change="updateTeamMemberRole(team, member)"
                    >
                        <option
                            v-for="role in teamRoles"
                            :key="role"
                            :value="role"
                        >
                            {{ teamRoleLabel(role) }}
                        </option>
                    </select>

                    <span
                        v-else
                        class="rounded-full bg-muted px-2 py-1 text-xs text-secondary"
                    >
                        {{ teamRoleLabel(member.pivot.role) }}
                    </span>

                    <button
                        v-if="member.id === user?.id || team.can_remove_members"
                        type="button"
                        class="rounded border border-border px-2 py-1 text-xs font-semibold text-primary hover:border-error/40 hover:bg-error/10 hover:text-error"
                        @click="removeTeamMember(team, member)"
                    >
                        {{ member.id === user?.id ? 'Team verlassen' : 'Entfernen' }}
                    </button>
                </div>
            </div>

            <form
                v-if="team.can_manage"
                class="grid gap-2 border-t border-border pt-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
                @submit.prevent="addTeamMember(team)"
            >
                <select
                    v-model="teamMemberFormFor(team).user_id"
                    class="min-w-0 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                >
                    <option value="">Vereinsmitglied wählen</option>
                    <option
                        v-for="member in availableTeamMemberOptions(team)"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }} · {{ member.email }}
                    </option>
                </select>

                <select
                    v-model="teamMemberFormFor(team).role"
                    class="rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                >
                    <option
                        v-for="role in teamRoles"
                        :key="role"
                        :value="role"
                    >
                        {{ teamRoleLabel(role) }}
                    </option>
                </select>

                <button
                    type="submit"
                    class="rounded bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="!teamMemberFormFor(team).user_id"
                >
                    Hinzufügen
                </button>
            </form>

            <section class="rounded-lg border border-border bg-card p-3">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-lg border border-border bg-inputBg px-3 py-2 text-start text-sm font-bold text-primary hover:border-borderHover"
                    :disabled="isLoadingInsights(team)"
                    @click="loadTeamInsights(team)"
                >
                    <span>{{ labels.open }}</span>
                    <span class="text-xs text-secondary">
                        {{ isLoadingInsights(team) ? labels.loading : 'API' }}
                    </span>
                </button>

                <div v-if="insightFor(team)?.error" class="mt-3 rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">
                    {{ insightFor(team).error || labels.error }}
                </div>

                <div v-else-if="insightFor(team)" class="mt-3 space-y-3">
                    <div class="rounded-lg bg-bg p-3">
                        <p class="text-xs font-bold uppercase text-secondary">{{ labels.nextEvent }}</p>
                        <p class="mt-1 font-semibold text-primary">
                            {{ insightFor(team).events?.next?.title || labels.noEvent }}
                        </p>

                        <div v-if="insightFor(team).events?.next?.participation" class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded bg-inputBg p-2">
                                <p class="font-bold text-primary">{{ percent(insightFor(team).events.next.participation.response_rate) }}</p>
                                <p class="text-[11px] text-secondary">{{ labels.response }}</p>
                            </div>
                            <div class="rounded bg-inputBg p-2">
                                <p class="font-bold text-primary">{{ percent(insightFor(team).events.next.participation.attendance_rate) }}</p>
                                <p class="text-[11px] text-secondary">{{ labels.attendance }}</p>
                            </div>
                            <div class="rounded bg-inputBg p-2">
                                <p class="font-bold text-primary">{{ insightFor(team).events.next.participation.missing }}</p>
                                <p class="text-[11px] text-secondary">{{ labels.missing }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs font-bold uppercase text-secondary">{{ labels.actions }}</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="action in insightFor(team).team_actions || []"
                                    :key="action.key"
                                    class="flex items-center justify-between gap-2 rounded bg-inputBg px-2 py-2 text-xs"
                                >
                                    <span class="font-semibold text-primary">{{ actionLabel(action.key) }}</span>
                                    <span v-if="action.count" class="rounded-full bg-muted px-2 py-0.5 text-secondary">{{ action.count }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg bg-bg p-3">
                            <p class="text-xs font-bold uppercase text-secondary">{{ labels.reliability }}</p>
                            <div v-if="insightFor(team).participation?.missing_responses?.length" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="member in insightFor(team).participation.missing_responses.slice(0, 6)"
                                    :key="member.id"
                                    class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary"
                                >
                                    {{ member.name }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-secondary">{{ labels.noMissing }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="team.can_request_join || team.viewer_pending_join_request_id || team.pending_join_requests?.length"
                class="space-y-2 rounded-lg border border-border bg-card p-3"
            >
                <button
                    v-if="team.can_request_join"
                    type="button"
                    class="w-full rounded bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                    :disabled="processingJoinTeamIds.has(team.id)"
                    :class="{ 'opacity-60': processingJoinTeamIds.has(team.id) }"
                    @click="requestJoinTeam(team)"
                >
                    {{ processingJoinTeamIds.has(team.id) ? 'Wird gesendet...' : 'Beitritt anfragen' }}
                </button>

                <p
                    v-if="joinRequestNotices[team.id]"
                    class="rounded border px-3 py-2 text-xs font-semibold"
                    :class="joinRequestNotices[team.id].type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ joinRequestNotices[team.id].message }}
                </p>

                <p
                    v-else-if="team.viewer_pending_join_request_id"
                    class="rounded border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-air-blue"
                >
                    Deine Beitrittsanfrage wartet auf Freigabe.
                </p>

                <div v-if="team.pending_join_requests?.length" class="space-y-2">
                    <p class="text-xs font-semibold uppercase text-secondary">
                        Offene Team-Anfragen
                    </p>

                    <div
                        v-for="request in team.pending_join_requests"
                        :key="request.id"
                        class="flex flex-col gap-2 rounded border border-border bg-bg p-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-primary">
                                {{ request.user?.name || 'Mitglied' }}
                            </p>
                            <p class="truncate text-xs text-secondary">
                                {{ request.user?.email }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded bg-buttonPrimary px-3 py-1.5 text-xs font-semibold text-buttonTextPrimary"
                                :disabled="processingJoinRequestIds.has(request.id)"
                                :class="{ 'opacity-60': processingJoinRequestIds.has(request.id) }"
                                @click="approveJoinRequest(request)"
                            >
                                Annehmen
                            </button>
                            <button
                                type="button"
                                class="rounded border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted"
                                :disabled="processingJoinRequestIds.has(request.id)"
                                :class="{ 'opacity-60': processingJoinRequestIds.has(request.id) }"
                                @click="declineJoinRequest(request)"
                            >
                                Ablehnen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form
                v-if="team.can_manage"
                class="flex flex-col gap-2 sm:flex-row"
                @submit.prevent="inviteUser(team)"
            >
                <input
                    v-model="inviteFormFor(team).email"
                    type="email"
                    placeholder="E-Mail"
                    class="min-w-0 flex-1 rounded border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                >

                <button
                    class="rounded bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary disabled:opacity-50"
                    :disabled="club.subscription_capabilities?.member_invitation_remaining_today === 0"
                >
                    Einladen
                </button>
            </form>

            <p
                v-if="club.subscription_capabilities?.member_invitation_daily_limit"
                class="text-xs text-secondary"
            >
                Free-Limit: {{ club.subscription_capabilities.member_invitation_remaining_today }} von {{ club.subscription_capabilities.member_invitation_daily_limit }} Einladungen heute übrig.
            </p>

            <p
                v-if="inviteNotices[team.id]"
                class="rounded-lg border px-3 py-2 text-xs font-semibold"
                :class="inviteNotices[team.id].type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ inviteNotices[team.id].message }}
            </p>
        </div>
    </div>
</template>
