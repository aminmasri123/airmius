<script setup>
import {reactive,watch} from 'vue'
import {useI18n} from 'vue-i18n'
import {assessmentText} from '@/support/learningAssessment'
import LearningAssessmentAnswers from './LearningAssessmentAnswers.vue'
const props=defineProps({assignment:Object,canSubmit:Boolean})
defineEmits(['submit'])
const {locale}=useI18n();const t=key=>assessmentText(locale.value,key)
const payload=reactive({body:'',attachment_url:'',answers:{}})
watch(()=>[props.assignment.id,props.assignment.revision],()=>Object.assign(payload,{body:props.assignment.submission?.body||'',attachment_url:props.assignment.submission?.attachment_url||'',answers:props.assignment.submission?.answers||{}}),{immediate:true})
</script>
<template>
    <article :id="`assignment-${assignment.id}`" class="rounded-lg border border-border bg-bg p-4"><h3 class="font-semibold">{{ assignment.title }}</h3><p class="mt-2 whitespace-pre-wrap text-sm">{{ assignment.instructions }}</p><p v-if="assignment.submission" class="mt-2 text-sm">{{ assignment.submission.status }} · {{ assignment.submission.feedback }}</p><form v-if="canSubmit && !assignment.locked" class="mt-4 grid gap-4" @submit.prevent="$emit('submit',{...payload,assignment_revision:assignment.revision})"><LearningAssessmentAnswers v-model="payload.answers" :questions="assignment.questions||[]"/><textarea v-if="!assignment.questions?.length" v-model="payload.body" rows="4" maxlength="10000" :aria-label="t('answer_placeholder')" class="rounded-lg border-border bg-inputBg"/><input v-if="assignment.grading_mode!=='automatic'" v-model="payload.attachment_url" type="url" maxlength="500" placeholder="https://" :aria-label="t('image')" class="rounded-lg border-border bg-inputBg"><button type="submit" class="justify-self-start rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary">{{ t('submit') }}</button></form></article>
</template>
