<!-- Pages/Teams/Index.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
defineProps({
    teams: Array
})

defineOptions({ layout: AppLayout })
</script>

<template>
<Head :title="$t('Teams')" />

<div>
    <h1 class="text-xl font-bold mb-4">Teams</h1>

    <div class="grid grid-cols-3 gap-4">
        <div v-for="team in teams" :key="team.id"
             class="bg-card p-4 rounded">
            {{ team.name }}
        </div>
    </div>
</div>
</template>
