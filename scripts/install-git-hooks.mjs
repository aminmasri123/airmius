import { execFileSync, spawnSync } from 'node:child_process'

const result = spawnSync('git', ['config', '--get', 'core.hooksPath'], { encoding: 'utf8' })
if (result.error || (result.status !== 0 && result.status !== 1)) {
    throw result.error ?? new Error(result.stderr)
}
const current = result.stdout.trim()
if (current && current !== '.githooks') {
    throw new Error(`Existing hooksPath ${current}. Keep the existing hooks and integrate the pre-push check explicitly.`)
}
execFileSync('git', ['config', '--local', 'core.hooksPath', '.githooks'])
console.log('Local pre-push checks enabled for this checkout, including VS Code pushes.')
