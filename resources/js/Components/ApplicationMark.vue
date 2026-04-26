<script setup>
import { computed } from 'vue'
import { useTheme } from '@/services/useTheme'

const { isDark } = useTheme()

const logoSrc = computed(() => {
    return isDark.value
        ? '/img/logo/LOGO-Dark-Airmius-Quervormat.png'
        : '/img/logo/Logo-Airmius-Quervormat.png'
})
</script>
<template>
    <img :src="logoSrc" alt="AIRMIUS Logo" >
</template>
