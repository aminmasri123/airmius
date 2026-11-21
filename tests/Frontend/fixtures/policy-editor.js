import { createApp, h } from 'vue'
import { createI18n } from 'vue-i18n'
import axios from 'axios'
import Editor from '../../../resources/js/Components/Clubs/ClubPolicyDocumentsSection.vue'
import { fieldFeedbackDirective, installFormFeedback } from '../../../resources/js/services/formFeedback'
import '../../../resources/css/app.css'
import '../../../resources/css/form-feedback.css'
window.uploads = []; window.writes = []; window.failSave = true
const client = axios.create({ adapter: async config => {
    if (config.method === 'get' && config.url === '/api/v1/uploads') return { config, status: 200, data: { data: [{ id: 9, display_name: 'existing.pdf' }] } }
    if (config.method === 'post' && config.url === '/api/v1/uploads') {
        window.uploads.push({ type: config.data.get('policy_document_type'), scope: config.data.get('scope'), club: config.data.get('club_id') })
        return { config, status: 201, data: { data: { id: 17, display_name: 'new.pdf', folder_id: 5 } } }
    }
    if (config.method === 'post') {
        window.writes.push(JSON.parse(config.data))
        if (window.failSave) { window.failSave = false; throw { config, response: { status: 422, data: { errors: { version_label: ['Version already exists'] } } } } }
    }
    return { config, status: 200, data: { data: { documents: [], can_manage: true, can_edit: true, can_delete: false, can_download: true } } }
} })
window.axios = client
installFormFeedback({ router: { on: () => () => {} }, axios: client })
createApp({ render: () => h(Editor, { clubId: 7 }) }).use(createI18n({ legacy: false, locale: 'de', messages: {} })).directive('field-feedback', fieldFeedbackDirective).mount('#app')
window.ready = true
