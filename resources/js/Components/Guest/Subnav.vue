<script setup>
import { Link } from '@inertiajs/vue3'

const items = [
    ['las la-bullhorn', 'Top Inhalte'],
    ['las la-briefcase', 'Jobs'],
    ['las la-chalkboard-teacher', 'E-Learning'],
    ['las la-warehouse', 'Vereine'],
    ['las la-shopping-bag', 'Marketplace'],
]
</script>

<template>
    <div class="fixed bottom-0 md:top-16 left-0 right-0 max-w-7xl mx-auto h-16 sm:px-6 bg-card/90 border-y border-border backdrop-blur items-center z-40 transition-all duration-300">
        <div class="hidden md:flex items-center justify-center gap-6 py-2 text-sm font-medium text-secondary">
            <Link
                v-for="[icon, label] in items"
                :key="label"
                class="pb-1 text-center transition hover:text-primary"
            >
                <p><i :class="[icon, 'la-2x']"></i></p>
                {{ $t(label) }}
            </Link>
        </div>

        <div class="md:hidden flex w-full justify-start gap-6 overflow-x-auto bg-card/95 px-4 py-2 text-xs text-secondary custom-scrollbar">
            <button
                v-for="[icon, label] in items"
                :key="label"
                class="py-1 rounded-full whitespace-nowrap transition hover:text-primary"
            >
                <p><i :class="[icon, 'la-2x']"></i></p>
                {{ $t(label) }}
            </button>
        </div>
    </div>
</template>
