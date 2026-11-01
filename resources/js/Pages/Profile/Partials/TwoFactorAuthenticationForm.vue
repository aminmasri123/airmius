<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import ActionSection from '@/Components/ActionSection.vue'
import Modal from '@/Components/Modal.vue'
import { useI18n } from 'vue-i18n'

defineProps({ requiresConfirmation: Boolean })
const { t } = useI18n()
const enabled = ref(false), pending = ref(false), loading = ref(true), busy = ref(false)
const setupKey = ref(''), qrCode = ref(''), recoveryCodes = ref([]), authenticatorCode = ref('')
const error = ref(''), dialogError = ref(''), action = ref(null), method = ref('email')
const password = ref(''), emailCode = ref(''), challenge = ref('')
const messageFor = e => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Die Aktion konnte nicht ausgeführt werden.'
const apply = data => {
    enabled.value = data.enabled
    pending.value = data.pending_confirmation
    setupKey.value = data.setup_key || ''
    qrCode.value = data.qr_code || ''
    recoveryCodes.value = data.recovery_codes || []
}
onMounted(async () => {
    try { apply((await axios.get(route('security.two-factor.show'))).data.data) }
    catch (e) { error.value = messageFor(e) }
    finally { loading.value = false }
})
const open = purpose => {
    action.value = purpose
    method.value = 'email'
    password.value = emailCode.value = challenge.value = dialogError.value = ''
}
const close = () => {
    if (busy.value) return
    action.value = null
    password.value = emailCode.value = challenge.value = ''
}
const sendCode = async () => {
    if (busy.value) return
    busy.value = true
    dialogError.value = ''
    try {
        challenge.value = (await axios.post(route('security.two-factor.email-code'), { purpose: action.value })).data.data.challenge_token
        emailCode.value = ''
    } catch (e) { dialogError.value = messageFor(e) }
    finally { busy.value = false }
}
const perform = async () => {
    if (busy.value) return
    if (method.value === 'email' && (!challenge.value || !/^\d{6}$/.test(emailCode.value.trim()))) {
        dialogError.value = 'Bitte fordere einen E-Mail-Code an und gib die sechs Ziffern ein.'
        return
    }
    busy.value = true
    dialogError.value = ''
    const credentials = method.value === 'email' ? { challenge_token: challenge.value, email_code: emailCode.value.trim() } : { current_password: password.value }
    try {
        const response = action.value === 'disable'
            ? await axios.delete(route('security.two-factor.disable'), { data: credentials })
            : await axios.post(route('security.two-factor.' + action.value), credentials)
        apply(response.data.data)
        error.value = ''
        busy.value = false
        close()
        router.reload({ only: ['auth'], preserveScroll: true, preserveState: true })
    } catch (e) { dialogError.value = messageFor(e) }
    finally { busy.value = false }
}
const confirmSetup = async () => {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        apply((await axios.post(route('security.two-factor.confirm'), { code: authenticatorCode.value })).data.data)
        authenticatorCode.value = ''
        router.reload({ only: ['auth'], preserveScroll: true, preserveState: true })
    } catch (e) { error.value = messageFor(e) }
    finally { busy.value = false }
}
</script>

<template>
    <ActionSection>
        <template #title>{{ t('settings.security.two_factor_title') }}</template>
        <template #description>{{ t('settings.security.two_factor_description') }}</template>
        <template #content>
            <div class="space-y-4 text-primary">
                <p>{{ enabled ? 'Zwei-Faktor-Authentifizierung ist aktiviert.' : pending ? 'Bitte schließe die Einrichtung ab.' : 'Zwei-Faktor-Authentifizierung ist ausgeschaltet.' }}</p>
                <p class="text-sm text-secondary">Zum Einrichten oder Ändern bestätigst du deine Identität per E-Mail-Code oder Airmius-Passwort. Bei Google-Anmeldung benötigst du dein Google-Passwort nicht.</p>
                <p v-if="loading" role="status">Wird geladen …</p>
                <p v-if="error" role="alert" class="text-error">{{ error }}</p>
                <button v-if="!loading && !enabled" type="button" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" @click="open('enable')">{{ pending && !setupKey ? 'Einrichtung neu starten' : '2FA einrichten' }}</button>
                <div v-if="pending && setupKey" class="space-y-3">
                    <p>Scanne den QR-Code mit deiner Authenticator-App oder trage den Einrichtungsschlüssel ein. Bestätige danach den Code aus dieser App.</p>
                    <div v-if="qrCode" class="inline-block bg-white p-3" v-html="qrCode" />
                    <p class="break-all font-mono">{{ setupKey }}</p>
                    <form class="flex flex-wrap gap-3" @submit.prevent="confirmSetup">
                        <input v-model="authenticatorCode" aria-label="Code aus der Authenticator-App" autocomplete="one-time-code" inputmode="numeric" maxlength="6" class="rounded-lg border border-border bg-inputBg px-3 py-2" placeholder="Authenticator-Code" :disabled="busy" />
                        <button :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary">Einrichtung bestätigen</button>
                    </form>
                </div>
                <div v-if="enabled && recoveryCodes.length" class="space-y-2">
                    <p>Bewahre diese Wiederherstellungscodes sicher auf. Jeder Code kann nur einmal verwendet werden.</p>
                    <pre class="overflow-x-auto rounded-lg bg-inputBg p-3">{{ recoveryCodes.join('\n') }}</pre>
                </div>
                <div v-if="enabled" class="flex flex-wrap gap-3">
                    <button type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="open('recovery')">Neue Wiederherstellungscodes erstellen</button>
                    <button type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2 text-error" @click="open('disable')">2FA ausschalten</button>
                </div>
            </div>
            <Modal :show="!!action" :closeable="!busy" aria-label="Sicherheitsänderung bestätigen" @close="close">
                <form class="space-y-4 p-3 text-primary" @submit.prevent="perform">
                    <h3 class="pr-8 text-lg font-semibold">Sicherheitsänderung bestätigen</h3>
                    <p class="text-sm text-secondary">{{ action === 'enable' ? '2FA einrichten' : action === 'disable' ? '2FA ausschalten' : 'Neue Wiederherstellungscodes erstellen' }}: Bestätige, dass du diese Änderung selbst angefordert hast.</p>
                    <div class="flex flex-wrap gap-4">
                        <label><input v-model="method" type="radio" value="email" :disabled="busy" /> E-Mail-Code</label>
                        <label><input v-model="method" type="radio" value="password" :disabled="busy" /> Airmius-Passwort</label>
                    </div>
                    <template v-if="method === 'email'">
                        <button type="button" :disabled="busy" class="rounded-lg border border-border px-3 py-2" @click="sendCode">{{ challenge ? 'Neuen Code senden' : 'Code per E-Mail senden' }}</button>
                        <p v-if="challenge" role="status" class="text-sm">Code an deine bestätigte E-Mail-Adresse gesendet. Er gilt 10 Minuten und nur für diese Änderung. Ein neuer Code ersetzt den vorherigen.</p>
                        <input v-if="challenge" v-model="emailCode" :disabled="busy" aria-label="E-Mail-Code" autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="E-Mail-Code" class="block w-full rounded-lg border border-border bg-inputBg px-3 py-2" />
                    </template>
                    <template v-else>
                        <input v-model="password" :disabled="busy" type="password" autocomplete="current-password" aria-label="Airmius-Passwort" placeholder="Airmius-Passwort" class="block w-full rounded-lg border border-border bg-inputBg px-3 py-2" />
                        <p class="text-sm text-secondary">Verwende dein Airmius-Passwort. Dein Google-Passwort wird nicht benötigt.</p>
                        <button type="button" :disabled="busy" class="block text-sm underline" @click="router.post(route('logout'), {}, { onSuccess: () => router.visit(route('password.request')) })">Abmelden und Airmius-Passwort einrichten / zurücksetzen</button>
                    </template>
                    <p v-if="dialogError" role="alert" class="text-error">{{ dialogError }}</p>
                    <div class="flex gap-3">
                        <button :disabled="busy" type="button" class="rounded-lg border border-border px-4 py-2" @click="close">Abbrechen</button>
                        <button :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary">{{ busy ? 'Bitte warten …' : 'Bestätigen' }}</button>
                    </div>
                </form>
            </Modal>
        </template>
    </ActionSection>
</template>
