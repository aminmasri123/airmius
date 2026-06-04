<script setup>
defineProps({
    profileUser: { type: Object, required: true },
})
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
        <h2 class="text-lg font-bold text-primary">Rollen & Berechtigungen</h2>
        <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
            <div class="rounded-xl border border-border bg-bg p-4">
                <div class="font-semibold text-primary">Rollen</div>
                <div class="mt-2 text-secondary">{{ profileUser.roles.length ? profileUser.roles.join(', ') : 'Keine Rollen' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-bg p-4">
                <div class="font-semibold text-primary">Berechtigungen</div>
                <div class="mt-2 max-h-32 overflow-auto text-secondary">
                    {{ profileUser.permissions.length ? profileUser.permissions.join(', ') : 'Keine Berechtigungen' }}
                </div>
            </div>
        </div>
    </section>
</template>
