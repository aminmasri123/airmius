<!-- Components/NavItem.vue -->
<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    href: String,
    label: String,
    icon: String,
    badge: {
        type: [String, Number],
        default: null,
    },
    subitems: {
        type: Array,
        default: () => []
    }
})

const page = usePage()
const isOpen = ref(false)

const hasSubitems = computed(() => props.subitems.length > 0)
const isActive = computed(() => {
    if (props.href && page.url === props.href) {
        return true
    }

    return props.subitems.some(item => item.href && page.url === item.href)
})
</script>

<template>
    <div class="space-y-1">
        <button
            v-if="hasSubitems"
            type="button"
            @click="isOpen = !isOpen"
            class="w-full flex items-center justify-between px-4 py-2 rounded text-primary hover:bg-muted hover:text-card transition-colors duration-200"
            :class="{ 'bg-white/10 text-white': isActive, 'bg-card': !isActive }"
        >
            <span class="flex items-center">
                <i :class="icon" class="mr-3 la-lg"></i>
                {{ label }}
            </span>
            <i :class="isOpen ? 'las la-chevron-up' : 'las la-chevron-down'" class="la-lg"></i>
        </button>

        <Link
            v-else
            :href="href"
            class="block px-4 py-2 rounded text-primary hover:bg-muted hover:text-secondary transition-colors duration-200"
            :class="{ 'bg-white/10 text-white': isActive }"
        >
            <span>
                <i :class="icon" class="mr-3 la-lg"></i>
                {{ label }}
            </span>
            <span v-if="badge" class="rounded-full bg-buttonPrimary px-2 py-0.5 text-xs font-semibold text-buttonTextPrimary">
                {{ badge }}
            </span>
        </Link>

        <div v-if="hasSubitems && isOpen" class="space-y-1 pl-8">
            <Link
                v-for="sub in subitems"
                :key="sub.label"
                :href="sub.href"
                class="block px-4 py-2 rounded text-secondary hover:bg-muted hover:text-card transition-colors duration-200"
                :class="{ 'bg-white/10 text-white': page.url === sub.href }"
            >
                {{ sub.label }}
            </Link>
        </div>
    </div>
</template>
