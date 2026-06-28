<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoVertical } from '@/services/logoAssets'

const { isDark } = useTheme()
const logoSrc = computed(() => logoVertical(isDark.value))
</script>

<template>
    <Link :href="route('welcome')" class="inline-flex justify-center">
        <img
            :src="logoSrc"
            alt="AIRMIUS Logo"
            class="inline-block h-full w-full object-contain"
            @error="applyLogoFallback"
        >
    </Link>
</template>
