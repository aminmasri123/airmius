<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    OPEN_COOKIE_PREFERENCES_EVENT,
    readBrowserCookieConsent,
    saveBrowserCookieConsent,
} from '@/services/privacyConsent'

const { t } = useI18n()
const isOpen = ref(false)
const hasDecision = ref(false)
const showDetails = ref(false)
const analytics = ref(false)
const marketing = ref(false)
const dialog = ref(null)
const previousFocus = ref(null)
const previousBodyOverflow = ref('')

const isDetailedDialog = computed(() => hasDecision.value || showDetails.value)

const hydrate = () => {
    const stored = readBrowserCookieConsent()
    hasDecision.value = Boolean(stored)
    analytics.value = Boolean(stored?.analytics)
    marketing.value = Boolean(stored?.marketing)
    isOpen.value = !stored
}

const focusDialog = async () => {
    await nextTick()
    dialog.value?.focus()
}

const setPageLocked = (locked) => {
    if (locked) {
        previousBodyOverflow.value = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        return
    }

    document.body.style.overflow = previousBodyOverflow.value
}

const openPreferences = () => {
    previousFocus.value = document.activeElement
    showDetails.value = true
    isOpen.value = true
    setPageLocked(true)
    void focusDialog()
}

const closePreferences = () => {
    if (!hasDecision.value) {
        return
    }

    isOpen.value = false
    showDetails.value = false
    setPageLocked(false)
    previousFocus.value?.focus?.()
}

const persist = (nextAnalytics, nextMarketing) => {
    const consent = saveBrowserCookieConsent({
        analytics: nextAnalytics,
        marketing: nextMarketing,
    })

    analytics.value = consent.analytics
    marketing.value = consent.marketing
    hasDecision.value = true
    isOpen.value = false
    showDetails.value = false
    setPageLocked(false)
    previousFocus.value?.focus?.()
}

const acceptNecessary = () => persist(false, false)
const acceptAll = () => persist(true, true)
const saveSelection = () => persist(analytics.value, marketing.value)

const handleKeydown = (event) => {
    if (!isOpen.value) {
        return
    }

    if (event.key === 'Escape') {
        closePreferences()
        return
    }

    if (event.key !== 'Tab' || !dialog.value) {
        return
    }

    const focusable = Array.from(dialog.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ))

    if (!focusable.length) {
        event.preventDefault()
        dialog.value.focus()
        return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

onMounted(() => {
    hydrate()
    window.addEventListener(OPEN_COOKIE_PREFERENCES_EVENT, openPreferences)
    window.addEventListener('keydown', handleKeydown)

    if (isOpen.value) {
        setPageLocked(true)
        void focusDialog()
    }
})

onBeforeUnmount(() => {
    window.removeEventListener(OPEN_COOKIE_PREFERENCES_EVENT, openPreferences)
    window.removeEventListener('keydown', handleKeydown)
    setPageLocked(false)
})
</script>

<template>
    <button
        v-if="hasDecision && !isOpen"
        type="button"
        class="fixed bottom-4 start-4 z-[90] flex h-11 w-11 items-center justify-center rounded-full border border-border bg-card text-xl text-primary shadow-lg transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary focus:ring-offset-2 focus:ring-offset-bg"
        :aria-label="t('cookie_consent.open')"
        :title="t('cookie_consent.open')"
        @click="openPreferences"
    >
        <i class="las la-shield-alt" aria-hidden="true"></i>
    </button>

    <div
        v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-end bg-black/45 p-3 sm:items-center sm:justify-center sm:p-6"
        role="presentation"
        @click.self="closePreferences"
    >
        <section
            ref="dialog"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="isDetailedDialog ? 'cookie-preferences-title' : 'cookie-consent-title'"
            tabindex="-1"
            class="max-h-[calc(100dvh-1.5rem)] w-full overflow-y-auto rounded-lg border border-border bg-card p-5 text-primary shadow-2xl outline-none sm:max-w-2xl sm:p-6"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary/15 text-2xl text-buttonPrimary">
                    <i class="las la-shield-alt" aria-hidden="true"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 :id="isDetailedDialog ? 'cookie-preferences-title' : 'cookie-consent-title'" class="text-xl font-bold">
                        {{ t(isDetailedDialog ? 'cookie_consent.preferences_title' : 'cookie_consent.title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">
                        {{ t('cookie_consent.description') }}
                    </p>
                </div>
                <button
                    v-if="hasDecision"
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl text-secondary hover:bg-muted hover:text-primary"
                    :aria-label="t('cookie_consent.close')"
                    :title="t('cookie_consent.close')"
                    @click="closePreferences"
                >
                    <i class="las la-times" aria-hidden="true"></i>
                </button>
            </div>

            <div v-if="isDetailedDialog" class="mt-5 divide-y divide-border border-y border-border">
                <div class="flex items-start justify-between gap-4 py-4">
                    <div>
                        <h3 class="font-semibold">{{ t('cookie_consent.necessary_title') }}</h3>
                        <p class="mt-1 text-sm leading-5 text-secondary">{{ t('cookie_consent.necessary_help') }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-success/15 px-3 py-1 text-xs font-semibold text-success">
                        {{ t('cookie_consent.always_active') }}
                    </span>
                </div>

                <label class="flex cursor-pointer items-start justify-between gap-4 py-4">
                    <span>
                        <span class="block font-semibold">{{ t('cookie_consent.analytics_title') }}</span>
                        <span class="mt-1 block text-sm leading-5 text-secondary">{{ t('cookie_consent.analytics_help') }}</span>
                    </span>
                    <input v-model="analytics" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-border bg-inputBg text-buttonPrimary focus:ring-buttonPrimary">
                </label>

                <label class="flex cursor-pointer items-start justify-between gap-4 py-4">
                    <span>
                        <span class="block font-semibold">{{ t('cookie_consent.marketing_title') }}</span>
                        <span class="mt-1 block text-sm leading-5 text-secondary">{{ t('cookie_consent.marketing_help') }}</span>
                    </span>
                    <input v-model="marketing" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-border bg-inputBg text-buttonPrimary focus:ring-buttonPrimary">
                </label>
            </div>

            <p class="mt-4 text-xs leading-5 text-secondary">
                {{ t('cookie_consent.change_later') }}
                <Link :href="route('legal.cookies')" class="font-semibold text-link underline underline-offset-2">
                    {{ t('cookie_consent.more_information') }}
                </Link>
            </p>

            <div class="mt-5 grid gap-2 sm:grid-cols-3">
                <button type="button" class="min-h-11 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="acceptNecessary">
                    {{ t('cookie_consent.necessary_only') }}
                </button>
                <button
                    v-if="!isDetailedDialog"
                    type="button"
                    class="min-h-11 rounded-lg border border-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonPrimary hover:bg-buttonPrimary/10"
                    @click="showDetails = true"
                >
                    {{ t('cookie_consent.customize') }}
                </button>
                <button
                    v-else
                    type="button"
                    class="min-h-11 rounded-lg border border-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonPrimary hover:bg-buttonPrimary/10"
                    @click="saveSelection"
                >
                    {{ t('cookie_consent.save_selection') }}
                </button>
                <button type="button" class="min-h-11 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:opacity-90" @click="acceptAll">
                    {{ t('cookie_consent.accept_all') }}
                </button>
            </div>
        </section>
    </div>
</template>
