<script setup>
import {useI18n} from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import {learningText} from '@/support/learningExperience'
defineProps({target:Object,busy:Boolean,error:String})
defineEmits(['confirm','close'])
const {locale}=useI18n()
const ex=key=>learningText(locale.value,key)
</script>
<template>
  <Modal :show="Boolean(target)" :closeable="!busy" @close="$emit('close')">
    <div v-if="target" class="grid gap-4 p-5">
      <h2 class="text-xl font-bold">{{ ex(target.action) }}</h2>
      <p class="font-semibold">{{ target.title }}</p>
      <p>{{ ex(target.action+'_hint') }}</p>
      <p v-if="error" role="alert" class="text-error">{{ error }}</p>
      <div class="flex flex-wrap justify-end gap-3"><button type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="$emit('close')">{{ ex('cancel') }}</button><button type="button" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary" @click="$emit('confirm')">{{ ex('confirm') }}</button></div>
    </div>
  </Modal>
</template>
