<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentText } from '@/support/learningAssessment'
const props = defineProps({ modelValue: String, courseId: Number, disabled: Boolean })
const emit = defineEmits(['update:modelValue','busy'])
const { locale } = useI18n()
const t = key => assessmentText(locale.value,key)
const busy = ref(false), error = ref('')
const upload = async event => {
    const file = event.target.files?.[0]
    if (!file || busy.value || props.disabled) return
    busy.value = true; error.value = ''; emit('busy',true)
    try {
        const data = new FormData(); data.append('file',file); data.append('purpose','assessment_image')
        const result = await window.axios.post(route('auth.learning.studio.uploads.store',props.courseId),data)
        emit('update:modelValue',result.data.url)
    } catch (failure) { error.value = failure?.response?.data?.errors?.file?.[0] || t('text_or_image') }
    finally { busy.value = false; emit('busy',false); event.target.value = '' }
}
</script>
<template>
    <div class="grid gap-2">
        <label class="text-sm text-secondary">{{ t('image') }}<input :value="modelValue" type="url" maxlength="500" :disabled="disabled || busy" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" @input="$emit('update:modelValue',$event.target.value || null)"></label>
        <label class="cursor-pointer rounded-lg border border-border px-3 py-2 text-center text-sm"><i class="las la-image" aria-hidden="true"></i> {{ t('upload_image') }}<input type="file" accept="image/png,image/jpeg,image/gif,image/webp" :disabled="disabled || busy" class="sr-only" @change="upload"></label>
        <p v-if="busy" role="status">{{ t('uploading') }}</p><p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <img v-if="modelValue" :key="modelValue" :src="modelValue" :alt="t('image_only')" class="max-h-36 max-w-full object-contain" loading="lazy">
    </div>
</template>
