<script setup>
import { computed } from 'vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoWordmark } from '@/services/logoAssets'

const { isDark } = useTheme()
const logoSrc = computed(() => logoWordmark(isDark.value))
</script>

<template>
    <img :src="logoSrc" alt="AIRMIUS Logo" class="inline-block w-auto object-contain align-middle" @error="applyLogoFallback">
</template>
