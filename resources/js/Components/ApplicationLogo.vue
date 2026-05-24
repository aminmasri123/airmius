<script setup>
import { LOGO_MARK, applyLogoFallback } from '@/services/logoAssets'
</script>

<template>
    <img :src="LOGO_MARK" alt="AIRMIUS Logo" class="w-10 h-10 mr-2 inline-block -mt-1" @error="applyLogoFallback">

</template>
