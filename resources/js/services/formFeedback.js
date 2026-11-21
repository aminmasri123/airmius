import { formFeedbackText as text, formFailureText, safeFieldMessage } from './formFeedbackMessages'

const fields = new WeakMap()
const fieldHosts = new WeakMap()
let sequence = 0
const visible = el => el?.isConnected && el.getClientRects().length > 0
const feedbackVisible = el => visible(el) || el?.type === 'file' && visible(el.parentElement)
const controls = scope => [...(scope?.querySelectorAll('[data-feedback-field], input, select, textarea') || [])]
const nameOf = el => el.dataset.feedbackField || el.name?.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '')
const matches = (name, key) => name === key || Boolean(name) && key.startsWith(`${name}.`)
const matchesField = (el, key) => matches(nameOf(el), key) || matches(`${el.dataset.feedbackModel}.${nameOf(el)}`, key)

function clearField(el) {
    const state = fields.get(el)
    if (!state?.error) return
    state.error?.remove()
    state.error = null
    if (el.getAttribute('aria-invalid') === 'true') {
        if (state.invalid == null) el.removeAttribute('aria-invalid')
        else el.setAttribute('aria-invalid', state.invalid)
    }
    const described = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== state.id)
    if (described.length) el.setAttribute('aria-describedby', described.join(' '))
    else el.removeAttribute('aria-describedby')
}

function fieldError(el, message) {
    if (!feedbackVisible(el)) return
    let state = fields.get(el)
    if (!state) {
        state = { id: `form-feedback-field-${++sequence}`, invalid: el.getAttribute('aria-invalid') }
        fields.set(el, state)
    }
    if (state.error?.textContent === message) return
    clearField(el)
    const error = el.ownerDocument.createElement('p')
    error.id = state.id
    error.className = 'form-feedback-field'
    error.setAttribute('role', 'alert')
    error.textContent = message
    el.insertAdjacentElement('afterend', error)
    state.error = error
    el.setAttribute('aria-invalid', 'true')
    const described = el.getAttribute('aria-describedby') || ''
    el.setAttribute('aria-describedby', `${described} ${state.id}`.trim())
}

export const fieldFeedbackDirective = {
    mounted(el, binding) { updateField(el, binding) },
    updated(el, binding) { updateField(el, binding) },
    beforeUnmount(el) { const input = fieldControl(el); if (input) { clearField(input); fields.delete(input) } },
}
const fieldControl = el => el.matches('input, select, textarea, [role="combobox"]') ? el
    : el.querySelector('[role="combobox"], button[aria-haspopup="listbox"]') || el.querySelector('input, select, textarea')
function updateField(el, { value }) {
    if (!value) return
    const host = el
    el = fieldControl(host)
    if (!el) return
    if (host === el && fieldHosts.has(el) && fieldHosts.get(el) !== el) return
    fieldHosts.set(el, host)
    el.dataset.feedbackField = el.getAttribute('data-feedback-name') || value.field || value.model
    el.dataset.feedbackModel = value.model
    const entry = Object.entries(value.errors || {}).find(([key]) => matchesField(el, key))
    const message = entry ? safeFieldMessage(entry[1]) : null
    const state = fields.get(el)
    if (message && message !== state?.dismissed) {
        fieldError(el, message)
        if (fields.has(el)) fields.get(el).boundError = message
    } else if (!message && state?.boundError) {
        clearField(el)
        state.boundError = null
        state.dismissed = null
    }
}

function nativeMessage(el) {
    const v = el.validity
    if (v.valueMissing) return text('required')
    if (v.typeMismatch && el.type === 'email') return text('email')
    if (v.badInput || v.stepMismatch) return text('number')
    if (v.rangeUnderflow) return `${text('min')} (${el.min})`
    if (v.rangeOverflow) return `${text('max')} (${el.max})`
    if (v.tooShort) return text('short')
    if (v.tooLong) return text('long')
    return text('invalid')
}

// One installation for the entire SPA, including guest/authentication pages.
// Each request retains its own origin. No drafts are written to browser storage.
export function installFormFeedback({ router, axios, document: doc = document, window: win = window }) {
    let origin = null
    let currentVisit = null
    const pending = new WeakMap()
    const states = new WeakMap()
    const dirty = new Set()
    const revisions = new WeakMap()
    const global = doc.createElement('div')
    global.className = 'form-feedback-global'
    doc.body.append(global)
    const scopeFor = target => {
        if (target?.form) return target.form
        const scope = target?.closest?.('form, [role="dialog"], dialog, section, article')
        if (scope) return scope
        let parent = target?.parentElement
        while (parent && parent !== doc.body) {
            if (controls(parent).length) return parent
            parent = parent.parentElement
        }
        return null
    }
    const remember = event => {
        if (event.type === 'click' && !event.target.closest?.('button, [role="button"], input[type="submit"]')) return
        const scope = scopeFor(event.target)
        origin = scope ? { scope, at: Date.now(), submitter: event.submitter || event.target.closest?.('button') } : null
    }
    const context = data => {
        if (!origin || Date.now() - origin.at > 15000 || !origin.scope.isConnected) return null
        const list = controls(origin.scope).filter(feedbackVisible)
        const keys = Object.keys(data || {})
        const relevant = !list.length || keys.some(key => list.some(el => nameOf(el)?.split('.')[0] === key))
        const result = relevant || origin.submitter ? { ...origin, revision: revisions.get(origin.scope) || 0, aliases: new Map() } : null
        if (result && list.some(el => el.type === 'file')) {
            const visit = (value, key, depth = 0) => {
                if (depth > 8 || value == null) return
                if (value instanceof File) {
                    for (const el of list) if (el.files && [...el.files].includes(value)) result.aliases.set(el, key)
                } else if (value instanceof FormData) {
                    for (const [key, item] of value.entries()) visit(item, key.replace(/\[([^\]]+)\]/g, '.$1'), depth + 1)
                } else if (typeof value === 'object') {
                    for (const [part, item] of Object.entries(value)) visit(item, key ? `${key}.${part}` : part, depth + 1)
                }
            }
            visit(data, '')
        }
        if (result) origin = null
        return result
    }
    const status = (ctx, kind, message) => {
        const scope = ctx?.scope?.isConnected ? ctx.scope : global
        let state = states.get(scope)
        if (!state) {
            const box = doc.createElement('div')
            box.className = 'form-feedback-status'
            const content = doc.createElement('span')
            const close = doc.createElement('button')
            close.type = 'button'
            close.textContent = '×'
            close.setAttribute('aria-label', text('close'))
            close.onclick = () => { box.hidden = true }
            box.append(content, close)
            scope.prepend(box)
            state = { box, content, count: 0 }
            states.set(scope, state)
        }
        state.box.hidden = false
        state.box.dataset.kind = kind
        state.box.setAttribute('role', kind === 'error' ? 'alert' : 'status')
        state.box.setAttribute('aria-live', kind === 'error' ? 'assertive' : 'polite')
        state.content.textContent = message
        if (ctx) setTimeout(() => { if (!scope.isConnected) status(null, kind, message) }, 0)
        return state
    }
    const start = ctx => {
        if (!ctx) return
        dirty.add(ctx.scope)
        status(ctx, 'pending', text('saving')).count++
        ctx.scope.setAttribute('aria-busy', 'true')
    }
    const finish = ctx => {
        if (!ctx) return
        const state = states.get(ctx.scope)
        if (state) state.count = Math.max(0, state.count - 1)
        if (!state?.count) ctx.scope.removeAttribute('aria-busy')
    }
    const errors = (ctx, bag, code = 422) => {
        const scope = ctx?.scope || global
        let first = null
        const list = controls(scope).filter(feedbackVisible)
        const assigned = (el, key) => matchesField(el, key) || matches(ctx?.aliases?.get(el), key)
        for (const [key, value] of Object.entries(bag || {})) {
            for (const el of list.filter(el => assigned(el, key))) {
                fieldError(el, safeFieldMessage(value))
                const target = visible(el) ? el : fields.get(el)?.error
                if (target && target !== el) target.tabIndex = -1
                first ||= target
            }
        }
        const unmatched = Object.entries(bag || {}).filter(([key]) => !list.some(el => assigned(el, key)))
        status(ctx, 'error', [formFailureText(code), ...unmatched.map(([, v]) => safeFieldMessage(v))].join(' '))
        const target = first || states.get(scope)?.box
        if (target) {
            if (!first) target.tabIndex = -1
            target.focus({ preventScroll: true })
            target.scrollIntoView?.({ block: 'nearest', behavior: 'auto' })
        }
    }
    const invalid = event => {
        const el = event.target
        if (!el.validity) return
        fieldError(el, nativeMessage(el))
        const scope = scopeFor(el)
        if (scope) status({ scope }, 'error', text('check'))
    }
    const input = event => {
        const state = fields.get(event.target)
        if (state) state.dismissed = state.boundError
        clearField(event.target)
        const scope = scopeFor(event.target)
        if (scope) { dirty.add(scope); revisions.set(scope, (revisions.get(scope) || 0) + 1) }
    }
    const saved = (ctx, code = 200) => {
        if ((revisions.get(ctx.scope) || 0) === ctx.revision) dirty.delete(ctx.scope)
        for (const el of controls(ctx.scope)) clearField(el)
        status(ctx, 'success', text(code === 202 ? 'accepted' : 'success'))
    }
    const sessionExpired = event => {
        for (const scope of dirty) if (!scope.isConnected) dirty.delete(scope)
        if (!dirty.size) return
        event.preventDefault()
        for (const scope of dirty) status({ scope }, 'error', text('session'))
    }
    win.addEventListener('airmius:session-expired', sessionExpired)
    const submit = event => {
        remember(event)
        const state = states.get(event.target)
        if (state?.count) { event.preventDefault(); event.stopImmediatePropagation() }
        else for (const el of controls(event.target)) clearField(el)
    }
    doc.addEventListener('click', remember, true)
    doc.addEventListener('submit', submit, true)
    doc.addEventListener('invalid', invalid, true)
    doc.addEventListener('input', input, true)
    doc.addEventListener('change', input, true)
    const mutation = method => ['post', 'put', 'patch', 'delete'].includes(String(method || '').toLowerCase())
    const requestId = axios.interceptors.request.use(config => {
        if (mutation(config.method) && config.formFeedback !== false) {
            const ctx = context(config.data)
            if (ctx) { pending.set(config, ctx); start(ctx) }
        }
        return config
    })
    const response = (result, failed = false) => {
        const config = result.config
        const ctx = config && pending.get(config)
        if (ctx) {
            pending.delete(config)
            finish(ctx)
            const res = failed ? result.response : result
            if (failed || res.status >= 400 || res.data?.success === false) errors(ctx, res?.data?.errors, res?.status || 0)
            else saved(ctx, res.status)
        }
        return failed ? Promise.reject(result) : result
    }
    const responseId = axios.interceptors.response.use(result => response(result), error => response(error, true))
    const stops = [
        router.on('before', event => {
            const visit = event.detail.visit
            if (mutation(visit.method) && visit.preserveState === false) visit.preserveState = 'errors'
        }),
        router.on('start', event => {
            const visit = event.detail.visit
            if (!mutation(visit.method)) return
            currentVisit = context(visit.data)
            start(currentVisit)
        }),
        router.on('error', event => { if (currentVisit) { event.formFeedbackHandled = true; errors(currentVisit, event.detail.errors) } }),
        router.on('invalid', event => {
            if (!currentVisit) return
            event.formFeedbackHandled = true
            event.preventDefault()
            errors(currentVisit, event.detail.response?.data?.errors, event.detail.response?.status)
        }),
        router.on('exception', event => {
            if (!currentVisit) return
            event.formFeedbackHandled = true
            event.preventDefault()
            errors(currentVisit, {}, 0)
        }),
        router.on('success', event => {
            if (!currentVisit) return
            if (event.detail.page.props.flash?.error) errors(currentVisit, {}, 500)
            else saved(currentVisit)
        }),
        router.on('finish', () => { finish(currentVisit); currentVisit = null }),
    ]
    return () => {
        for (const stop of stops) stop()
        axios.interceptors.request.eject(requestId)
        axios.interceptors.response.eject(responseId)
        doc.removeEventListener('click', remember, true)
        doc.removeEventListener('submit', submit, true)
        doc.removeEventListener('invalid', invalid, true)
        doc.removeEventListener('input', input, true)
        doc.removeEventListener('change', input, true)
        win.removeEventListener('airmius:session-expired', sessionExpired)
        global.remove()
    }
}
