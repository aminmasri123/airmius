import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { execFileSync, spawnSync } from 'node:child_process'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const tools = path.resolve(root, '..', 'airmius-tools')
const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8' }).trim()
const php = process.env.AIRMIUS_PHP || (fs.existsSync(path.join(tools, 'php', 'php.exe'))
    ? path.join(tools, 'php', 'php.exe') : 'php')
const temporaryRoot = path.resolve(os.tmpdir())
const environment = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', BROADCAST_CONNECTION: 'log' }

function run(command, args, cwd = root, env = environment) {
    const result = spawnSync(command, args, { cwd, env, stdio: 'inherit' })
    if (result.error) throw new Error(`Cannot run ${command}: ${result.error.message}`)
    if (result.status !== 0) throw new Error(`${path.basename(command)} ${args.join(' ')} failed (${result.status ?? result.signal}).`)
}

let snapshot
function removeSnapshot() {
    if (path.dirname(snapshot) !== temporaryRoot || !path.basename(snapshot).startsWith('airmius-prepush-')) {
        throw new Error('Unexpected temporary snapshot path. Cleanup stopped.')
    }
    fs.rmSync(snapshot, { recursive: true, force: true })
    snapshot = undefined
}

try {
    let revisions = ['HEAD']
    if (process.argv.includes('--hook')) {
        const updates = fs.readFileSync(0, 'utf8').trim().split(/\r?\n/).filter(Boolean)
        revisions = [...new Set(updates.map(line => line.split(/\s+/)[1]).filter(sha => sha && !/^0+$/.test(sha)))]
    }
    if (!revisions.length) {
        console.log('No commits to publish. Local build checks skipped.')
        process.exit(0)
    }
    console.log('Checking committed snapshots before push. The first run can take several minutes.')
    run(php, ['-r', 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'])
    if (!fs.existsSync(path.join(root, 'vendor', 'autoload.php')) || !fs.existsSync(path.join(root, 'node_modules', 'vite', 'bin', 'vite.js'))) {
        throw new Error('Install project dependencies first with composer install and npm ci.')
    }
    for (const revision of revisions) {
        const commit = git('rev-parse', '--verify', `${revision}^{commit}`)
        console.log(`\nChecking commit ${commit}`)
        for (const lock of ['composer.lock', 'package-lock.json']) {
            const committed = execFileSync('git', ['show', `${commit}:${lock}`], { cwd: root })
            if (!committed.equals(fs.readFileSync(path.join(root, lock)))) {
                throw new Error(`${lock} differs from the pushed commit. Check out that commit and install its locked dependencies before pushing.`)
            }
        }
        snapshot = fs.mkdtempSync(path.join(temporaryRoot, 'airmius-prepush-'))
        // A separate index includes export-ignored files such as CI workflows
        // without touching the user's staging area or working tree.
        const checkoutEnv = { ...environment, GIT_INDEX_FILE: path.join(snapshot, '.snapshot-index') }
        run('git', ['read-tree', commit], root, checkoutEnv)
        run('git', ['checkout-index', '--all', `--prefix=${snapshot.replaceAll('\\', '/')}/`], root, checkoutEnv)
        // Composer's generated autoloader derives application paths from its
        // own location. Copy vendor so it loads this snapshot's PHP sources.
        console.log('Preparing isolated dependencies and test database…')
        fs.cpSync(path.join(root, 'vendor'), path.join(snapshot, 'vendor'), { recursive: true })
        // Vite can share the installed packages without changing them.
        fs.symlinkSync(path.join(root, 'node_modules'), path.join(snapshot, 'node_modules'), process.platform === 'win32' ? 'junction' : 'dir')
        fs.copyFileSync(path.join(snapshot, '.env.example'), path.join(snapshot, '.env'))
        fs.mkdirSync(path.join(snapshot, 'database'), { recursive: true })
        const database = path.join(snapshot, 'database', 'database.sqlite')
        fs.writeFileSync(database, '')
        const env = { ...environment, DB_DATABASE: database }
        run(php, ['artisan', 'key:generate', '--force'], snapshot, env)
        console.log('\nChecking translations, API paths and accessibility…')
        run(php, ['artisan', 'test', '--filter=LocalizationIntegrityTest|MobileApiPathSourceTest|Wcag22AccessibilityContractTest'], snapshot, env)
        console.log('\nBuilding web assets and checking the manifest…')
        run(process.execPath, [path.join(root, 'node_modules', 'vite', 'bin', 'vite.js'), 'build'], snapshot, env)
        run(php, ['scripts/deploy/verify-build.php', 'public/build/manifest.json'], snapshot, env)
        const nodeEnv = { ...env, PATH: `${path.dirname(php)}${path.delimiter}${env.PATH || env.Path || ''}` }
        run(process.execPath, ['--test', 'tests/Deployment/buildManifest.test.mjs'], snapshot, nodeEnv)
        console.log('\nRunning the complete backend test suite…')
        run(php, ['artisan', 'test'], snapshot, env)
        removeSnapshot()
    }
    console.log('\nLocal checks passed. Push may continue.')
} catch (error) {
    console.error(`\nPUSH STOPPED: ${error.message}`)
    console.error('Fix the reported problem, commit the correction and push again.')
    process.exitCode = 1
} finally {
    if (snapshot) removeSnapshot()
}
