<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'

const props = defineProps({
    logo_url: String,
    ends_at: String,
    support_url: String,
    scenario: String,
    title: {
        type: String,
        default: 'Airmius ist gerade im Wartemodus',
    },
    message: {
        type: String,
        default: 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.',
    },
    canLogin: {
        type: Boolean,
        default: true,
    },
})

const page = usePage()
const { t } = useI18n()

const defaultTitle = 'Airmius ist gerade im Wartemodus'
const defaultMessage = 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'
const legacyDefaultMessage = 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'

const translatedOrCustom = (value, defaults, key) => {
    const normalized = String(value || '').trim()

    return !normalized || defaults.includes(normalized) ? t(key) : normalized
}

const localizedTitle = computed(() => translatedOrCustom(props.title, [defaultTitle], 'maintenance.title'))
const localizedMessage = computed(() => translatedOrCustom(props.message, [defaultMessage, legacyDefaultMessage], 'maintenance.message'))
</script>

<template>
    <Head :title="localizedTitle" />

    <main data-no-auto-translate class="min-h-screen bg-bg text-primary [overflow-wrap:anywhere]">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-5 sm:px-6 sm:py-10">
            <section class="grid w-full overflow-hidden rounded-lg border border-border bg-card shadow-xl lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]">
                <div class="flex items-center justify-end gap-2 border-b border-border bg-bg/80 px-4 py-3 lg:col-span-2 sm:px-6">
                    <span class="hidden text-xs font-semibold uppercase tracking-wide text-secondary sm:inline">
                        {{ t('maintenance.language_label') }}
                    </span>
                    <LanguageDropdown align="end" />
                </div>

                <div class="min-w-0 p-5 sm:p-8 lg:p-10">
                    <div class="flex min-h-28 items-center [&>a]:max-w-full">
                        <img v-if="logo_url" :src="logo_url" alt="Airmius" class="h-full w-full object-contain" /><AuthenticationCardLogo v-else />
                    </div>

                    <div class="mt-6 max-w-2xl sm:mt-8">
                        <h1 class="text-3xl font-semibold leading-tight text-primary sm:text-4xl xl:text-5xl">
                            {{ localizedTitle }}
                        </h1>
                        <p class="mt-5 text-base leading-7 text-secondary sm:text-lg">
                            {{ localizedMessage }}
                        </p>
                    </div>

                    <p v-if="ends_at && !Number.isNaN(Date.parse(ends_at))" class="mt-6 text-secondary">{{ t('maintenance.available_from') }} {{ new Date(ends_at).toLocaleString(page.props.locale || undefined) }}</p>
                    <a v-if="support_url && /^https?:\/\//i.test(support_url)" :href="support_url" class="mt-5 inline-block btn">{{ t('maintenance.support_status') }}</a>
                    <a href="mailto:contact@airmius.com" class="mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-5 py-3 text-center font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto">
                        <i class="las la-envelope text-xl" aria-hidden="true"></i>
                        {{ t('maintenance.contact_support') }}
                    </a>
                    <p class="mt-2 text-sm text-secondary">{{ t('maintenance.contact_hint') }}</p>
                    <div class="mt-8 flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="btn-primary"
                        >
                            {{ t('maintenance.admin_login') }}
                        </Link>

                        <Link
                            v-else-if="page.props.auth?.user"
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="btn"
                        >
                            {{ t('maintenance.logout') }}
                        </Link>
                    </div>
                </div>

                <aside class="min-w-0 border-t border-border bg-bg p-5 sm:p-8 lg:border-s lg:border-t-0 lg:p-6">
                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                                        <i class="las la-wrench text-xl"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-primary">{{ t('maintenance.updating_title') }}</p>
                                        <p class="text-sm text-secondary">{{ t('maintenance.updating_text') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted text-primary">
                                        <i class="las la-shield-alt text-xl"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-primary">{{ t('maintenance.protected_title') }}</p>
                                        <p class="text-sm text-secondary">{{ t('maintenance.protected_text') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ t('maintenance.status_label') }}</p>
                            
                            <p class="mt-3 text-sm text-secondary">
                                {{ t('maintenance.status_text') }}
                            </p>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </main>
</template>

