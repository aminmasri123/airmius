import { createApp, h } from 'vue'
import { createI18n } from 'vue-i18n'
import axios from 'axios'
import Editor from '../../../resources/js/Components/Clubs/ClubMetadataSubjectEditor.vue'
import { fieldFeedbackDirective, installFormFeedback } from '../../../resources/js/services/formFeedback'
import '../../../resources/css/app.css'
import '../../../resources/css/form-feedback.css'

const readOnly = new URLSearchParams(location.search).has('readonly')
const fields = Array.from({ length: 40 }, (_, i) => ({ id: i + 1, key: `field_${i}`, label: `Feld ${i + 1}`, field_type: 'text', is_required: false, is_active: true, value: `Wert ${i + 1}` }))
const requests = []
const client = axios.create({ adapter: async config => {
    requests.push(config)
    if (config.method === 'put' && (window.failSave || readOnly)) throw { config, response: { status: readOnly ? 403 : 422, data: { errors: { 'values.1': ['Bitte korrigieren'] } } } }
    const data = config.url.endsWith('/metadata')
        ? { can_manage: !readOnly, can_edit: !readOnly, can_delete: false, custom_fields: [], categories: [], number_ranges: [] }
        : { can_edit: !readOnly, fields, categories: [{ id: 1, name: 'Leistung', is_active: true, selected: true }] }
    return { config, status: 200, data: { data } }
} })
window.axios = client
window.requests = requests
installFormFeedback({ router: { on: () => () => {} }, axios: client })
createApp({ render: () => h(Editor, { direct: true, clubId: 7, subjectType: 'member', subjectId: 9, subjectLabel: 'Amin Masri' }) })
    .use(createI18n({ legacy: false, locale: 'de', messages: {} }))
    .directive('field-feedback', fieldFeedbackDirective).mount('#app')
window.ready = true
