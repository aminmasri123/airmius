<!-- Pages/Chat/Index.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

import { useTheme } from '@/services/useTheme'

defineOptions({ layout: AppLayout })

const { setTheme } = useTheme()


import { router } from '@inertiajs/vue3'

setTheme
</script>

<template>

    <Head :title="$t('Einstellungen')" />

    <div class="space-y-5">

        <!-- Contacts -->
        <div class="surface-card p-5 text-xl font-semibold">
            {{ $t('Einstellungen') }}
        </div>


        <div class="surface-card p-5">
            <div class="mb-3 text-sm font-semibold text-secondary">Design</div>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 rounded-lg border border-border bg-muted hover:border-borderHover transition" @click="setTheme('air')">Air</button>
                <button class="px-4 py-2 rounded-lg border border-border bg-muted hover:border-borderHover transition" @click="setTheme('dark')">Dark</button>
                <button class="px-4 py-2 rounded-lg border border-border bg-muted hover:border-borderHover transition" @click="setTheme('womanly')">Womanly</button>
            </div>
        </div>
    </div>
</template>
