import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import axios from 'axios'
import { createI18n } from 'vue-i18n'
import Harness from './InertiaFormFeedbackHarness.vue'
import { fieldFeedbackDirective, installFormFeedback } from '../../../resources/js/services/formFeedback'
import '../../../resources/css/form-feedback.css'

installFormFeedback({ router, axios })
await createInertiaApp({
    page: { component: 'Harness', props: { errors: {} }, url: location.pathname, version: null },
    resolve: () => Harness,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).use(createI18n({ legacy: false, locale: 'en', messages: {} })).directive('field-feedback', fieldFeedbackDirective).mount(el),
})
window.ready = true
