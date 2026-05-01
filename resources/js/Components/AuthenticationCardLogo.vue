<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue'

const logoSrc = computed(() => {
    const isDark = document.documentElement.classList.contains('theme-dark')
    return isDark ? '/img/logo/Logo-Dark-Airmius-mit-Schrift.png' : '/img/logo/Logo-Airmius-mit-Schrift.png'
})
</script>

<template>
    <Link :href="route('welcome')">
        <img :src="logoSrc" alt="AIRMIUS Logo" class=" mr-2 inline-block -mt-1">


    </Link>
</template>
