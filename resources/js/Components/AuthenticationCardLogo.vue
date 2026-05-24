<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoFull } from '@/services/logoAssets'

const { isDark } = useTheme()
const logoSrc = computed(() => logoFull(isDark.value))
</script>

<template>
    <Link :href="route('welcome')">
        <img :src="logoSrc" alt="AIRMIUS Logo" class=" mr-2 inline-block -mt-1" @error="applyLogoFallback">


    </Link>
</template>
