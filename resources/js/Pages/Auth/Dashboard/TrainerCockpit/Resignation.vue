<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
defineProps({ has_role: Boolean, teams: Array })
const selected = ref(undefined)
const form = useForm({ confirmed: false, team_id: null })
function choose(team) { selected.value = team; form.reset(); form.clearErrors(); form.team_id = team?.id ?? null }
function submit() { form.post(route('auth.trainer-resignation.store'), { onSuccess: () => { selected.value = undefined; form.reset() } }) }
</script>
<template>
<Head title="Trainerrollen verwalten" />
<section class="surface-card mx-auto max-w-2xl space-y-6 p-6">
<h1 class="text-2xl font-bold">Trainerrollen verwalten</h1>
<p>Du kannst bei einzelnen Teams als Trainer zurücktreten. Deine Teammitgliedschaft bleibt als Spieler bestehen. Andere Teams und Rollen bleiben unverändert.</p>
<div v-for="team in teams" :key="team.id" class="rounded-xl border p-4 space-y-3">
<h2 class="font-bold">{{ team.name }}</h2>
<button :disabled="form.processing" class="text-buttonPrimary" @click="choose(team)">Als Trainer dieses Teams zurücktreten</button>
</div>
<div v-if="has_role" class="space-y-3 border-t pt-5">
<h2 class="font-bold">Allgemeine Trainerrolle</h2>
<p>Dein Nutzerkonto bleibt erhalten. Für die Aufgabe der allgemeinen Trainerrolle musst du zuerst deine Trainerzuordnungen zu Teams beenden.</p>
<button :disabled="teams.length > 0 || form.processing" class="text-red-600 disabled:opacity-50" @click="choose(null)">Trainerrolle aufgeben</button>
</div>
<p v-if="!has_role && !teams.length">Du hast keine aktive Trainerrolle oder Trainerzuordnung zu einem Team.</p>
<form v-if="selected !== undefined" class="rounded-xl border p-5 space-y-4" @submit.prevent="submit">
<h2 class="font-bold">{{ selected ? 'Rücktritt bei '+selected.name : 'Trainerrolle aufgeben' }} bestätigen</h2>
<p>{{ selected ? 'Du verlierst die Trainerrechte in diesem Team und bleibst als Spieler Mitglied.' : 'Deine allgemeine Trainerrolle wird entfernt. Offene Traineranträge werden zurückgezogen.' }} Du und die zuständige Verwaltung erhalten eine Benachrichtigung.</p>
<label class="flex gap-3"><input v-model="form.confirmed" type="checkbox" required />Ich möchte diesen Rücktritt bestätigen.</label>
<p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
<div class="flex gap-4"><button :disabled="!form.confirmed || form.processing" class="rounded-lg bg-red-700 p-3 text-white disabled:opacity-50">Verbindlich bestätigen</button><button type="button" :disabled="form.processing" @click="selected = undefined">Abbrechen</button></div>
</form>
</section>
</template>
