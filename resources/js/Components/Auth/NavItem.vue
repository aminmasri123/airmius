<!-- Components/NavItem.vue -->
<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

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
    },
    activePaths: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['navigate'])
const page = usePage()
const isOpen = ref(false)

const hasSubitems = computed(() => props.subitems.length > 0)
const normalizePath = (url) => {
    if (!url) return ''

    try {
        const baseUrl = typeof window === 'undefined' ? 'http://localhost' : window.location.origin

        return new URL(url, baseUrl).pathname
    } catch (error) {
        return String(url).split('?')[0]
    }
}
const currentPath = computed(() => normalizePath(page.url))
const isSameSection = (href) => {
    const path = normalizePath(href)

    return path && (currentPath.value === path || currentPath.value.startsWith(`${path}/`))
}
const isSubitemActive = (item) => {
    const paths = [
        item.href,
        ...(item.activePaths || []),
    ]

    return paths.some((path) => isSameSection(path))
}
const isActive = computed(() => {
    if ([props.href, ...props.activePaths].some((path) => isSameSection(path))) {
        return true
    }

    return props.subitems.some(isSubitemActive)
})

watch(isActive, (active) => {
    if (active && hasSubitems.value) {
        isOpen.value = true
    }
}, {
    immediate: true,
})
</script>

<template>
    <div class="space-y-1">
        <button
            v-if="hasSubitems"
            type="button"
            @click="isOpen = !isOpen"
            :aria-expanded="isOpen"
            class="flex min-h-11 w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-primary transition-colors duration-200 hover:bg-muted"
            :class="{ 'bg-muted text-primary': isActive, 'bg-card': !isActive }"
        >
            <span class="flex min-w-0 items-center gap-3">
                <i :class="icon" class="la-lg shrink-0"></i>
                <span class="truncate">
                {{ $t(label) }}
                </span>
            </span>
            <i :class="isOpen ? 'las la-chevron-up' : 'las la-chevron-down'" class="la-lg shrink-0"></i>
        </button>

        <Link
            v-else
            :href="href"
            @click="emit('navigate')"
            :aria-current="isActive ? 'page' : undefined"
            class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium text-primary transition-colors duration-200 hover:bg-muted"
            :class="{ 'bg-muted text-primary': isActive }"
        >
            <span class="flex min-w-0 items-center gap-3">
                <i :class="icon" class="la-lg shrink-0"></i>
                <span class="truncate">
                {{ $t(label) }}
                </span>
            </span>
            <span v-if="badge" class="shrink-0 rounded-full bg-buttonPrimary px-2 py-0.5 text-xs font-semibold text-buttonTextPrimary">
                {{ badge }}
            </span>
        </Link>

        <div v-if="hasSubitems && isOpen" class="space-y-1 pl-8">
            <Link
                v-for="sub in subitems"
                :key="sub.label"
                :href="sub.href"
                @click="emit('navigate')"
                :aria-current="isSubitemActive(sub) ? 'page' : undefined"
                class="block min-h-10 rounded-lg px-3 py-2 text-sm text-secondary transition-colors duration-200 hover:bg-muted hover:text-primary"
                :class="{ 'bg-muted text-primary': isSubitemActive(sub) }"
            >
                {{ $t(sub.label) }}
            </Link>
        </div>
    </div>
</template>
