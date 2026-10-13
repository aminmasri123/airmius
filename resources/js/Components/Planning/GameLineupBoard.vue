<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({ competitionId: Number, game: Object, roster: Array, eligibleIds: Array, canEdit: Boolean, locale: String })
const emit = defineEmits(['saved'])
const de = computed(() => props.locale === 'de')
const label = (deText, enText) => de.value ? deText : enText
const board = ref(null)
const selected = ref(null)
const entries = ref([])
const busy = ref(false)
const error = ref('')
const sport = computed(() => props.game?.sport_type === 'basketball' ? 'basketball' : 'football')
const maxStarters = computed(() => sport.value === 'basketball' ? 5 : 11)
const eligible = computed(() => props.roster.filter((r) => props.eligibleIds.includes(r.id)))
const available = computed(() => eligible.value.filter((r) => !entries.value.some((e) => e.competition_roster_entry_id === r.id)))
const starters = computed(() => entries.value.filter((e) => e.role === 'starter'))
const name = (entry) => props.roster.find((r) => r.id === entry.competition_roster_entry_id)?.display_name || entry.display_name || ''

watch(() => props.game, (game) => {
    entries.value = (game?.lineup?.entries || []).map((e) => ({
        competition_roster_entry_id: e.competition_roster_entry_id, role: e.role,
        position: e.position || '', shirt_number: e.shirt_number || '', board_x: e.board_x, board_y: e.board_y,
    }))
    selected.value = null
    error.value = ''
}, { immediate: true })

const add = (id) => {
    if (!id || entries.value.some((e) => e.competition_roster_entry_id === Number(id))) return
    entries.value.push({ competition_roster_entry_id: Number(id), role: 'substitute', position: '', shirt_number: '', board_x: null, board_y: null })
    selected.value = Number(id)
}
const place = (entry, x, y) => {
    if (!props.canEdit || !entry || (entry.role !== 'starter' && starters.value.length >= maxStarters.value)) return
    entry.role = 'starter'
    entry.board_x = Math.max(5, Math.min(95, Math.round(x)))
    entry.board_y = Math.max(7, Math.min(93, Math.round(y)))
}
const coordinates = (event) => {
    const rect = board.value.getBoundingClientRect()
    return { x: (event.clientX - rect.left) / rect.width * 100, y: (event.clientY - rect.top) / rect.height * 100 }
}
const boardClick = (event) => {
    if (!selected.value) return
    const entry = entries.value.find((e) => e.competition_roster_entry_id === selected.value)
    const point = coordinates(event)
    place(entry, point.x, point.y)
    selected.value = null
}
const dragEnd = (event, entry) => {
    if (!props.canEdit) return
    const rect = board.value.getBoundingClientRect()
    if (event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom) {
        const point = coordinates(event)
        place(entry, point.x, point.y)
    }
}
const toBench = (entry) => { entry.role = 'substitute'; entry.board_x = null; entry.board_y = null }
const save = async () => {
    if (busy.value || !props.canEdit) return
    error.value = ''
    if (!entries.value.length || entries.value.some((e) => !/^[0-9]{1,3}$/.test(String(e.shirt_number)))) {
        error.value = label('Bitte für jede Person eine Trikotnummer (0–999) eingeben.', 'Enter a shirt number (0–999) for each person.')
        return
    }
    const numbers = entries.value.map((e) => String(Number(e.shirt_number)))
    if (new Set(numbers).size !== numbers.length) {
        error.value = label('Trikotnummern dürfen in diesem Spiel nicht doppelt vorkommen.', 'Shirt numbers must be unique in this game.')
        return
    }
    busy.value = true
    try {
        await window.axios.put(`/api/v1/competitions/${props.competitionId}/games/${props.game.id}/lineup`, { entries: entries.value })
        emit('saved')
    } catch (e) {
        error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : (e.response?.data?.message || label('Speichern fehlgeschlagen.', 'Save failed.'))
    } finally { busy.value = false }
}
</script>

<template>
    <div class="space-y-4 border-t border-border pt-4">
        <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-semibold">{{ game.opponent }} · {{ label('Aufstellung', 'Lineup') }}</h4><span class="text-sm text-secondary">{{ starters.length }}/{{ maxStarters }} {{ label('auf dem Feld', 'on the field') }}</span></div>
        <label v-if="canEdit" class="block text-sm">{{ label('Person hinzufügen', 'Add player') }}<select class="mt-1 block w-full rounded border-border bg-inputBg p-2" :disabled="busy" :value="''" @change="add($event.target.value); $event.target.value = ''"><option value="">{{ label('Aus Kader wählen', 'Choose from roster') }}</option><option v-for="row in available" :key="row.id" :value="row.id">{{ row.display_name }}</option></select></label>
        <div ref="board" class="relative mx-auto aspect-[3/4] w-full max-w-[420px] overflow-hidden border-2 border-white/70 text-white touch-none" :class="sport === 'basketball' ? 'bg-amber-800' : 'bg-emerald-800'" role="group" :aria-label="label('Spielfeld', 'Playing field')" @click.self="boardClick">
            <div class="pointer-events-none absolute inset-3 border-2 border-white/55" />
            <div v-if="sport === 'football'" class="pointer-events-none absolute left-3 right-3 top-1/2 border-t-2 border-white/55" />
            <div v-if="sport === 'football'" class="pointer-events-none absolute left-1/2 top-1/2 h-[22%] w-[32%] -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/55" />
            <div v-if="sport === 'basketball'" class="pointer-events-none absolute inset-x-[12%] top-[12%] bottom-[12%] rounded-[45%] border-2 border-white/40" />
            <button v-for="entry in starters" :key="entry.competition_roster_entry_id" type="button" class="absolute z-10 flex h-12 w-12 -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center rounded-full border-2 border-white bg-sky-600 text-xs leading-tight shadow-md focus:outline-none focus:ring-2 focus:ring-yellow-300" :style="{ left: `${entry.board_x}%`, top: `${entry.board_y}%` }" :disabled="!canEdit || busy" :title="`${name(entry)} #${entry.shirt_number}`" :aria-label="`${name(entry)} #${entry.shirt_number}`" @pointerdown="selected = entry.competition_roster_entry_id; $event.currentTarget.setPointerCapture($event.pointerId)" @pointerup="dragEnd($event, entry); selected = null" @click.stop>
                <strong>{{ entry.shirt_number || '?' }}</strong><span class="max-w-10 truncate">{{ name(entry).split(' ').at(-1) }}</span>
            </button>
            <button v-if="selected && canEdit" type="button" class="absolute inset-0 z-0 h-full w-full" :aria-label="label('Hier platzieren', 'Place here')" @click="boardClick" />
        </div>
        <p v-if="canEdit" class="text-sm text-secondary">{{ label('Person auswählen und auf das Feld tippen; Figuren lassen sich verschieben.', 'Select a player and tap the field; drag markers to reposition.') }}</p>
        <div class="space-y-2"><h5 class="font-semibold">{{ label('Kader und Ersatzbank', 'Squad and bench') }}</h5><div v-for="entry in entries" :key="entry.competition_roster_entry_id" class="grid grid-cols-[minmax(0,1fr)_5rem_auto] items-center gap-2 border-b border-border py-2"><span class="min-w-0 break-words">{{ name(entry) }}</span><input v-model="entry.shirt_number" :disabled="!canEdit || busy" inputmode="numeric" maxlength="3" pattern="[0-9]{1,3}" class="w-full rounded border-border bg-inputBg p-2" :aria-label="`${label('Trikotnummer', 'Shirt number')}: ${name(entry)}`" /><div class="flex gap-1"><button v-if="canEdit && entry.role === 'starter'" type="button" class="px-2 py-1 text-sm" :disabled="busy" @click="toBench(entry)">{{ label('Bank', 'Bench') }}</button><button v-if="canEdit && entry.role === 'substitute'" type="button" class="px-2 py-1 text-sm" :disabled="busy || starters.length >= maxStarters" @click="selected = entry.competition_roster_entry_id">{{ label('Feld', 'Field') }}</button><button v-if="canEdit" type="button" :disabled="busy" class="px-2 py-1" :title="label('Entfernen', 'Remove')" @click="entries = entries.filter((e) => e !== entry)"><i class="las la-times" /></button></div></div></div>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <button v-if="canEdit" type="button" class="rounded bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" :disabled="busy || !entries.length" @click="save"><i class="las la-save" /> {{ label('Aufstellung speichern', 'Save lineup') }}</button>
    </div>
</template>
