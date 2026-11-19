<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {assessmentText,assessmentOptions} from '@/support/learningAssessment'
const props=defineProps({questions:{type:Array,default:()=>[]},modelValue:{type:Object,default:()=>({})}})
const emit=defineEmits(['update:modelValue'])
const {locale}=useI18n();const t=key=>assessmentText(locale.value,key)
const set=(q,value)=>emit('update:modelValue',{...props.modelValue,[q.id]:value})
const chosen=q=>Array.isArray(props.modelValue[q.id])?props.modelValue[q.id]:[]
const toggle=(q,id,checked)=>set(q,checked?(q.question_type==='single_choice'?[id]:[...chosen(q),id]):chosen(q).filter(v=>v!==id))
const order=q=>chosen(q).length?chosen(q):assessmentOptions(q).map(o=>o.id)
const move=(q,i,delta)=>{const list=[...order(q)];const item=list.splice(i,1)[0];list.splice(i+delta,0,item);set(q,list)}
watch(()=>props.questions,()=>{ const value={...props.modelValue};let changed=false;for(const q of props.questions)if(q.question_type==='ordering'&&!value[q.id]){value[q.id]=assessmentOptions(q).map(o=>o.id);changed=true}if(changed)emit('update:modelValue',value) },{immediate:true})
</script>
<template>
    <div class="grid gap-6">
        <fieldset v-for="q in questions" :key="q.id" class="grid gap-3">
            <legend class="font-semibold">{{ q.question }}</legend>
            <img v-if="q.image_url" :src="q.image_url" :alt="t('image_only')" class="max-h-40 max-w-full object-contain">
            <textarea v-if="q.question_type==='long_text'" :value="modelValue[q.id]||''" rows="5" maxlength="10000" :aria-label="q.question||t('answer_placeholder')" class="rounded-lg border-border bg-inputBg" @input="set(q,$event.target.value)" />
            <input v-else-if="q.question_type==='short_text'" :value="modelValue[q.id]||''" maxlength="1000" :aria-label="q.question||t('answer_placeholder')" class="rounded-lg border-border bg-inputBg" @input="set(q,$event.target.value)">
            <ol v-else-if="q.question_type==='ordering'" class="grid gap-2">
                <li v-for="(id,i) in order(q)" :key="id" class="flex items-center gap-3 rounded-lg border border-border p-3"><span>{{ i+1 }}.</span><div class="min-w-0 flex-1"><template v-for="option in assessmentOptions(q).filter(o=>o.id===id)" :key="option.id"><span>{{ option.text }}</span><img v-if="option.image_url" :src="option.image_url" :alt="t('image_only')" class="max-h-28 max-w-full object-contain"></template></div><button type="button" :disabled="i===0" :aria-label="t('up')" @click="move(q,i,-1)">↑</button><button type="button" :disabled="i===order(q).length-1" :aria-label="t('down')" @click="move(q,i,1)">↓</button></li>
            </ol>
            <template v-else><label v-for="option in assessmentOptions(q)" :key="option.id" class="flex items-start gap-3 rounded-lg border border-border p-3"><input :type="q.question_type==='single_choice'?'radio':'checkbox'" :name="'answer-'+q.id" :checked="chosen(q).includes(option.id)" class="mt-1" @change="toggle(q,option.id,$event.target.checked)"><span>{{ option.text }}<img v-if="option.image_url" :src="option.image_url" :alt="t('image_only')" class="max-h-28 max-w-full object-contain"></span></label></template>
        </fieldset>
    </div>
</template>
