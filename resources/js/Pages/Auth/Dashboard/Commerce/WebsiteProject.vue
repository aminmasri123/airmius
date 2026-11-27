<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import WebsiteProjectConversation from '@/Components/Commerce/WebsiteProjectConversation.vue'
defineOptions({ layout: AppLayout })
defineProps({ projectId: { type: Number, required: true } })
</script>

<template>
    <main class="mx-auto max-w-5xl p-6"><WebsiteProjectConversation :project-id="projectId" /></main>
</template>
