<script setup>
defineProps({
    audits: {
        type: Array,
        default: () => [],
    },
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <section v-if="canManageSecrets" class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Mailbox-Audit</h2>
            <p class="mt-1 text-sm text-secondary">
                Protokolliert werden Änderungen und Testversand ohne Klartext-Passwörter.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-bg">
                    <tr class="text-left text-xs uppercase tracking-wide text-secondary">
                        <th class="px-5 py-3">Zeit</th>
                        <th class="px-5 py-3">Kategorie</th>
                        <th class="px-5 py-3">Aktion</th>
                        <th class="px-5 py-3">Admin</th>
                        <th class="px-5 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!audits.length">
                        <td colspan="5" class="px-5 py-8 text-center text-secondary">Noch keine Audit-Einträge.</td>
                    </tr>
                    <tr v-for="audit in audits" :key="audit.id">
                        <td class="px-5 py-4 text-secondary">{{ audit.created_at }}</td>
                        <td class="px-5 py-4 text-primary">{{ audit.category }}</td>
                        <td class="px-5 py-4 text-primary">{{ audit.action }}</td>
                        <td class="px-5 py-4 text-secondary">#{{ audit.actor_id || '-' }}</td>
                        <td class="px-5 py-4 text-xs text-secondary">
                            <span v-if="audit.after?.password_changed">Passwort wurde neu gesetzt. </span>
                            <span v-if="audit.after?.from_address">Absender: {{ audit.after.from_address }}</span>
                            <span v-else-if="audit.after?.error">Fehler: {{ audit.after.error }}</span>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

