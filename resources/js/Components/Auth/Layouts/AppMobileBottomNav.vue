<script setup>
import { Link } from '@inertiajs/vue3'
import { useAirmiusShellNavigation } from '@/composables/useAirmiusShellNavigation'

const { primarySpaces } = useAirmiusShellNavigation()
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-2 pb-[calc(0.35rem+env(safe-area-inset-bottom))] pt-1.5 shadow-[0_-12px_28px_rgba(0,0,0,0.14)] backdrop-blur-xl md:hidden"
        :aria-label="$t('shell.mobile_navigation_label')"
    >
        <div class="mx-auto grid max-w-lg grid-cols-5 gap-1">
            <Link
                v-for="space in primarySpaces"
                :key="space.key"
                :href="space.href"
                :aria-current="space.active ? 'page' : undefined"
                class="relative flex min-w-0 flex-col items-center gap-1 rounded-xl px-1 py-2 text-[10px] font-bold transition"
                :class="space.active ? 'bg-muted text-primary' : 'text-secondary hover:bg-muted hover:text-primary'"
            >
                <span v-if="space.active" class="absolute inset-x-4 -top-1 h-0.5 rounded-full bg-buttonPrimary"></span>
                <i :class="[space.icon, 'text-xl']"></i>
                <span class="w-full truncate text-center leading-none">{{ $t(space.label) }}</span>
            </Link>
        </div>
    </nav>
</template>
