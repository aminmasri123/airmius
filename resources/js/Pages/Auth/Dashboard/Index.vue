<!-- Pages/Dashboard.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
defineOptions({ layout: AppLayout })
</script>

<template>

    <Head :title="$t('Dashboard')" />
    <div class="space-y-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="surface-card p-5">
                <div class="text-xl font-bold">4</div>
                <div class="text-secondary text-sm">Trainings</div>
            </div>

            <div class="surface-card p-5">
                <div class="text-xl font-bold">87</div>
                <div class="text-secondary text-sm">Fitness</div>
            </div>
        </div>
    </div>
</template>
