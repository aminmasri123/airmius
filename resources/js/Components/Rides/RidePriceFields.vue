<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ridePricingText } from '@/support/ridePricing'
const props = defineProps({ form: { type: Object, required: true }, catalog: { type: Object, default: () => ({}) }, editing: Boolean })
const { locale } = useI18n()
const search = ref('')
const text = key => ridePricingText(locale.value, key)
const currencies = computed(() => Object.keys(props.catalog.currencies || {}).filter(code => code === props.form.cost_currency || code.includes(search.value.trim().toUpperCase())))
</script>

<template>
    <fieldset class="space-y-3 rounded-xl border border-border p-4 md:col-span-2">
        <legend class="px-2 font-semibold text-primary">{{ text('mode') }}</legend>
        <label class="block">
            <span class="sr-only">{{ text('mode') }}</span>
            <select v-model="form.cost_mode" required class="w-full rounded-lg border-border bg-inputBg text-primary">
                <option value="" disabled>{{ text('choose_mode') }}</option>
                <option value="free">{{ text('free') }}</option>
                <option value="contribution">{{ text('contribution') }}</option>
            </select>
        </label>
        <p v-if="form.errors.cost_mode" class="text-sm text-error">{{ form.errors.cost_mode }}</p>
        <template v-if="form.cost_mode === 'contribution'">
            <label class="block text-sm text-secondary">{{ text('amount') }}
                <input v-model="form.cost_amount" type="text" inputmode="decimal" required maxlength="30" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
            </label>
            <p v-if="form.errors.cost_amount" class="text-sm text-error">{{ form.errors.cost_amount }}</p>
            <label class="block text-sm text-secondary">{{ text('search_currency') }}
                <input v-model="search" type="search" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
            </label>
            <label class="block text-sm text-secondary">{{ text('currency') }}
                <select v-model="form.cost_currency" required class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="" disabled>{{ text('currency') }}</option>
                    <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                </select>
            </label>
            <p v-if="form.errors.cost_currency" class="text-sm text-error">{{ form.errors.cost_currency }}</p>
            <p class="text-sm text-secondary">{{ text('country_hint') }}</p>
            <p class="text-sm text-secondary">{{ text('all_included') }}</p>
            <p class="text-sm text-secondary">{{ text('direct_payment') }}</p>
        </template>
        <p v-if="editing" class="text-sm text-secondary">{{ text('update_warning') }}</p>
    </fieldset>
</template>
