<script setup>
import { computed, ref } from 'vue'
import { sportSearch, useSuitabilityCopy } from '@/support/exerciseSuitability'

const props = defineProps({ modelValue: { type: Array, default: () => [] }, sports: { type: Array, default: () => [] }, filter: Boolean })
const emit = defineEmits(['update:modelValue'])
const sc = useSuitabilityCopy()
const open = ref(false)
const search = ref('')
// Preserve legacy or inactive sports already assigned to this exercise.
const options = computed(() => [...new Set([...props.sports.map((sport) => sport.value), ...props.modelValue])].sort((a, b) => a.localeCompare(b)))
const matches = computed(() => options.value.filter((sport) => sportSearch(sport).includes(sportSearch(search.value.trim()))))
const toggle = (sport) => {
    emit('update:modelValue', props.filter ? [sport] : props.modelValue.includes(sport) ? props.modelValue.filter((value) => value !== sport) : [...props.modelValue, sport])
    if (props.filter) open.value = false
}
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm font-semibold text-primary">{{ sc(filter ? 'allSports' : 'suitable') }}</p>
        <button type="button" :aria-expanded="open" class="flex w-full items-center justify-between gap-3 rounded-xl border border-border bg-inputBg px-4 py-3 text-start text-primary" @click="open = !open">
            <span>{{ modelValue.length ? modelValue.join(', ') : sc(filter ? 'allSports' : 'general') }}</span><span aria-hidden="true">{{ open ? '−' : '+' }}</span>
        </button>
        <p v-if="!filter" class="text-sm text-secondary">{{ sc('hint') }}</p>
        <div v-if="open" class="space-y-3 rounded-xl border border-border p-4">
            <label class="block text-sm text-primary">{{ sc('search') }}<input v-model="search" type="search" class="mt-2 w-full rounded-xl border-border bg-inputBg" /></label>
            <button type="button" :aria-pressed="!modelValue.length" class="w-full rounded-lg border border-border px-3 py-2 text-start font-semibold text-primary" @click="emit('update:modelValue', []); if (filter) open = false">{{ !modelValue.length ? '✓ ' : '' }}{{ sc(filter ? 'allSports' : 'general') }}</button>
            <div class="max-h-64 overflow-y-auto overscroll-contain">
                <label v-for="sport in matches" :key="sport" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-3 text-sm text-primary hover:bg-inputBg">
                    <input :type="filter ? 'radio' : 'checkbox'" :checked="modelValue.includes(sport)" @change="toggle(sport)" /><span>{{ sport }}</span>
                </label>
                <p v-if="!matches.length" role="status" class="py-3 text-sm text-secondary">{{ sc('empty') }}</p>
            </div>
            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" @click="open = false">{{ sc('done') }}</button>
        </div>
    </div>
</template>
