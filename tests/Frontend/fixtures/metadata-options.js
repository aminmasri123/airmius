import { createApp, h } from 'vue'
import { createI18n } from 'vue-i18n'
import axios from 'axios'
import Editor from '../../../resources/js/Components/Clubs/ClubMetadataSection.vue'
import { fieldFeedbackDirective, installFormFeedback } from '../../../resources/js/services/formFeedback'
import '../../../resources/css/app.css'
import '../../../resources/css/form-feedback.css'

window.fields = [{ id: 2, label: 'Trikotgröße', key: 'shirt_size', entity_type: 'member', field_type: 'select', options: ['S', 'M', 'L'], is_active: true, is_sensitive: false, is_required: false, sort_order: 0 }]
window.requests = []
const client = axios.create({ adapter: async config => {
    window.requests.push(config)
    const payload = typeof config.data === 'string' ? JSON.parse(config.data) : config.data
    if (config.method === 'put') window.fields[0] = { id: 2, ...payload }
    if (config.method === 'post') window.fields.push({ id: 3, ...payload })
    return { config, status: config.method === 'post' ? 201 : 200, data: { data: structuredClone({ can_manage: true, can_edit: true, can_delete: false, custom_fields: window.fields, categories: [], number_ranges: [] }) } }
} })
window.axios = client
installFormFeedback({ router: { on: () => () => {} }, axios: client })
createApp({ render: () => h(Editor, { clubId: 7 }) }).use(createI18n({ legacy: false, locale: 'de', messages: {} })).directive('field-feedback', fieldFeedbackDirective).mount('#app')
window.ready = true
