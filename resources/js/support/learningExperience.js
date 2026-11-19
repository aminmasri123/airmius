import copy from '../../data/learning-experience-copy.json'
export const learningText = (locale, key) => (copy[String(locale).split('-')[0]] || copy.en)[key] || key
export const nextLearningLesson = lessons => lessons.find(l => !l.completed && !l.locked && !l.drip_locked) || null
