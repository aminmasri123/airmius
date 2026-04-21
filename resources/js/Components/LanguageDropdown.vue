<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useLanguage } from '@/services/i18nService'


const open = ref(false)
const dropdownRef = ref(null)

const { locale, languages, changeLang } = useLanguage()

// 👉 Outside Click Handler
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        open.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-left">

        <!-- BUTTON -->
        <button
            @click="open = !open"
            class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-sm hover:bg-white/20 transition"
        >
            🌍 {{ locale.toUpperCase() }}
        </button>

        <!-- DROPDOWN -->
        <div
            v-if="open"
            class="absolute right-0 mt-2 w-44 bg-white text-black rounded-xl shadow-lg z-50 overflow-hidden"
        >
            <button
                v-for="lang in languages"
                :key="lang.code"
                @click="changeLang(lang.code); open = false"
                class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition"
                :class="locale === lang.code ? 'bg-gray-200 font-semibold' : ''"
            >
                {{ lang.label }}
            </button>
        </div>
    </div>
</template>
