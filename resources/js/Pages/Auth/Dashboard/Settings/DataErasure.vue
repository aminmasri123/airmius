<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    usesSocialLogin: Boolean,
    accountEmail: {
        type: String,
        default: '',
    },
})

const { t } = useI18n()
const codeSent = ref(false)
const form = useForm({
    identity: '',
    categories: [],
    code: '',
})

const hasAllCategories = computed(() => form.categories.length === props.categories.length)
const identityLabel = computed(() => t(props.usesSocialLogin
    ? 'data_erasure.identity.email_label'
    : 'data_erasure.identity.password_label'))
const identityHint = computed(() => props.usesSocialLogin
    ? t('data_erasure.identity.social_hint', {
        email: props.accountEmail || t('data_erasure.identity.account_email_fallback'),
    })
    : t('data_erasure.identity.password_hint'))

watch(() => [...form.categories], () => {
    if (!codeSent.value) return

    codeSent.value = false
    form.code = ''
    form.clearErrors('code')
})

const toggleAll = () => {
    form.categories = hasAllCategories.value ? [] : props.categories.map((category) => category.key)
    form.clearErrors('categories')
}

const requestCode = () => {
    if (!form.categories.length) {
        form.setError('categories', t('data_erasure.select_required'))
        return
    }

    form.post(route('auth.settings.privacy.erasure.code'), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = true
            form.clearErrors('code')
        },
    })
}

const eraseData = () => {
    if (!form.categories.length) {
        form.setError('categories', t('data_erasure.select_required'))
        return
    }

    form.post(route('auth.settings.privacy.erasure.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = false
            form.reset('identity', 'categories', 'code')
        },
    })
}
</script>

<template>
    <Head :title="t('data_erasure.meta_title')" />

    <div class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ t('data_erasure.eyebrow') }}</p>
                <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">{{ t('data_erasure.title') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                    {{ t('data_erasure.intro') }}
                </p>
            </div>
            <Link :href="route('auth.settings')" class="btn-secondary">{{ t('data_erasure.back') }}</Link>
        </div>

        <div class="rounded-xl border border-error/40 bg-error/10 p-4 text-sm text-primary">
            <p class="font-semibold text-error">{{ t('data_erasure.warning_title') }}</p>
            <p class="mt-1 leading-6 text-secondary">
                {{ t('data_erasure.warning_body') }}
            </p>
        </div>

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ t('data_erasure.select_title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ t('data_erasure.select_hint') }}</p>
                </div>
                <button type="button" class="btn-secondary" :disabled="form.processing" @click="toggleAll">
                    {{ hasAllCategories ? t('data_erasure.clear_all') : t('data_erasure.select_all') }}
                </button>
            </div>

            <p v-if="form.errors.categories" class="mt-3 text-sm text-error" role="alert">{{ form.errors.categories }}</p>

            <div class="mt-4 space-y-3">
                <label
                    v-for="category in categories"
                    :key="category.key"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-bg p-4 transition hover:border-buttonPrimary/60"
                    :class="form.categories.includes(category.key) ? 'border-buttonPrimary bg-buttonPrimary/5' : ''"
                >
                    <input v-model="form.categories" :value="category.key" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        <span class="block font-semibold text-primary">{{ category.label }}</span>
                        <span class="mt-1 block text-sm leading-6 text-secondary">{{ category.description }}</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="surface-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ t('data_erasure.confirm_title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-secondary">
                {{ identityHint }} {{ t('data_erasure.identity.code_hint') }}
            </p>

            <div class="mt-4 max-w-xl">
                <label class="block text-sm font-semibold text-primary" for="data-erasure-identity">{{ identityLabel }}</label>
                <input
                    id="data-erasure-identity"
                    v-model="form.identity"
                    :type="usesSocialLogin ? 'email' : 'password'"
                    :autocomplete="usesSocialLogin ? 'email' : 'current-password'"
                    class="input mt-2"
                    :placeholder="usesSocialLogin ? accountEmail : t('data_erasure.identity.password_placeholder')"
                    :disabled="form.processing"
                    @input="form.clearErrors('identity')"
                >
                <p v-if="form.errors.identity" class="mt-2 text-sm text-error" role="alert">{{ form.errors.identity }}</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" class="btn-primary" :disabled="form.processing" @click="requestCode">
                    {{ codeSent ? t('data_erasure.resend_code') : t('data_erasure.send_code') }}
                </button>
            </div>
        </section>

        <section v-if="codeSent" class="surface-card border border-warning/30 p-5">
            <h2 class="text-lg font-semibold text-primary">{{ t('data_erasure.erase_title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-secondary">
                {{ t('data_erasure.erase_intro') }}
            </p>

            <div class="mt-4 max-w-sm">
                <label class="block text-sm font-semibold text-primary" for="data-erasure-code">{{ t('data_erasure.code_label') }}</label>
                <input
                    id="data-erasure-code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    dir="ltr"
                    class="input mt-2 tracking-[0.35em]"
                    :placeholder="t('data_erasure.code_placeholder')"
                    :disabled="form.processing"
                    @input="form.clearErrors('code')"
                >
                <p v-if="form.errors.code" class="mt-2 text-sm text-error" role="alert">{{ form.errors.code }}</p>
            </div>

            <button type="button" class="mt-4 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white transition hover:bg-error/90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing || !form.code" @click="eraseData">
                {{ t('data_erasure.erase_button') }}
            </button>
        </section>

        <section class="rounded-xl border border-border bg-bg p-5 text-sm leading-6 text-secondary">
            <h2 class="font-semibold text-primary">{{ t('data_erasure.retained_title') }}</h2>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                <li>{{ t('data_erasure.retained_financial') }}</li>
                <li>{{ t('data_erasure.retained_organization') }}</li>
                <li>{{ t('data_erasure.retained_social_login') }}</li>
            </ul>
            <Link :href="route('legal.data-erasure')" class="mt-3 inline-block text-buttonPrimary hover:underline">{{ t('data_erasure.public_information') }}</Link>
        </section>
    </div>
</template>
