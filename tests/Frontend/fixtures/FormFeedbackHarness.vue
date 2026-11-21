<script setup>
import { inject, reactive, ref } from 'vue'
const client = inject('client')
const form = reactive({ name: '', email: '', questions: [{ title: '' }, { title: '' }], errors: {} })
const other = reactive({ name: 'Other form' })
const saved = ref(0)
const save = async () => {
    try { await client.post('/save', { name: form.name, email: form.email, questions: form.questions, attachment: document.getElementById('file').files[0] }); saved.value++ } catch { /* Feedback must preserve this draft. */ }
}
const saveOther = () => client.post('/other', { name: other.name }).catch(() => {})
window.draft = form
</script>
<template>
    <main>
        <form id="first" @submit.prevent="save">
            <label>Name<input id="name" v-model="form.name" required aria-describedby="name-hint"></label>
            <span id="name-hint">A helpful hint</span>
            <label>Email<input id="email" type="email" v-model="form.email"></label>
            <div v-for="(question, index) in form.questions" :key="index">
                <label>Question {{ index + 1 }}<input :id="`question-${index}`" v-model="question.title"></label>
            </div>
            <label>File<input id="file" type="file"></label>
            <button id="save">Save</button>
            <span id="saved">{{ saved }}</span>
        </form>
        <form id="other" @submit.prevent="saveOther"><label>Other<input id="other-name" v-model="other.name"></label><button id="save-other">Save other</button></form>
    </main>
</template>
<style>body { margin: 12px; font-family: sans-serif; } main { max-width: 700px; margin: auto; } form { margin-bottom: 24px; } label { display: block; margin-block: 12px; } input { display: block; max-width: 100%; box-sizing: border-box; } button { min-height: 44px; } </style>
