<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    award: Object,
})
</script>

<template>
    <Head :title="award?.badge?.name || 'Badge'" />

    <section class="surface-card mx-auto max-w-2xl p-6">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                <i :class="[award?.badge?.icon || 'las la-medal', 'text-2xl']"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-primary">{{ award?.badge?.name }}</h1>
                <p class="mt-2 text-secondary">{{ award?.badge?.description }}</p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-secondary">Typ</dt>
                        <dd class="font-semibold text-primary">{{ award?.badge?.actor_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-secondary">Grund</dt>
                        <dd class="font-semibold text-primary">{{ award?.reason || award?.badge?.trigger }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
