<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    workspaces: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Arbeitsbereiche" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <h1 class="text-2xl font-bold text-primary">Arbeitsbereiche</h1>
            <p class="mt-1 text-sm text-secondary">
                Rollenbasierte Einstiege für deine Aufgaben in Airmius.
            </p>
        </section>

        <section v-if="workspaces.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="workspace in workspaces"
                :key="workspace.key"
                :href="workspace.href"
                class="surface-card group p-5 transition hover:border-borderHover"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                        <i :class="[workspace.icon, 'text-xl']"></i>
                    </div>
                    <span class="rounded bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                        {{ workspace.status }}
                    </span>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-primary">{{ workspace.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ workspace.description }}</p>
            </Link>
        </section>

        <section v-else class="surface-card p-6 text-sm text-secondary">
            Für deine aktuelle Rolle sind noch keine speziellen Arbeitsbereiche sichtbar.
        </section>
    </div>
</template>
