<script setup>
defineProps({ model: { type: Object, required: true }, fields: { type: Array, required: true }, disabled: Boolean })
</script>

<template>
    <div class="grid min-w-0 gap-3 sm:grid-cols-2">
        <label v-for="field in fields" :key="field.key" class="min-w-0 text-sm font-semibold text-primary" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
            {{ field.label }}
            <select v-if="field.options" v-model="model[field.key]" :disabled="disabled" :required="field.required" class="mt-1 w-full rounded border-border bg-inputBg text-primary">
                <option v-if="!field.required" :value="null">-</option>
                <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <textarea v-else-if="field.type === 'textarea'" v-model="model[field.key]" :disabled="disabled" :required="field.required" :maxlength="field.maxlength || 5000" rows="3" class="mt-1 w-full rounded border-border bg-inputBg text-primary" />
            <input v-else v-model="model[field.key]" :disabled="disabled" :type="field.type || 'text'" :min="field.min" :max="field.max" :maxlength="field.maxlength || 200" :required="field.required" class="mt-1 w-full rounded border-border bg-inputBg text-primary" />
        </label>
    </div>
</template>
