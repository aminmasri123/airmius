import copy from '../../data/learning-assessment-copy.json'
export const assessmentText = (locale, key) => (copy[String(locale).split('-')[0]] || copy.en)[key] || key
export const assessmentTypes = ['short_text', 'long_text', 'multiple_choice', 'single_choice', 'ordering']
let sequence = 0
const key = () => `item_${Date.now()}_${++sequence}`
export const newAssessmentOption = () => ({ id: key(), text: '', image_url: null })
export const newAssessmentQuestion = (type = 'single_choice') => ({ id: null, editor_key: key(), question: '', question_type: type, image_url: null, options: [newAssessmentOption(),newAssessmentOption()], correct_options: [], explanation: '' })
export const editableAssessmentQuestions = raw => (raw || []).map(question => {
    const q = JSON.parse(JSON.stringify(question))
    const options = (q.options || []).map((option,index) => typeof option === 'string' ? { id: `option_${index}`, text: option, image_url: null } : option)
    return { ...q, editor_key: key(), question_type: q.question_type || 'multiple_choice', options, correct_options: (q.correct_options || []).map(value => { const index = (q.options || []).findIndex(o => typeof o === 'string' && o === value); return index < 0 ? value : `option_${index}` }) }
})
export const assessmentOptions = q => (q.options || []).map(o => typeof o === 'string' ? { id: o, text: o } : o)
export const assessmentEditorError = (questions, solutions) => {
    if (solutions && !questions.length) return 'answer_required'
    for (const q of questions) {
        if (!q.question?.trim() && !q.image_url?.trim()) return 'text_or_image'
        if (['single_choice','multiple_choice','ordering'].includes(q.question_type)) {
            if ((q.options || []).length < 2) return 'two_options'
            if (q.options.some(o => !o.text?.trim() && !o.image_url?.trim())) return 'text_or_image'
            if (solutions && q.question_type !== 'ordering' && !q.correct_options?.length) return 'correct_required'
            if (solutions && q.question_type === 'single_choice' && q.correct_options?.length !== 1) return 'one_correct'
        } else if (solutions && q.question_type === 'short_text' && !q.correct_options?.some(v => v.trim())) return 'correct_required'
    }
    return null
}
