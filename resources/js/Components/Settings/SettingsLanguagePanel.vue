<script setup>
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
    <div class="surface-card relative z-20 overflow-visible p-5">
        <h2 class="mb-3 text-sm font-semibold text-secondary">{{ t('Sprache') }}</h2>
        <p class="mb-3 text-sm text-secondary">
            {{ t('settings.language_description') }}
        </p>
        <LanguageDropdown align="start" />
    </div>
</template>
