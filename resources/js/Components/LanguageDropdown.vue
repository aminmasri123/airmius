<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useLanguage } from '@/services/i18nService'

const props = defineProps({
    align: {
        type: String,
        default: 'end',
        validator: (value) => ['start', 'end'].includes(value),
    },
})

const open = ref(false)
const dropdownRef = ref(null)
const showSuccess = ref(false)

const { locale, languages, changeLang } = useLanguage()

const handleLanguageChange = async (code) => {
    await changeLang(code)
    open.value = false
    showSuccess.value = true
    setTimeout(() => showSuccess.value = false, 1800)
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
    <div ref="dropdownRef" class="relative inline-block text-start">
        <button
            type="button"
            @click="open = !open"
            class="flex items-center gap-2 rounded-lg border border-border bg-card/80 px-4 py-2 text-sm text-primary transition hover:border-borderHover hover:bg-muted active:scale-95"
        >
            <span v-if="!showSuccess">{{ locale.toUpperCase() }}</span>
            <span v-else class="text-success">{{ $t('common.saved') }}</span>
        </button>

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
                class="absolute z-50 mt-2 w-44 overflow-hidden rounded-xl border border-border bg-card text-primary shadow-xl"
                :class="props.align === 'start' ? 'start-0' : 'end-0'"
            >
                <div class="py-1">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        type="button"
                        @click="handleLanguageChange(lang.code)"
                        class="flex w-full items-center px-4 py-3 text-start text-sm transition hover:bg-muted"
                        :class="locale === lang.code ? 'bg-muted font-bold text-primary' : ''"
                    >
                        <span class="flex-1">{{ lang.native }}</span>
                        <i v-if="locale === lang.code" class="las la-check text-xs"></i>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
