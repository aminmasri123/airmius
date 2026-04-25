<!-- Pages/Chat/Index.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
defineOptions({ layout: AppLayout })
</script>

<template>
<Head :title="$t('Chat')" />

<div class="grid grid-cols-3 gap-4">

    <!-- Contacts -->
    <div class="bg-card p-4">
        Kontakte
    </div>

    <!-- Chat -->
    <div class="col-span-2 bg-card p-4">
        Chat Fenster
    </div>

</div>
</template>
