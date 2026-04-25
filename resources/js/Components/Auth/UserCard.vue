<!-- Components/UserCard.vue -->
<script setup>
import { usePage } from '@inertiajs/vue3'

const user = usePage().props.auth.user
</script>

<template>
<div class="p-4 border-t border-border">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-blue-500 rounded-full flex items-center justify-center">
            {{ user.name[0] }}
        </div>
        <div>
            <div class="text-sm">{{ user.name }}</div>
            <div class="text-xs text-muted">{{ user.role }}</div>
        </div>
    </div>
</div>
</template>
