<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    canSubmit: { type: Boolean, default: false },
    form: { type: Object, required: true },
    people: { type: Array, default: () => [] },
    privatePeople: { type: Array, default: () => [] },
    selectedTeamMembers: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    setPlanTargetType: { type: Function, required: true },
    setPlanTeamMode: { type: Function, required: true },
    togglePlanUser: { type: Function, required: true },
})

const emit = defineEmits(['submit'])
const { t } = useI18n()
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Planname') }}
                <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Rhythmus') }}
                <select v-model="form.cadence" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="single">{{ $t('training_workspace.cadence.single') }}</option>
                    <option value="daily">{{ $t('training_workspace.cadence.daily') }}</option>
                    <option value="weekly">{{ $t('training_workspace.cadence.weekly') }}</option>
                    <option value="monthly">{{ $t('training_workspace.cadence.monthly') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Status') }}
                <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="published">{{ $t('Freigegeben') }}</option>
                    <option value="draft">{{ $t('Entwurf') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Berechtigung') }}
                <select v-model="form.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="read">{{ $t('training_workspace.permission.read') }}</option>
                    <option value="write">{{ $t('Mit schreiben / verbessern') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Start') }}
                <input v-model="form.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Ende') }}
                <input v-model="form.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Ziel des Plans') }}
                <input v-model="form.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Trainingsphase') }}
                <select v-model="form.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="base">{{ $t('Grundlage') }}</option>
                    <option value="build">{{ $t('Aufbau') }}</option>
                    <option value="peak">{{ $t('Peak / Wettkampfnähe') }}</option>
                    <option value="recovery">{{ $t('Regeneration') }}</option>
                    <option value="rehab">{{ $t('Reha / Wiedereinstieg') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Niveau') }}
                <select v-model="form.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="beginner">{{ $t('Einsteiger') }}</option>
                    <option value="intermediate">{{ $t('Fortgeschritten') }}</option>
                    <option value="advanced">{{ $t('Advanced') }}</option>
                    <option value="elite">{{ $t('Leistung') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Wochen') }}
                <input v-model="form.weeks" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Einheiten pro Woche') }}
                <input v-model="form.weekly_sessions" type="number" min="1" max="21" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Makrozyklus') }}
                <input v-model="form.macrocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Mesozyklus') }}
                <input v-model="form.mesocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Deload-Woche') }}
                <input v-model="form.deload_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">{{ $t('Wettkampf / Zieltermin') }}
                <input v-model="form.competition_date" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary md:col-span-2">{{ $t('Beschreibung') }}
                <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
        </div>
        <div class="rounded-2xl border border-border bg-inputBg/40 p-4">
            <p class="text-sm font-semibold text-primary">{{ t('training_workspace.plan_audience.title') }}</p>
            <p class="mt-1 text-xs text-secondary">{{ t('training_workspace.plan_audience.intro') }}</p>

            <div class="mt-3 grid gap-2 md:grid-cols-3">
                <button type="button" class="rounded-xl border p-3 text-start transition" :class="form.target_type === 'self' ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-bg/40 text-primary hover:bg-muted'" @click="setPlanTargetType('self', form)">
                    <i class="las la-user text-lg"></i>
                    <span class="mt-1 block text-sm font-semibold">{{ t('training_workspace.plan_audience.self_title') }}</span>
                    <span class="mt-1 block text-xs text-secondary">{{ t('training_workspace.plan_audience.self_hint') }}</span>
                </button>
                <button type="button" class="rounded-xl border p-3 text-start transition" :class="form.target_type === 'private' ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-bg/40 text-primary hover:bg-muted'" @click="setPlanTargetType('private', form)">
                    <i class="las la-user-friends text-lg"></i>
                    <span class="mt-1 block text-sm font-semibold">{{ t('training_workspace.plan_audience.private_title') }}</span>
                    <span class="mt-1 block text-xs text-secondary">{{ t('training_workspace.plan_audience.private_hint') }}</span>
                </button>
                <button type="button" class="rounded-xl border p-3 text-start transition" :class="form.target_type === 'team' ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-bg/40 text-primary hover:bg-muted'" @click="setPlanTargetType('team', form)">
                    <i class="las la-users text-lg"></i>
                    <span class="mt-1 block text-sm font-semibold">{{ t('training_workspace.plan_audience.team_title') }}</span>
                    <span class="mt-1 block text-xs text-secondary">{{ t('training_workspace.plan_audience.team_hint') }}</span>
                </button>
            </div>

            <div v-if="form.target_type === 'self'" class="mt-4 rounded-xl border border-air-blue/20 bg-air-blue/5 p-3 text-sm text-secondary">
                {{ t('training_workspace.plan_audience.self_notice') }}
            </div>

            <template v-else-if="form.target_type === 'private'">
                <div class="mt-4 flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-primary">{{ t('training_workspace.plan_audience.private_recipients') }}</p>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ t('training_workspace.plan_audience.selected', { count: form.user_ids.length }) }}</span>
                </div>
                <div class="mt-3 grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2">
                    <label v-for="person in privatePeople" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border bg-bg/40 px-3 py-2 text-sm text-primary">
                        <input type="checkbox" class="rounded border-border bg-inputBg" :checked="form.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id, form)" />
                        <span class="truncate">{{ person.name }}</span>
                    </label>
                    <p v-if="!privatePeople.length" class="text-sm text-secondary">{{ t('training_workspace.plan_audience.private_empty') }}</p>
                </div>
            </template>

            <template v-else>
                <label class="mt-4 block text-sm font-semibold text-primary">{{ t('training_workspace.plan_audience.team_label') }}
                    <select v-model="form.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" @change="form.user_ids = []; form.team_mode = 'all'">
                        <option value="">{{ t('training_workspace.plan_audience.team_choose') }}</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </label>

                <div v-if="form.team_id" class="mt-4 grid gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-xl border p-3 text-start transition" :class="form.team_mode === 'all' ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-bg/40 text-primary hover:bg-muted'" @click="setPlanTeamMode('all', form)">
                        <span class="block text-sm font-semibold">{{ t('training_workspace.plan_audience.all_title') }}</span>
                        <span class="mt-1 block text-xs text-secondary">{{ t('training_workspace.plan_audience.all_hint') }}</span>
                    </button>
                    <button type="button" class="rounded-xl border p-3 text-start transition" :class="form.team_mode === 'individual' ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-bg/40 text-primary hover:bg-muted'" @click="setPlanTeamMode('individual', form)">
                        <span class="block text-sm font-semibold">{{ t('training_workspace.plan_audience.individual_title') }}</span>
                        <span class="mt-1 block text-xs text-secondary">{{ t('training_workspace.plan_audience.individual_hint') }}</span>
                    </button>
                </div>

                <div v-if="form.team_id && form.team_mode === 'individual'" class="mt-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-primary">{{ t('training_workspace.plan_audience.team_members') }}</p>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ t('training_workspace.plan_audience.selected', { count: form.user_ids.length }) }}</span>
                    </div>
                    <div class="mt-3 grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2">
                        <label v-for="person in selectedTeamMembers" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border bg-bg/40 px-3 py-2 text-sm text-primary">
                            <input type="checkbox" class="rounded border-border bg-inputBg" :checked="form.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id, form)" />
                            <span class="truncate">{{ person.name }}</span>
                        </label>
                        <p v-if="!selectedTeamMembers.length" class="text-sm text-secondary">{{ t('training_workspace.plan_audience.team_members_empty') }}</p>
                    </div>
                </div>
                <p v-else-if="!form.team_id" class="mt-3 rounded-xl border border-dashed border-border p-3 text-sm text-secondary">{{ t('training_workspace.plan_audience.choose_team_first') }}</p>
                <p v-else class="mt-3 text-xs text-secondary">{{ t('training_workspace.plan_audience.team_selected', { count: selectedTeamMembers.length }) }}</p>
            </template>
        </div>
        <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing || !canSubmit">
            {{ $t('Änderungen speichern') }}
        </button>
    </form>
</template>
