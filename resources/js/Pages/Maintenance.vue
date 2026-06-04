<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'

const props = defineProps({
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

    <main data-no-auto-translate class="min-h-screen bg-bg text-primary">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-5 py-10">
            <section class="grid w-full overflow-hidden rounded-lg border border-border bg-card shadow-xl md:grid-cols-[1fr_24rem]">
                <div class="flex items-center justify-end gap-2 border-b border-border bg-bg/80 px-4 py-3 md:col-span-2 sm:px-6">
                    <span class="hidden text-xs font-semibold uppercase tracking-wide text-secondary sm:inline">
                        {{ t('maintenance.language_label') }}
                    </span>
                    <LanguageDropdown align="end" />
                </div>

                <div class="p-6 sm:p-10">
                    <div class="h-24 w-24">
                        <AuthenticationCardLogo />
                    </div>

                    <div class="mt-10 max-w-2xl">
                        <p class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ t('maintenance.eyebrow') }}</p>
                        <h1 class="mt-3 text-3xl font-semibold text-primary sm:text-5xl">
                            {{ localizedTitle }}
                        </h1>
                        <p class="mt-5 text-base leading-7 text-secondary sm:text-lg">
                            {{ localizedMessage }}
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
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

                <aside class="border-t border-border bg-bg p-6 md:border-l md:border-t-0">
                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                                        <i class="las la-wrench text-xl"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-primary">{{ t('maintenance.updating_title') }}</p>
                                        <p class="text-sm text-secondary">{{ t('maintenance.updating_text') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-primary">
                                        <i class="las la-shield-alt text-xl"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-primary">{{ t('maintenance.protected_title') }}</p>
                                        <p class="text-sm text-secondary">{{ t('maintenance.protected_text') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ t('maintenance.status_label') }}</p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full w-3/4 rounded-full bg-buttonPrimary"></div>
                            </div>
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

