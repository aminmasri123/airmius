<script setup>
import { ref } from 'vue'

const props = defineProps({
    label: String,
    icon: String
})

const open = ref(false)
</script>

<template>
    <div>
        <button @click="open = !open"
            class="w-full flex items-center justify-between px-3 py-2 rounded hover:bg-muted transition">

            <div class="flex items-center gap-2">
                <i :class="icon"></i>
                <span>{{ label }}</span>
            </div>

            <i :class="open ? 'las la-angle-down' : 'las la-angle-right'"></i>
        </button>

        <div v-if="open" class="ml-6 mt-1 space-y-1">
            <slot />
        </div>
    </div>
</template>
