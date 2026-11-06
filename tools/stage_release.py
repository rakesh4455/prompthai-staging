#!/usr/bin/env python3
"""Stage validated batch assets using an already authenticated GitHub CLI."""
import argparse
import hashlib
import json
import re
import shutil
import subprocess
from pathlib import Path

REPO = 'rakesh4455/prompthai-staging'


def command(folder):
    folder = Path(folder)
    manifests = list(folder.glob('manifest-*.json'))
    if len(manifests) != 1:
        raise ValueError('Expected one batch manifest')
    m = json.loads(manifests[0].read_text())
    if not isinstance(m, dict) or set(m) != {'schema', 'site', 'kind', 'batch_id', 'items'} or m['schema'] != 1 or m['site'] != 'https://sienna-ostrich-209432.hostingersite.com' or m['kind'] not in ('media', 'content', 'theme') or not isinstance(m['items'], list):
        raise ValueError('Invalid batch manifest')
    tag = m['batch_id']
    if not re.fullmatch(r'[a-z0-9][a-z0-9._-]{0,79}', tag):
        raise ValueError('Invalid batch ID')
    files = []
    for line in (folder / 'SHA256SUMS').read_text().splitlines():
        sha, name = line.split('  ', 1)
        if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]{0,119}', name):
            raise ValueError('Unsafe filename')
        p = folder / name
        if p.is_symlink() or hashlib.sha256(p.read_bytes()).hexdigest() != sha:
            raise ValueError(f'Checksum mismatch: {name}')
        files.append(str(p.resolve()))
    expected = {manifests[0].name}
    prefix = f'https://github.com/{REPO}/releases/download/{tag}/'
    for item in m['items']:
        if 'url' in item:
            if not item['url'].startswith(prefix):
                raise ValueError('Asset URL outside this release')
            name = item['url'][len(prefix):]
            expected.add(name)
            if hashlib.sha256((folder / name).read_bytes()).hexdigest() != item['sha256']:
                raise ValueError('Manifest asset hash mismatch')
    if expected != {Path(p).name for p in files}:
        raise ValueError('Checksum list differs from manifest assets')
    files.append(str((folder / 'SHA256SUMS').resolve()))
    return ['gh', 'release', 'create', tag, *files, '--repo', REPO, '--title', tag, '--notes', f'PromptHai {m["kind"]} batch. Website publication is not authorized by staging this release.']


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('folder', type=Path)
    p.add_argument('--upload', action='store_true', help='Explicitly stage these public files; never publishes WordPress pages')
    args = p.parse_args()
    cmd = command(args.folder)
    if not args.upload:
        print(json.dumps({'repository': REPO, 'command': cmd, 'uploaded': False}, indent=2))
        return
    if not shutil.which('gh'):
        p.exit(2, 'GitHub CLI is not installed. Use the approved GitHub browser upload; never paste credentials into chat.\n')
    subprocess.run(['gh', 'auth', 'status'], check=True)
    subprocess.run(cmd, check=True)


if __name__ == '__main__':
    main()
