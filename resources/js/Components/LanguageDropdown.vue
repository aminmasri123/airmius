<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
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
const buttonRef = ref(null)
const showSuccess = ref(false)
const changeFailed = ref(false)
const changing = ref(false)
const menuStyle = ref({})
let feedbackTimeout = null

const { locale, languages, changeLang } = useLanguage()

const updateDropdownPosition = () => {
    if (!buttonRef.value || typeof window === 'undefined') {
        return
    }

    const rect = buttonRef.value.getBoundingClientRect()
    const viewportPadding = 8
    const menuWidth = Math.min(176, window.innerWidth - viewportPadding * 2)
    const preferredLeft = props.align === 'start' ? rect.left : rect.right - menuWidth
    const left = Math.min(
        Math.max(preferredLeft, viewportPadding),
        window.innerWidth - menuWidth - viewportPadding,
    )

    menuStyle.value = {
        left: `${Math.round(left)}px`,
        top: `${Math.round(rect.bottom + 8)}px`,
        width: `${Math.round(menuWidth)}px`,
    }
}

const toggleOpen = async () => {
    open.value = !open.value

    if (open.value) {
        await nextTick()
        updateDropdownPosition()
    }
}

const handleLanguageChange = async (code) => {
    if (changing.value) {
        return
    }

    if (code === locale.value) {
        open.value = false
        return
    }

    changing.value = true
    changeFailed.value = false
    showSuccess.value = false

    try {
        await changeLang(code)
        open.value = false
        showSuccess.value = true
    } catch (error) {
        changeFailed.value = true
    } finally {
        changing.value = false
        window.clearTimeout(feedbackTimeout)
        feedbackTimeout = window.setTimeout(() => {
            showSuccess.value = false
            changeFailed.value = false
        }, 1800)
    }
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        open.value = false
    }
}

const handleViewportChange = () => {
    if (open.value) {
        updateDropdownPosition()
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
    window.clearTimeout(feedbackTimeout)
})
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-start">
        <button
            ref="buttonRef"
            type="button"
            @click="toggleOpen"
            :aria-busy="changing"
            :disabled="changing"
            class="flex items-center gap-2 rounded-lg border border-border bg-card/80 px-4 py-2 text-sm text-primary transition hover:border-borderHover hover:bg-muted active:scale-95 disabled:cursor-wait disabled:opacity-60"
        >
            <span v-if="changing">{{ $t('common.changing_language') }}</span>
            <span v-else-if="changeFailed" class="text-danger">{{ $t('common.language_change_failed') }}</span>
            <span v-else-if="showSuccess" class="text-success">{{ $t('common.saved') }}</span>
            <span v-else>{{ locale.toUpperCase() }}</span>
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
                class="fixed z-[100] overflow-hidden rounded-xl border border-border bg-card text-primary shadow-xl"
                :class="props.align === 'start' ? 'origin-top-left' : 'origin-top-right'"
                :style="menuStyle"
            >
                <div class="py-1">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        type="button"
                        @click="handleLanguageChange(lang.code)"
                        :disabled="changing"
                        class="flex w-full items-center px-4 py-3 text-start text-sm transition hover:bg-muted disabled:cursor-wait disabled:opacity-60"
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
