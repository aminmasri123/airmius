<!-- Components/NavItem.vue -->
<script setup>
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    href: String,
    label: String,
    icon: String
})

const page = usePage()
</script>

<template>
<Link
    :href="href"

    class="block px-4 py-2 rounded text-primary hover:bg-muted hover:text-card transition-colors duration-200"
    :class="{ 'bg-white/10 text-white': page.url === href }"
>
    <i :class="icon" class="mr-3 la-lg"></i>
    {{ label }}
</Link>
</template>
