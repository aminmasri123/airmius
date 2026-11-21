<script setup>
import { computed } from 'vue'
import definitions from '@/marketplace-taxonomy.json'

const props = defineProps({
    form: { type: Object, required: true },
    taxonomy: { type: Object, default: () => definitions },
    buyer: Boolean,
    translate: { type: Function, default: (text) => text },
})

const children = computed(() => props.taxonomy.groups?.[props.form.catalog_group]?.children || {})

function changeGroup(event) {
    props.form.catalog_group = event.target.value
    props.form.catalog_subcategory = ''
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="grid gap-1 text-sm font-semibold text-primary">
            {{ translate('Hauptkategorie') }}
            <select :value="form.catalog_group" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary" @change="changeGroup">
                <option value="">{{ translate(buyer ? 'Alle Hauptkategorien' : 'Hauptkategorie wählen') }}</option>
                <option v-for="(group, key) in taxonomy.groups" :key="key" :value="key">{{ translate(group.label) }}</option>
            </select>
            <span v-if="form.errors?.catalog_group" class="text-sm text-error">{{ form.errors.catalog_group }}</span>
        </label>
        <label class="grid gap-1 text-sm font-semibold text-primary">
            {{ translate('Unterkategorie') }}
            <select v-model="form.catalog_subcategory" :disabled="!form.catalog_group" :required="!buyer && !!form.catalog_group" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary disabled:opacity-60">
                <option value="">{{ translate(buyer ? 'Alle Unterkategorien' : 'Unterkategorie wählen') }}</option>
                <option v-for="(label, key) in children" :key="key" :value="key">{{ translate(label) }}</option>
            </select>
            <span v-if="form.errors?.catalog_subcategory" class="text-sm text-error">{{ form.errors.catalog_subcategory }}</span>
        </label>
        <label v-if="buyer" class="grid gap-1 text-sm font-semibold text-primary sm:col-span-2">
            {{ translate('Sportart') }}
            <select v-model="form.sport" class="w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                <option value="">{{ translate('Alle Sportarten') }}</option>
                <option v-for="(label, key) in taxonomy.sports" :key="key" :value="key">{{ translate(label) }}</option>
            </select>
        </label>
        <fieldset v-else class="sm:col-span-2">
            <legend class="mb-2 text-sm font-semibold text-primary">{{ translate('Sportarten') }}</legend>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                <label v-for="(label, key) in taxonomy.sports" :key="key" class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="form.sports" type="checkbox" :value="key" class="rounded border-border bg-inputBg">
                    {{ translate(label) }}
                </label>
            </div>
            <p v-if="form.errors?.sports" class="text-sm text-error">{{ form.errors.sports }}</p>
        </fieldset>
    </div>
</template>
