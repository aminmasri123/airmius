<!-- Pages/Chat/Index.vue -->
<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
defineOptions({ layout: AppLayout })



import { router } from '@inertiajs/vue3'


function changeTheme(theme) {
    const root = document.documentElement

    // alte Themes entfernen
    root.classList.remove('theme-air', 'theme-dark', 'theme-womanly')

    // neues Theme setzen
    root.classList.add(`theme-${theme}`)

    // Theme in LocalStorage speichern
    localStorage.setItem('theme', theme)

    router.put(route('auth.settings.update'), {
        theme: theme
    },
        {
            preserveState: true,
            preserveScroll: true,
        });
}
</script>

<template>

    <Head :title="$t('Einstellungen')" />

    <div class="">

        <!-- Contacts -->
        <div class="bg-card p-4">
            {{ $t('Einstellungen') }}
        </div>


        <div class="flex gap-4">
            <button @click="changeTheme('air')">Air</button>
            <button @click="changeTheme('dark')">Dark</button>
            <button @click="changeTheme('womanly')">Womanly</button>
        </div>
    </div>
</template>
