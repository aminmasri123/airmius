import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { mkdir } from 'node:fs/promises'
import { resolve } from 'node:path'
import { createServer } from 'vite'
import vue from '@vitejs/plugin-vue'
import { parse } from '@vue/compiler-sfc'
import { instrumentFormFeedback } from '../../resources/js/support/formFeedbackCompiler.js'

const dir = resolve('artifacts/web-form-feedback-browser')
await mkdir(dir, { recursive: true })
const inertiaResponse = (req, res) => {
    let body = ''
    req.on('data', chunk => { body += chunk })
    req.on('end', () => {
        const name = JSON.parse(body || '{}').name || ''
        if (name === 'offline') return req.socket.destroy()
        if (name === 'server') { res.statusCode = 500; res.end('private server exception'); return }
        res.setHeader('Content-Type', 'application/json')
        res.setHeader('X-Inertia', 'true')
        res.end(JSON.stringify({ component: 'Harness', props: { errors: name === 'reject' ? { name: 'Please choose another name' } : {} }, url: '/tests/Frontend/fixtures/inertia-form-feedback.html', version: null }))
    })
}
const vite = await createServer({ configFile: false, root: process.cwd(), optimizeDeps: { include: ['vue', 'axios', '@inertiajs/vue3', 'vue-i18n'] }, server: { host: '127.0.0.1', port: 43219, strictPort: true, watch: { ignored: ['**/artifacts/**', '**/mobile/**', '**/vendor/**'] } }, plugins: [
    { name: 'inertia-qa-response', configureServer(server) { server.middlewares.use('/__feedback-inertia', inertiaResponse) } },
    { name: 'feedback-test', enforce: 'pre', transform(source, id) {
        if (!id.endsWith('.vue')) return
        const template = parse(source).descriptor.template
        return source.slice(0, template.loc.start.offset) + instrumentFormFeedback(template.content) + source.slice(template.loc.end.offset)
    } }, vue(),
] })

await vite.listen()
const edge = spawn(process.env.AIRMIUS_CHROMIUM || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9437', `--user-data-dir=${dir}/profile`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' })
let socket
let id = 0
const waiting = new Map()
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))
try {
    let target
    for (let i = 0; i < 100; i++) {
        try { const response = await fetch('http://127.0.0.1:9437/json/new?http://127.0.0.1:43219/tests/Frontend/fixtures/form-feedback.html', { method: 'PUT', signal: AbortSignal.timeout(500) }); target = await response.json(); break } catch { await sleep(100) }
    }
    assert.ok(target, 'Headless browser started')
    socket = new WebSocket(target.webSocketDebuggerUrl)
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject })
    socket.onmessage = event => { const result = JSON.parse(event.data); if (result.id) { const entry = waiting.get(result.id); waiting.delete(result.id); result.error ? entry.reject(result.error) : entry.resolve(result.result) } }
    const send = (method, params = {}) => new Promise((resolve, reject) => { const number = ++id; waiting.set(number, { resolve, reject }); socket.send(JSON.stringify({ id: number, method, params })) })
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text)
        return result.result.value
    }
    for (let i = 0; i < 100 && !await evaluate('Boolean(window.ready)'); i++) await sleep(100)
    assert.equal(await evaluate('Boolean(window.ready)'), true, 'Vue harness mounted')
    const results = await evaluate(`(async () => {
        const results = [];
        const check = (condition, label) => { if (!condition) throw new Error(label); results.push(label) };
        const el = id => document.getElementById(id);
        const tick = () => new Promise(r => setTimeout(r, 40));
        const enter = (id, value) => { el(id).value = value; el(id).dispatchEvent(new Event('input', { bubbles:true })); };
        const h = window.feedbackHarness;
        const fail = (status, errors = {}) => { const req = h.requests.shift(); req.reject({ config:req.config, response: status ? { status, data:{errors} } : undefined }); };
        const succeed = () => { const req = h.requests.shift(); req.resolve({config:req.config, status:200, data:{success:true}}); };
        el('save').click(); await tick();
        check(h.requests.length === 0 && el('name').getAttribute('aria-invalid') === 'true', 'native required feedback prevents request');
        check(el('first').textContent.includes('Bitte fülle dieses Feld aus.'), 'readable native field error');
        enter('name', 'My unsaved draft'); enter('email', 'a@example.org'); enter('question-1', 'Second question'); await tick();
        const transfer = new DataTransfer(); transfer.items.add(new File(['draft'], 'draft.txt')); el('file').files = transfer.files;
        el('save').click(); await tick();
        check(el('first').getAttribute('aria-busy') === 'true', 'pending feedback');
        el('save').click(); await tick(); check(h.requests.length === 1, 'duplicate submit blocked while pending');
        fail(422, { name:['Name already taken'], 'questions.1.title':['Please improve this question'], attachment:['Unsupported file'] }); await tick();
        check(el('name').getAttribute('aria-invalid') === 'true', 'API field error assigned');
        check(el('file').getAttribute('aria-invalid') === 'true', 'upload error matched to file input through request payload');
        check(el('question-1').getAttribute('aria-invalid') === 'true' && el('question-0').getAttribute('aria-invalid') !== 'true', 'nested row error assigned to correct question');
        check(el('other-name').getAttribute('aria-invalid') !== 'true', 'other form remains untouched');
        check(el('name').value === 'My unsaved draft' && el('file').files[0].name === 'draft.txt', 'text and file selection survive 422');
        check(el('name').getAttribute('aria-describedby').includes('name-hint'), 'existing accessible hint retained');
        enter('name', 'Corrected name'); await tick(); el('save').click(); await tick(); fail(0); await tick();
        check(el('first').textContent.includes('Keine Verbindung') && el('name').value === 'Corrected name', 'network failure retains draft');
        const expiry = new CustomEvent('airmius:session-expired', { cancelable:true }); window.dispatchEvent(expiry);
        check(expiry.defaultPrevented && el('first').textContent.includes('Sitzung'), 'session expiry keeps unsaved form on screen');
        for (const status of [403,419,500]) { el('save').click(); await tick(); fail(status); await tick(); check(el('name').value === 'Corrected name' && el('first').querySelector('[role=alert]'), status+' retains draft with feedback'); }
        el('save').click(); await tick(); succeed(); await tick();
        check(el('saved').textContent === '1' && el('first').textContent.includes('erfolgreich'), 'retry succeeds and is announced');
        el('save').click(); await tick(); el('save-other').click(); await tick();
        const firstRequest = h.requests.shift(), otherRequest = h.requests.shift();
        otherRequest.reject({config:otherRequest.config,response:{status:422,data:{errors:{name:['Other form error']}}}}); await tick();
        firstRequest.reject({config:firstRequest.config,response:{status:422,data:{errors:{name:['First form error']}}}}); await tick();
        check(el('first').textContent.includes('First form error') && !el('first').textContent.includes('Other form error') && el('other').textContent.includes('Other form error'), 'concurrent responses stay with their originating forms');
        // Inertia retains state on errors and uses the same field mapping.
        el('save').dispatchEvent(new MouseEvent('click',{bubbles:true})); await tick(); fail(0); await tick();
        el('name').dispatchEvent(new MouseEvent('click',{bubbles:true}));
        const button = el('save'); const event = new MouseEvent('click',{bubbles:true}); button.type='button'; button.dispatchEvent(event);
        const visit = { method:'put', data:{name:'Corrected name'}, preserveState:false };
        h.router.emit('before',{visit}); h.router.emit('start',{visit});
        check(visit.preserveState === 'errors', 'Inertia preserves draft on validation failure');
        h.router.emit('error',{errors:{name:'Inertia validation error'}}); await tick();
        check(el('first').textContent.includes('Inertia validation error') && el('name').value === 'Corrected name', 'Inertia field error retains draft');
        h.router.emit('finish',{visit});
        document.documentElement.lang='ar';
        button.type='submit'; el('save').click(); await tick(); fail(0); await tick();
        check(el('first').textContent.includes('تعذر الاتصال'), 'Arabic feedback follows page language');
        return results;
    })()`)
    for (const result of results) console.log(`PASS ${result}`)
    for (const width of [390, 1280]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 500 })
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `No overflow at ${width}px`)
        const capture = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
        const { writeFile } = await import('node:fs/promises')
        await writeFile(`${dir}/${width}.png`, Buffer.from(capture.data, 'base64'))
    }
    console.log('PASS desktop and mobile layouts; screenshots saved')
    await send('Page.navigate', { url: 'http://127.0.0.1:43219/tests/Frontend/fixtures/inertia-form-feedback.html' })
    await sleep(500)
    for (let i = 0; i < 100 && !await evaluate('Boolean(window.ready)'); i++) await sleep(100)
    const inertiaResults = await evaluate(`(async () => {
        const results = [];
        const check = (value, label) => { if (!value) throw Error(label+' '+document.body.textContent.slice(0,1500)); results.push(label) };
        const wait = () => new Promise(resolve => setTimeout(resolve,300));
        const save = async value => { const input=document.getElementById('inertia-name'); input.value=value; input.dispatchEvent(new Event('input',{bubbles:true})); document.getElementById('inertia-save').click(); await wait(); };
        await save('reject');
        check(document.getElementById('inertia-name').value === 'reject', 'real Inertia validation preserves component state');
        check(document.getElementById('inertia').textContent.includes('Please choose another name'), 'real Inertia renders field error');
        await save('server');
        check(document.getElementById('inertia-name').value === 'server' && document.getElementById('inertia').textContent.includes('could not be saved'), 'real Inertia 500 retains draft');
        check(!document.body.textContent.includes('private server exception'), 'technical response is hidden');
        await save('offline');
        check(document.getElementById('inertia-name').value === 'offline' && document.getElementById('inertia').textContent.includes('Unable to connect'), 'real Inertia connection failure retains draft');
        await save('accepted');
        check(document.getElementById('inertia-name').value === '', 'real Inertia success allows intended reset');
        return results;
    })()`)
    for (const result of inertiaResults) console.log(`PASS ${result}`)
} finally { socket?.close(); edge.kill(); await vite.close() }
