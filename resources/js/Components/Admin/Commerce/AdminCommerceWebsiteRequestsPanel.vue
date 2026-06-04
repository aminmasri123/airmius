<script setup>
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
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    <tr v-for="request in websiteRequests" :key="request.id">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-primary">{{ request.club?.name || request.club_name || 'Ohne Verein' }}</p>
                            <p class="text-xs text-secondary">{{ request.user?.email || request.guest_email || '-' }}</p>
                            <p v-if="request.guest_name" class="text-xs text-secondary">{{ request.guest_name }}</p>
                        </td>
                        <td class="px-5 py-3 text-secondary">{{ request.domain || '-' }}</td>
                        <td class="px-5 py-3 text-secondary">{{ request.status }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('update-website-request', request, 'contacted')">Kontaktiert</button>
                                <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('update-website-request', request, 'quoted')">Angebot</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!websiteRequests.length" class="px-5 py-6 text-sm text-secondary">Noch keine Website-Anfragen.</p>
        </div>
    </section>
</template>

