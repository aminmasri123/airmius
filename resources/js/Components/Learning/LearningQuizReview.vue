<script setup>
import {ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {assessmentText} from '@/support/learningAssessment'
import LearningAnswersSummary from './LearningAnswersSummary.vue'
const props=defineProps({attempt:Object})
defineEmits(['grade'])
const {locale}=useI18n();const t=key=>assessmentText(locale.value,key)
const grades=ref({}),feedback=ref('')
const manual=()=> (props.attempt.question_snapshot||[]).filter(q=>q.question_type==='long_text')
</script>
<template>
    <details class="rounded-lg border border-warning/40 p-4"><summary class="cursor-pointer font-semibold">{{ attempt.user?.name }} · {{ t('pending_review') }}</summary><div class="mt-4 grid gap-4"><LearningAnswersSummary :questions="attempt.question_snapshot" :answers="attempt.answers"/><fieldset v-for="q in manual()" :key="q.id" class="grid gap-2"><legend>{{ q.question }}</legend><label class="flex gap-2"><input v-model="grades[q.id]" type="radio" :name="'grade-'+attempt.id+'-'+q.id" :value="true">{{ t('correct') }}</label><label class="flex gap-2"><input v-model="grades[q.id]" type="radio" :name="'grade-'+attempt.id+'-'+q.id" :value="false">{{ t('incorrect') }}</label></fieldset><label class="grid gap-2">{{ t('feedback') }}<textarea v-model="feedback" class="rounded-lg border-border bg-inputBg" rows="3"/></label><button type="button" :disabled="manual().some(q=>grades[q.id]===undefined)" class="rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary disabled:opacity-40" @click="$emit('grade',{manual_grades:grades,feedback})">{{ t('grade') }}</button></div></details>
</template>
