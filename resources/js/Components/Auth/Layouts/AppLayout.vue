<!-- Components/Layouts/AppLayout.vue -->
<script setup>
import Sidebar from '@/Components/Auth/Sidebar.vue'
import { Head } from '@inertiajs/vue3'
import { onMounted } from 'vue'

defineProps({
    title: String
})

onMounted(() => {
    const theme = localStorage.getItem('theme')
    if (theme) {
        document.documentElement.classList.add(`theme-${theme}`)
    }
})
</script>

<template>
    <Head :title="title" />

<div class="flex h-screen bg-bg text-primary">
    <Sidebar />

    <main class="flex-1 p-6 overflow-auto">
        <slot />
    </main>
</div>
</template>
