import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

function verify(t, manifest, files = ['assets/app.js']) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'airmius-manifest-'));
    t.after(() => fs.rmSync(root, { recursive: true, force: true }));
    for (const file of files) {
        fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
        fs.writeFileSync(path.join(root, file), 'test asset');
    }
    fs.writeFileSync(path.join(root, 'manifest.json'), JSON.stringify(manifest));
    const result = spawnSync(process.env.PHP_BIN || 'php', [
        'scripts/deploy/verify-build.php', path.join(root, 'manifest.json'),
    ], { encoding: 'utf8' });
    if (result.error) throw result.error;
    return result;
}

test('accepts complete manifest with valid entry and imported chunk', t => {
    assert.equal(verify(t, {
        'resources/js/app.js': { file: 'assets/app.js', imports: ['_shared.js'] },
        '_shared.js': { file: 'assets/shared.js' },
    }, ['assets/app.js', 'assets/shared.js']).status, 0);
});

test('rejects missing built asset', t => {
    assert.notEqual(verify(t, {
        'resources/js/app.js': { file: 'assets/missing.js' },
    }).status, 0);
});

test('rejects manifest import that does not exist', t => {
    assert.notEqual(verify(t, {
        'resources/js/app.js': { file: 'assets/app.js', imports: ['_missing.js'] },
    }).status, 0);
});

test('rejects absolute file outside build directory', t => {
    assert.notEqual(verify(t, {
        'resources/js/app.js': { file: path.resolve('composer.json') },
    }).status, 0);
});
