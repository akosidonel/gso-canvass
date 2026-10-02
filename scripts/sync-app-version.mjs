import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const releasePattern = /^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;

export function syncAppVersion(root, releaseTag = '') {
    const path = resolve(root, 'VERSION');
    const fallback = existsSync(path) ? readFileSync(path, 'utf8').trim() : '1.0.0';
    let version = fallback;
    const git = args => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8' }).trim();
    if (releaseTag) {
        if (!releasePattern.test(releaseTag)) throw new Error('Release tags must use vMAJOR.MINOR.PATCH, for example v1.1.0.');
        if (git(['rev-parse', `${releaseTag}^{commit}`]) !== git(['rev-parse', 'HEAD'])) {
            throw new Error('The release tag must point to the checked-out commit.');
        }
        version = releaseTag.slice(1);
    } else if (existsSync(resolve(root, '.git'))) {
        const tags = git(['tag', '--merged', 'HEAD']).split('\n').filter(tag => releasePattern.test(tag));
        tags.sort((a, b) => {
            const left = a.slice(1).split('.').map(BigInt);
            const right = b.slice(1).split('.').map(BigInt);
            for (let i = 0; i < 3; i++) {
                if (left[i] !== right[i]) return left[i] < right[i] ? -1 : 1;
            }
            return 0;
        });
        if (tags.length) version = tags.at(-1).slice(1);
    }
    if (!releasePattern.test(`v${version}`)) throw new Error('VERSION must contain a valid MAJOR.MINOR.PATCH release number.');
    writeFileSync(path, `${version}\n`);
    return version;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    try {
        const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
        const tag = process.env.GITHUB_REF_TYPE === 'tag' ? process.env.GITHUB_REF_NAME : '';
        process.stdout.write(`Application version: v${syncAppVersion(root, tag)}\n`);
    } catch (error) {
        process.stderr.write(`${error.message}\n`);
        process.exitCode = 1;
    }
}
