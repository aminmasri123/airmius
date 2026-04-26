<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useLanguage } from '@/services/i18nService'

const open = ref(false)
const dropdownRef = ref(null)
const showSuccess = ref(false) // 👉 Für die Erfolgsanzeige

const { locale, languages, changeLang } = useLanguage()

const handleLanguageChange = async (code) => {
    await changeLang(code) // Ruft deine Funktion mit router.post auf
    open.value = false

    // 👉 Kleines visuelles Feedback
    showSuccess.value = true
    setTimeout(() => showSuccess.value = false, 2000)
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-left">

        <!-- BUTTON -->
        <button
            @click="open = !open"
            class="flex items-center gap-2 px-4 py-2 bg-card/80 border border-border rounded-lg text-sm text-primary hover:border-borderHover hover:bg-muted transition active:scale-95"
        >
            <span v-if="!showSuccess">🌍 {{ locale.toUpperCase() }}</span>
            <span v-else class="text-green-400">✅ Gespeichert</span> <!-- 👉 Feedback -->
        </button>

        <!-- DROPDOWN -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 mt-2 w-44 bg-card text-primary rounded-xl shadow-xl z-50 overflow-hidden border border-border"
            >
                <div class="py-1">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        @click="handleLanguageChange(lang.code)"
                        class="flex items-center w-full text-left px-4 py-3 text-sm hover:bg-muted transition"
                        :class="locale === lang.code ? 'bg-muted text-primary font-bold' : ''"
                    >
                        <span class="flex-1">{{ lang.label }}</span>
                        <i v-if="locale === lang.code" class="las la-check text-xs"></i>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
