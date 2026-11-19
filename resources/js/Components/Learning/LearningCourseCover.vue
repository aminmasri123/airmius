<script setup>
import { ref, watch } from 'vue'
const props = defineProps({ src: String, title: String })
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })
</script>
<template>
    <div class="flex aspect-video items-center justify-center overflow-hidden bg-gradient-to-br from-air-blue/20 to-success/20">
        <img v-if="src && !failed" :src="src" :alt="title" loading="lazy" decoding="async" class="h-full w-full object-cover" @error="failed=true">
        <i v-else class="las la-book-open text-6xl text-air-blue" aria-hidden="true"></i>
    </div>
</template>
