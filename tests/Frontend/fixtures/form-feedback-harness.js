import { createApp } from 'vue'
import axios from 'axios'
import Harness from './FormFeedbackHarness.vue'
import { fieldFeedbackDirective, installFormFeedback } from '../../../resources/js/services/formFeedback'
import '../../../resources/css/form-feedback.css'

const listeners = new Map()
const router = {
    on(name, fn) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(fn); return () => listeners.get(name).delete(fn) },
    emit(name, detail = {}) { const e = { detail, preventDefault() { this.prevented = true } }; for (const fn of listeners.get(name) || []) fn(e); return e },
}
const requests = []
const client = axios.create({ adapter: config => new Promise((resolve, reject) => requests.push({ config, resolve, reject })) })
installFormFeedback({ router, axios: client })
window.feedbackHarness = { router, requests, client }
createApp(Harness).provide('client', client).directive('field-feedback', fieldFeedbackDirective).mount('#app')
window.ready = true
