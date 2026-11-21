<script setup>
import { useForm } from '@inertiajs/vue3'
import TextInput from '../../../resources/js/Components/TextInput.vue'
const form = useForm({ name: '' })
const save = () => form.post('/__feedback-inertia', { preserveState: false, onSuccess: () => form.reset() })
</script>
<template><form id="inertia" @submit.prevent="save"><label>Name<TextInput id="inertia-name" v-model="form.name" /></label><button id="inertia-save">Save</button></form></template>
