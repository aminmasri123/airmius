<script setup>
import AppTable from '@/Components/UI/AppTable.vue';

defineProps({
    audits: {
        type: Array,
        default: () => [],
    },
    canManageSecrets: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <section v-if="canManageSecrets" class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <h2 class="text-lg font-semibold text-primary">Mailbox-Audit</h2>
            <p class="mt-1 text-sm text-secondary">
                Protokolliert werden Änderungen und Testversand ohne Klartext-Passwörter.
            </p>
        </div>

        <AppTable
            :empty="!audits.length"
            empty-title="Noch keine Audit-Einträge"
            empty-description="Sobald Mailboxen geändert oder Testmails versendet werden, erscheint der Verlauf hier."
        >
            <template #head>
                <tr>
                    <th class="px-5 py-3">Zeit</th>
                    <th class="px-5 py-3">{{ $t('Kategorie') }}</th>
                    <th class="px-5 py-3">Aktion</th>
                    <th class="px-5 py-3">{{ $t('Admin') }}</th>
                    <th class="px-5 py-3">Details</th>
                </tr>
            </template>

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
        </AppTable>
    </section>
</template>
