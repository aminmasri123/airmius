<script setup>
import axios from 'axios'
import { ref, watch } from 'vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({ userId: Number, roleName: String })
const emit = defineEmits(['saved'])
const opened = ref(false)
const busy = ref(false)
const query = ref('')
const assignedOnly = ref(false)
const users = ref([])
const roles = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const target = ref(null)
const selected = ref([])
const error = ref('')
const message = ref('')
let sequence = 0

const load = async (page = 1) => {
    const request = ++sequence
    busy.value = true
    error.value = ''
    target.value = null
    try {
        const { data: response } = await axios.get(route('role-assignments.index'), {
            params: { q: query.value, page, user_id: props.userId, role: props.roleName, assigned_only: assignedOnly.value ? 1 : 0 },
        })
        if (request !== sequence) return
        users.value = response.data.users.data
        roles.value = response.data.roles
        currentPage.value = response.data.users.current_page
        lastPage.value = response.data.users.last_page
        if (props.userId && users.value[0]) choose(users.value[0])
    } catch (e) {
        if (request === sequence) error.value = e.response?.data?.message || 'Nutzer konnten nicht geladen werden.'
    } finally {
        if (request === sequence) busy.value = false
    }
}
const choose = (user) => {
    target.value = user
    selected.value = [...user.roles]
    message.value = ''
}
const open = () => {
    opened.value = !opened.value
    if (opened.value) load()
}
const save = async () => {
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.patch(route('role-assignments.update', target.value.id), {
            roles: selected.value, expected_roles: target.value.roles,
        })
        target.value.roles = [...data.data.roles]
        selected.value = [...data.data.roles]
        message.value = data.message
        emit('saved')
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Rollen konnten nicht gespeichert werden.'
    } finally {
        busy.value = false
    }
}
watch(() => props.roleName, () => { if (opened.value) load() })
</script>

<template>
    <div class="space-y-3">
        <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" :disabled="busy" @click="open">
            {{ userId ? 'Rollen verwalten' : 'Nutzer zuweisen / verwalten' }}
        </button>
        <Modal :show="opened" :closeable="!busy" aria-label="Rollenzuweisung" @close="opened = false">
        <section class="space-y-4 rounded-lg border border-border bg-card p-4" aria-label="Rollenzuweisung">
            <h2 class="pr-10 text-lg font-semibold text-primary">{{ userId ? 'Rollen verwalten' : 'Nutzer zuweisen / verwalten' }}</h2>
            <p class="text-sm text-secondary">Änderungen gelten auf Plattformebene. Die Person erhält eine Benachrichtigung mit ihren aktuellen Berechtigungen.</p>
            <form v-if="!userId && !target" class="flex flex-wrap items-center gap-2" @submit.prevent="load()">
                <input v-model="query" maxlength="255" aria-label="Name oder E-Mail suchen" placeholder="Name oder E-Mail" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-primary" />
                <label v-if="roleName" class="text-sm text-primary"><input v-model="assignedOnly" type="checkbox" /> Nur dieser Rolle zugewiesene Personen</label>
                <button :disabled="busy" class="rounded-lg bg-buttonPrimary px-3 py-2 text-buttonTextPrimary">Suchen</button>
            </form>
            <p v-if="busy" role="status">Wird geladen …</p>
            <p v-if="error" role="alert" class="text-error">{{ error }}</p>
            <p v-if="message" role="status" class="text-primary">{{ message }}</p>
            <div v-if="!userId && !target" class="space-y-2">
                <button v-for="user in users" :key="user.id" type="button" :disabled="busy" class="block w-full rounded-lg border border-border p-3 text-left text-primary" @click="choose(user)">
                    <span class="font-semibold">{{ user.name }}</span> · {{ user.email }}
                    <span class="block text-sm text-secondary">{{ user.roles.join(', ') || 'Keine Rolle' }}</span>
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" :disabled="busy || currentPage <= 1" @click="load(currentPage - 1)">Zurück</button>
                    <span>{{ currentPage }} / {{ lastPage }}</span>
                    <button type="button" :disabled="busy || currentPage >= lastPage" @click="load(currentPage + 1)">Weiter</button>
                </div>
            </div>
            <p v-if="!busy && !users.length" class="text-secondary">Keine zuweisbaren Personen gefunden.</p>
            <form v-if="target" class="space-y-3" @submit.prevent="save">
                <h3 class="font-semibold text-primary">Rollen für {{ target.name }}</h3>
                <button v-if="!userId" type="button" :disabled="busy" class="text-sm text-primary underline" @click="target = null; message = ''; error = ''">Andere Person wählen</button>
                <p v-if="roleName" class="text-sm text-secondary">Gewünschte Rolle: {{ roleName }}. Bestehende Rollen bleiben ausgewählt.</p>
                <div class="max-h-96 space-y-2 overflow-y-auto">
                    <label v-for="role in roles" :key="role.name" class="block rounded-lg border border-border p-3 text-primary">
                        <input v-model="selected" :value="role.name" type="checkbox" :disabled="busy" /> {{ role.name }}
                        <span class="block text-sm text-secondary">{{ role.description }}</span>
                        <span class="block text-xs text-secondary">{{ role.permissions.map(p => p.description).join('; ') || 'Keine zusätzlichen Berechtigungen' }}</span>
                    </label>
                </div>
                <button :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary">Rollen speichern</button>
            </form>
        </section>
        </Modal>
    </div>
</template>
