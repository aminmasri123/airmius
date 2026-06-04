<script setup>
defineProps({
    clubProfile: { type: Object, required: true },
})
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-2xl font-semibold text-primary">{{ clubProfile.users_count }}</div>
            <div class="text-sm text-secondary">Mitglieder</div>
        </div>
        <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-2xl font-semibold text-primary">{{ clubProfile.teams_count }}</div>
            <div class="text-sm text-secondary">Teams</div>
        </div>
        <div class="rounded-lg border border-border bg-card p-4">
            <div class="text-2xl font-semibold text-primary">{{ clubProfile.posts_count }}</div>
            <div class="text-sm text-secondary">Beiträge</div>
        </div>
    </section>
</template>

