<script setup>
import AppEmptyState from '@/Components/UI/AppEmptyState.vue';
</script>

<template>
    <section class="surface-card flex min-h-[28rem] items-center justify-center p-8 text-center">
        <AppEmptyState
            title="Starte deine Online-Sportschule"
            description="Lege links deinen ersten Kurs an. Danach kannst du Kapitel, Lektionen und Quiz direkt im Studio bauen."
        >
            <template #icon>
                <i class="las la-graduation-cap text-2xl" aria-hidden="true"></i>
            </template>
        </AppEmptyState>
    </section>
</template>
