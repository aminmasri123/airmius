<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
</script>
<template>
    <Head title="Unterhaltung nicht verfügbar" />
    <section class="mx-auto my-12 max-w-xl rounded-2xl border p-8 text-center">
        <h1 class="text-xl font-bold">Diese Unterhaltung ist nicht verfügbar.</h1>
        <p class="my-4">Prüfe, ob du mit dem richtigen Konto angemeldet bist.</p>
        <Link :href="route('auth.conversations.index')" class="text-air-blue">Zur Nachrichtenübersicht</Link>
    </section>
</template>
