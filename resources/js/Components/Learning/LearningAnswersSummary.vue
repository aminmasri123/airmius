<script setup>
import {assessmentOptions} from '@/support/learningAssessment'
defineProps({questions:{type:Array,default:()=>[]},answers:{type:Object,default:()=>({})}})
</script>
<template>
    <div class="grid gap-4"><div v-for="q in questions" :key="q.id"><p class="font-semibold">{{ q.question }}</p><img v-if="q.image_url" :src="q.image_url" alt="" class="max-h-32 max-w-full object-contain"><ol v-if="Array.isArray(answers[q.id])" class="list-inside list-decimal"><li v-for="id in answers[q.id]" :key="id"><template v-for="option in assessmentOptions(q).filter(o=>o.id===id)" :key="option.id">{{ option.text }}<img v-if="option.image_url" :src="option.image_url" alt="" class="max-h-24 max-w-full object-contain"></template></li></ol><p v-else class="whitespace-pre-wrap">{{ answers[q.id] }}</p></div></div>
</template>
