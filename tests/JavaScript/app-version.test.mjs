import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { syncAppVersion } from '../../scripts/sync-app-version.mjs';

function withRepo(run) {
    const root = mkdtempSync(join(tmpdir(), 'gso-version-'));
    const git = (...args) => execFileSync('git', ['-C', root, ...args], {
        encoding: 'utf8',
        env: { ...process.env, GIT_AUTHOR_NAME: 'Version test', GIT_AUTHOR_EMAIL: 'test@example.invalid', GIT_COMMITTER_NAME: 'Version test', GIT_COMMITTER_EMAIL: 'test@example.invalid' },
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
    try {
        git('init', '-q');
        writeFileSync(join(root, 'VERSION'), '1.0.0\n');
        git('add', 'VERSION');
        git('-c', 'commit.gpgsign=false', 'commit', '-qm', 'Initial');
        run(root, git);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
}

test('untagged commits preserve the release number and tagged builds choose numeric versions', () => withRepo((root, git) => {
    assert.equal(syncAppVersion(root), '1.0.0');
    git('tag', 'v1.9.0');
    git('tag', 'v1.10.0');
    git('tag', 'v01.99.0');
    git('tag', 'template-v99.0.0');
    assert.equal(syncAppVersion(root), '1.10.0');
    writeFileSync(join(root, 'new.txt'), 'Fix');
    git('add', 'new.txt');
    git('-c', 'commit.gpgsign=false', 'commit', '-qm', 'Ordinary fix');
    assert.equal(syncAppVersion(root), '1.10.0');
    assert.equal(readFileSync(join(root, 'VERSION'), 'utf8'), '1.10.0\n');
}));

test('checking out an older release excludes tags from later commits', () => withRepo((root, git) => {
    git('tag', 'v1.1.0');
    const old = git('rev-parse', 'HEAD');
    writeFileSync(join(root, 'new.txt'), 'Feature');
    git('add', 'new.txt');
    git('-c', 'commit.gpgsign=false', 'commit', '-qm', 'New release');
    git('tag', 'v2.0.0');
    assert.equal(syncAppVersion(root, 'v2.0.0'), '2.0.0');
    assert.throws(() => syncAppVersion(root, 'v1.1.0'), /checked-out commit/);
    git('checkout', '--detach', old);
    assert.equal(syncAppVersion(root), '1.1.0');
}));

test('release tags must be stable version tags and exact release builds use the supplied tag', () => withRepo((root, git) => {
    git('tag', 'v1.0.1');
    git('tag', 'v9.0.0');
    assert.equal(syncAppVersion(root, 'v1.0.1'), '1.0.1');
    for (const tag of ['v1.2', 'v01.0.0', 'v1.0.0-beta', 'release-1.0.0', 'v1.0.0;echo wrong']) {
        assert.throws(() => syncAppVersion(root, tag), /vMAJOR.MINOR.PATCH/);
    }
}));

test('packaged deployments preserve their version without Git metadata', () => {
    const root = mkdtempSync(join(tmpdir(), 'gso-packaged-version-'));
    try {
        writeFileSync(join(root, 'VERSION'), '2.3.4\n');
        assert.equal(syncAppVersion(root), '2.3.4');
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
