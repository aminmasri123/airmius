<script setup>
import axios from 'axios'
import { ref, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRideLocationCopy } from '@/support/rideLocations'
const props = defineProps({ modelValue: { type: String, default: '' }, label: String, area: Boolean, required: Boolean })
const emit = defineEmits(['update:modelValue', 'select'])
const { locale } = useI18n()
const c = useRideLocationCopy()
const results = ref([]), error = ref(''), loading = ref(false), queried = ref(false)
let timer, request, version = 0
function changed(value) {
    emit('update:modelValue', value)
    clearTimeout(timer); request?.abort(); const current = ++version
    results.value = []; error.value = ''; queried.value = false; loading.value = false
    if (value.trim().length < 3) return
    timer = setTimeout(async () => {
        request = new AbortController(); loading.value = true
        try {
            const response = await axios.get(route('auth.rides.locations'), { params: { q: value.trim(), area: props.area ? 1 : 0, locale: locale.value.split('-')[0] }, signal: request.signal })
            if (current === version) { results.value = response.data.data; queried.value = true }
        } catch (failure) { if (current === version && !axios.isCancel(failure)) error.value = c('unavailable') }
        finally { if (current === version) loading.value = false }
    }, 450)
}
function select(item) {
    clearTimeout(timer); request?.abort(); ++version
    results.value = []; queried.value = false; loading.value = false
    emit('update:modelValue', item.label); emit('select', item)
}
watch(() => props.modelValue, value => { if (!value) { clearTimeout(timer); request?.abort(); ++version; results.value = []; error.value = ''; loading.value = false; queried.value = false } })
onBeforeUnmount(() => { clearTimeout(timer); request?.abort(); ++version })
</script>
<template>
    <div class="space-y-2">
        <label class="block text-sm font-semibold text-primary">{{ label }}<input :value="modelValue" :required="required" maxlength="200" autocomplete="off" :placeholder="c(area ? 'area' : 'address')" class="mt-2 w-full rounded-xl border-border bg-inputBg" @input="changed($event.target.value)" @keydown.esc="results = []" /></label>
        <p v-if="loading" role="status" class="text-sm text-secondary">{{ c('loading') }}</p>
        <ul v-if="results.length" class="max-h-56 overflow-y-auto rounded-xl border border-border bg-card" :aria-label="label">
            <li v-for="(item, index) in results" :key="index"><button type="button" class="w-full border-b border-border px-3 py-3 text-start text-sm text-primary hover:bg-inputBg focus:bg-inputBg" @click="select(item)">{{ item.label }}</button></li>
        </ul>
        <p v-else-if="queried" role="status" class="text-sm text-secondary">{{ c('empty') }}</p>
        <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
    </div>
</template>
