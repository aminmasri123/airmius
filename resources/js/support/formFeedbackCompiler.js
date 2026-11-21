import { parseExpression } from '@babel/parser'
import { parse as parseTemplate } from '@vue/compiler-dom'

// Applied by Vue's template compiler, so new forms get the same feedback too.
// Keep model paths (including repeated rows), without storing any field values.
export function createFormFeedbackTransform() {
    const aliases = new Map()
    const path = (expression, source) => {
        if (expression.type === 'Identifier') return aliases.get(expression.name) || { root: expression.name, parts: [] }
        if (expression.type === 'CallExpression') return { root: source.slice(expression.start, expression.end), parts: [] }
        if (expression.type !== 'MemberExpression' && expression.type !== 'OptionalMemberExpression') return null
        const parent = path(expression.object, source)
        if (!parent) return null
        const property = expression.computed
            ? expression.property.type === 'StringLiteral' || expression.property.type === 'NumericLiteral'
                ? JSON.stringify(expression.property.value)
                : source.slice(expression.property.start, expression.property.end)
            : JSON.stringify(expression.property.name)
        return property == null ? null : { root: parent.root, parts: [...parent.parts, property] }
    }
    return (node) => {
        if (node.type !== 1) return
        const previous = new Map(aliases)
        const loop = node.props.find(p => p.type === 7 && p.name === 'for')?.exp?.content
        const match = loop?.match(/^\s*\(?\s*(\w+)\s*(?:,\s*(\w+)\s*)?\)?\s+(?:in|of)\s+(.+)$/)
        if (match) {
            try {
                const source = path(parseExpression(match[3]), match[3])
                if (source) aliases.set(match[1], { root: source.root, parts: [...source.parts, match[2] || `(${match[3]}).indexOf(${match[1]})`] })
            } catch { /* Unusual expressions still get native validation feedback. */ }
        }
        const model = node.props.find(p => p.type === 7 && p.name === 'model')?.exp?.content
        if (model && ['input', 'select', 'textarea', 'TextInput', 'Checkbox', 'DateInput', 'CountrySelect', 'SearchableSelect', 'RideLocationField'].includes(node.tag) && !node.props.some(p => p.type === 7 && p.name === 'field-feedback')) {
            try {
                const expression = parseExpression(model)
                const field = path(expression, model)
                const owner = expression.object ? model.slice(expression.object.start, expression.object.end) : null
                const localField = field?.parts.at(-1)
                if (field) node.props.push({
                    type: 7, name: 'field-feedback', arg: undefined, modifiers: [], loc: node.loc,
                    exp: { type: 4, isStatic: false, constType: 0, loc: node.loc,
                        content: `{ field: ${owner ? `(${owner})?.errors ? String(${localField}) : ` : ''}[${field.parts.join(',')}].join('.'), model: ${JSON.stringify(field.root)}, errors: ${owner ? `(${owner})?.errors || ` : ''}${field.root}?.errors }` },
                })
            } catch { /* Do not change the meaning of unsupported model expressions. */ }
        }
        return () => { aliases.clear(); for (const [key, value] of previous) aliases.set(key, value) }
    }
}

export function instrumentFormFeedback(template) {
    const ast = parseTemplate(template)
    const transform = createFormFeedbackTransform()
    const edits = []
    const walk = node => {
        const original = node.props?.length
        const exit = transform(node)
        if (node.props?.length > original) {
            const expression = node.props.at(-1).exp.content.replaceAll('&', '&amp;').replaceAll('"', '&quot;')
            edits.push({ at: node.loc.start.offset + node.tag.length + 1, content: ` v-field-feedback="${expression}"` })
        }
        for (const child of node.children || []) walk(child)
        exit?.()
    }
    walk(ast)
    for (const edit of edits.sort((a, b) => b.at - a.at)) template = template.slice(0, edit.at) + edit.content + template.slice(edit.at)
    return template
}
