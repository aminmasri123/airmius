<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentTypes,assessmentText,newAssessmentQuestion,newAssessmentOption } from '@/support/learningAssessment'
import AssessmentImageField from './AssessmentImageField.vue'
const props = defineProps({ modelValue: {type:Array,default:()=>[]}, solutions:Boolean, courseId:Number })
const emit = defineEmits(['update:modelValue','busy'])
const {locale} = useI18n(); const t = key => assessmentText(locale.value,key)
const busy = ref(false)
const change = mutation => { mutation(); emit('update:modelValue',[...props.modelValue]) }
const moved = (list,index,delta) => { const item=list.splice(index,1)[0];list.splice(index+delta,0,item) }
const typeChanged = q => { q.correct_options=[]; if (['single_choice','multiple_choice','ordering'].includes(q.question_type) && q.options.length<2) q.options=[newAssessmentOption(),newAssessmentOption()] }
const mark = (q,id,checked) => change(()=>{ q.correct_options=checked ? (q.question_type==='single_choice' ? [id] : [...q.correct_options,id]) : q.correct_options.filter(v=>v!==id) })
const onBusy = value => {busy.value=value;emit('busy',value)}
</script>
<template>
    <div class="grid gap-5">
        <fieldset v-for="(q,index) in modelValue" :key="q.editor_key || q.id" class="grid gap-4 rounded-xl border border-border bg-card p-4">
            <legend class="px-2 font-semibold">{{ t('question') }} {{ index+1 }}</legend>
            <div class="flex justify-end gap-2"><button type="button" :disabled="busy || index===0" :aria-label="t('up')" @click="change(()=>moved(modelValue,index,-1))">↑</button><button type="button" :disabled="busy" class="text-error" @click="change(()=>modelValue.splice(index,1))">{{ t('remove_question') }}</button></div>
            <label class="grid gap-2 text-sm">{{ t('question') }}<textarea v-model="q.question" rows="2" maxlength="3000" class="rounded-lg border-border bg-inputBg" /></label>
            <AssessmentImageField v-model="q.image_url" :course-id="courseId" :disabled="busy" @busy="onBusy" />
            <label class="grid gap-2 text-sm">{{ t('question_type') }}<select v-model="q.question_type" :disabled="busy" class="rounded-lg border-border bg-inputBg" @change="typeChanged(q)"><option v-for="type in assessmentTypes" :key="type" :value="type">{{ t(type) }}</option></select></label>
            <template v-if="['single_choice','multiple_choice','ordering'].includes(q.question_type)">
                <p v-if="q.question_type==='ordering'" class="text-sm text-secondary">{{ t('ordering_hint') }}</p>
                <div v-for="(option,j) in q.options" :key="option.id" class="grid gap-3 rounded-lg border border-border p-3">
                    <label class="grid gap-2 text-sm">{{ t('option') }} {{ j+1 }}<input v-model="option.text" maxlength="1000" class="rounded-lg border-border bg-inputBg" /></label>
                    <AssessmentImageField v-model="option.image_url" :course-id="courseId" :disabled="busy" @busy="onBusy" />
                    <label v-if="solutions && q.question_type!=='ordering'" class="flex items-center gap-2"><input :type="q.question_type==='single_choice'?'radio':'checkbox'" :name="'correct-'+q.editor_key" :checked="q.correct_options.includes(option.id)" @change="mark(q,option.id,$event.target.checked)">{{ t('correct') }}</label>
                    <div class="flex flex-wrap justify-end gap-3"><button v-if="q.question_type==='ordering'" type="button" :disabled="busy || j===0" :aria-label="t('up')" @click="change(()=>moved(q.options,j,-1))">↑</button><button type="button" :disabled="busy" class="text-error" @click="change(()=>{q.options.splice(j,1);q.correct_options=q.correct_options.filter(v=>v!==option.id)})">{{ t('remove_option') }}</button></div>
                </div>
                <button type="button" :disabled="busy || q.options.length>=20" class="rounded-lg border border-border px-3 py-2" @click="change(()=>q.options.push(newAssessmentOption()))">{{ t('add_option') }}</button>
            </template>
            <label v-if="solutions && q.question_type==='short_text'" class="grid gap-2 text-sm">{{ t('accepted_answers') }}<textarea :value="q.correct_options.join('\n')" rows="3" class="rounded-lg border-border bg-inputBg" @input="q.correct_options=$event.target.value.split('\n')" /></label>
            <p v-if="q.question_type==='long_text'" class="text-sm text-secondary">{{ t(solutions?'manual_hint':'assignment_manual') }}</p>
            <label v-if="solutions" class="grid gap-2 text-sm">{{ t('explanation') }}<textarea v-model="q.explanation" rows="2" class="rounded-lg border-border bg-inputBg" /></label>
        </fieldset>
        <button type="button" :disabled="busy || modelValue.length>=50" class="rounded-lg border border-border px-4 py-3" @click="$emit('update:modelValue',[...modelValue,newAssessmentQuestion(solutions?'single_choice':'long_text')])">{{ t('add_question') }}</button>
    </div>
</template>
