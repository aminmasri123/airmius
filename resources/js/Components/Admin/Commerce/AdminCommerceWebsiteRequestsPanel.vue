<script setup>
import { useI18n } from 'vue-i18n'
import { ref } from 'vue'
import WebsiteProjectConversation from '@/Components/Commerce/WebsiteProjectConversation.vue'
const selected = ref(null)
const statuses = { new: 'Anfrage eingegangen', contacted: 'In Klärung', quoted: 'Angebot erstellt', in_progress: 'In Umsetzung', done: 'Abgeschlossen', cancelled: 'Storniert' }

const { t } = useI18n()

defineProps({
    websiteRequests: { type: Array, default: () => [] },
})

const emit = defineEmits(['update-website-request'])
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Website-Anfragen</h2>
            <p class="mt-1 text-sm text-secondary">Vereine, die eine Website von Airmius erstellen lassen möchten.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-start text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="request in websiteRequests" :key="request.id">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ request.club?.name || request.club_name || 'Ohne Verein' }}</p>
                            <p class="text-xs text-secondary">{{ request.user?.email || request.guest_email || '-' }}</p>
                            <p v-if="request.guest_name" class="text-xs text-secondary">{{ request.guest_name }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ request.domain || '-' }}</td>
                        <td class="px-5 py-3 text-secondary">{{ statuses[request.status] || request.status }}</td>
                        <td class="px-5 py-3 text-end">
                                                        <div class="flex flex-wrap justify-end gap-3">
                                <button type="button" class="rounded-lg border border-border px-3 py-2" @click="selected = request.id">Projekt und Nachrichten</button>
                                <select :value="request.status" aria-label="Projektstatus ändern" class="rounded-lg border-border bg-inputBg text-primary" @change="emit('update-website-request', request, $event.target.value)"><option v-for="(label, status) in statuses" :key="status" :value="status">{{ label }}</option></select>
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-website-request', request, 'contacted')">Kontaktiert</button>
                                <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('update-website-request', request, 'quoted')">{{ t('commerce.ui.offer') }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!websiteRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Website-Anfragen.</p>
        </div>
        <WebsiteProjectConversation v-if="selected" :key="`${selected}-${websiteRequests.find(r => r.id === selected)?.status}`" :project-id="selected" class="m-5" />
    </section>
</template>
