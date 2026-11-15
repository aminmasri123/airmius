import test from 'node:test'
import assert from 'node:assert/strict'
import { localizedExercise } from '../../resources/js/support/exerciseContent.js'

const exercise = {
    title: 'Squat', instructions: 'Descendez', original_locale: 'de',
    original_content: { title: 'Kniebeuge', description: 'Beschreibung', instructions: 'Absenken', notes: null, todos: ['Atmen'] },
    translations: { fr: { title: 'Squat', instructions: 'Descendez' }, en: { title: 'Squat', instructions: ' ' } },
    explanation_images: [{ id: 'start', url: '/start.jpg', caption: 'Départ', texts: { de: { caption: 'Start' }, fr: { caption: 'Départ' } } }],
}

test('partial translations retain original instructions and tasks', () => {
    const result = localizedExercise(exercise, 'en-US')
    assert.equal(result.title, 'Squat')
    assert.equal(result.instructions, 'Absenken')
    assert.deepEqual(result.todos, ['Atmen'])
    assert.equal(result.uses_original_fallback, true)
})

test('switching away from a translated view resolves against original content', () => {
    const result = localizedExercise(exercise, 'ar')
    assert.equal(result.title, 'Kniebeuge')
    assert.equal(result.instructions, 'Absenken')
    assert.equal(result.explanation_images[0].caption, 'Start')
})

test('image caption never falls back to an unrelated previously displayed translation', () => {
    const result = localizedExercise({ ...exercise, explanation_images: [{ id: 'image', caption: 'Français', texts: { fr: { caption: 'Français' } } }] }, 'ar')
    assert.equal(result.explanation_images[0].caption, '')
})

test('original language ignores stale translation entries', () => {
    const result = localizedExercise({ ...exercise, translations: { de: { title: 'Stale' } } }, 'de')
    assert.equal(result.title, 'Kniebeuge')
    assert.equal(result.uses_original_fallback, false)
})

test('translated tasks become a list, while legacy exercises remain readable', () => {
    assert.deepEqual(localizedExercise({ ...exercise, translations: { fr: { todos: 'Respirez\nRépétez' } } }, 'fr').todos, ['Respirez', 'Répétez'])
    assert.equal(localizedExercise({ title: 'Legacy' }, 'ar').title, 'Legacy')
})
