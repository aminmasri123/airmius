import {test} from 'node:test'
import assert from 'node:assert/strict'
import {readFile} from 'node:fs/promises'
import {parse,compileScript} from '@vue/compiler-sfc'
import {createSSRApp} from 'vue'
import {renderToString} from '@vue/server-renderer'
const root=new URL('../../',import.meta.url)
const copy=JSON.parse(await readFile(new URL('resources/data/learning-experience-copy.json',root),'utf8'))
const moduleUrl=source=>`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const helper=(await readFile(new URL('resources/js/support/learningExperience.js',root),'utf8')).replace(/import copy from [^\n]+/,`const copy=${JSON.stringify(copy)}`)
const {nextLearningLesson,learningText}=await import(moduleUrl(helper))
for(const locale of ['de','en','fr','ar']) test(`learner labels have complete translations in ${locale}`,()=>{
    assert.deepEqual(Object.keys(copy[locale]),Object.keys(copy.en))
    for(const key of Object.keys(copy.en)) assert.ok(learningText(locale,key).trim())
})
test('continue chooses first open unfinished lesson and never a drip-locked lesson',()=>{
    const lessons=[{id:1,completed:true},{id:2,drip_locked:true},{id:3,locked:true},{id:4,completed:false},{id:5,completed:false}]
    assert.equal(nextLearningLesson(lessons).id,4)
    assert.equal(nextLearningLesson(lessons.slice(0,3)),null)
})
const {descriptor}=parse(await readFile(new URL('resources/js/Components/Learning/LearningCourseCover.vue',root),'utf8'))
const script=compileScript(descriptor,{id:'course-cover-test',inlineTemplate:true}).content.replaceAll('from "vue"',`from '${import.meta.resolve('vue')}'`).replaceAll("from 'vue'",`from '${import.meta.resolve('vue')}'`)
const Cover=(await import(moduleUrl(script))).default
test('course covers render meaningful image labels and graceful missing-image fallback',async()=>{
    const image=await renderToString(createSSRApp(Cover,{src:'https://example.test/course.png',title:'Course & skills'}))
    assert.ok(image.includes('alt="Course &amp; skills"'))
    assert.ok(image.includes('loading="lazy"'))
    const fallback=await renderToString(createSSRApp(Cover,{title:'Course'}))
    assert.ok(fallback.includes('la-book-open'))
    assert.ok(!fallback.includes('<img'))
})
