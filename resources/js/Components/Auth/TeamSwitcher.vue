<!-- Components/TeamSwitcher.vue -->
<script setup>
import { router } from '@inertiajs/vue3'

const switchTeam = (id) => {
    router.post('/switch-club', { club_id: id })
}
</script>

<template>
<div class="px-4 mt-4">
    <div class="text-xs text-gray-500 mb-2">{{ $t('My teams') }}</div>

    <div class="space-y-2">
        <div
            class="p-2 bg-white/5 rounded cursor-pointer"
            @click="switchTeam(1)">
            SC Adler
        </div>
    </div>
</div>
</template>
