#!/usr/bin/env python3
"""Build a reviewable PromptHai release batch. Never contacts WordPress."""
import argparse
import hashlib
import json
import re
import shutil
from pathlib import Path

SITE = 'https://sienna-ostrich-209432.hostingersite.com'
REPO = 'rakesh4455/prompthai-staging'
KINDS = {'content': 'import-batch', 'media': 'import-files', 'theme': 'import-theme'}
KEY = re.compile(r'[a-z0-9][a-z0-9._-]{0,79}\Z')
HASH = re.compile(r'[a-f0-9]{64}\Z')


def digest(data):
    return hashlib.sha256(data).hexdigest()


def json_bytes(data):
    return (json.dumps(data, ensure_ascii=False, separators=(',', ':')) + '\n').encode()


def source_file(base, value):
    p = Path(value)
    if p.is_absolute() or '..' in p.parts:
        raise ValueError('Source paths must stay within the batch specification folder')
    full = base / p
    if any(part.is_symlink() for part in [full, *full.parents] if part != base.parent):
        raise ValueError('Symbolic links are not permitted')
    if not full.resolve().is_relative_to(base.resolve()) or not full.is_file():
        raise ValueError('Missing or out-of-folder source file')
    return full


def build(spec_path, destination):
    spec_path = Path(spec_path).resolve()
    spec = json.loads(spec_path.read_text())
    if set(spec) != {'kind', 'batch_id', 'items'}:
        raise ValueError('Expected only kind, batch_id and items')
    kind, batch_id = spec['kind'], spec['batch_id']
    if kind not in KINDS or not isinstance(batch_id, str) or not KEY.fullmatch(batch_id):
        raise ValueError('Invalid kind or batch ID')
    if not isinstance(spec['items'], list) or not 1 <= len(spec['items']) <= 12:
        raise ValueError('Use 1–12 items per batch')
    out = Path(destination)
    if out.exists() and any(out.iterdir()):
        raise ValueError('Output directory must be empty; previous batches are preserved')
    out.mkdir(parents=True, exist_ok=True)
    base_url = f'https://github.com/{REPO}/releases/download/{batch_id}/'
    items, assets, seen, targets = [], {}, set(), set()
    total_bytes = 0
    for number, raw in enumerate(spec['items'], 1):
        x = dict(raw)
        identity = x.get('path') if kind == 'theme' else x.get('key')
        if not isinstance(identity, str) or identity in seen:
            raise ValueError('Missing or duplicated item identity')
        seen.add(identity)
        if kind == 'content':
            allowed = {'key', 'id', 'expected_sha256', 'type', 'slug', 'title', 'html', 'html_file', 'excerpt', 'meta', 'featured_media'}
            if set(x) - allowed or not KEY.fullmatch(x['key']):
                raise ValueError('Unsupported content field or item key; publishing is not supported')
            if x.get('type', 'page') not in ('page', 'ps_look'):
                raise ValueError('Unsupported content type')
            if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', x.get('slug', '')):
                raise ValueError('Invalid content slug')
            target = (x.get('type', 'page'), x['slug'])
            if target in targets or ('id' in x and ('id', x['id']) in targets):
                raise ValueError('Duplicate content destination')
            targets.add(target)
            if 'id' in x:
                targets.add(('id', x['id']))
                if not HASH.fullmatch(x.get('expected_sha256', '')):
                    raise ValueError('Draft updates require an approved snapshot hash')
            elif 'expected_sha256' in x:
                raise ValueError('New records must omit expected_sha256')
            if 'html_file' in x:
                if 'html' in x:
                    raise ValueError('Supply html or html_file, not both')
                x['html'] = source_file(spec_path.parent, x.pop('html_file')).read_text()
            if not x.get('title') or not isinstance(x.get('html', ''), str):
                raise ValueError('Content title and HTML required')
            if re.search(r'<\s*(?:script|iframe|style)\b|\bon\w+\s*=', x.get('html', ''), re.I):
                raise ValueError('Page scripts, inline styles and event handlers are not supported')
            if isinstance(x.get('meta', {}).get('ph_config'), dict):
                x['meta']['ph_config'] = json.dumps(x['meta']['ph_config'], ensure_ascii=False, separators=(',', ':'))
        else:
            allowed = {'path', 'file', 'expected_sha256'} if kind == 'theme' else {'key', 'file', 'filename', 'title', 'alt'}
            if set(x) - allowed or 'file' not in x:
                raise ValueError('Use local files and supported fields only')
            path = source_file(spec_path.parent, x.pop('file'))
            data = path.read_bytes()
            total_bytes += len(data)
            if not data or len(data) > (1048576 if kind == 'theme' else 5242880) or total_bytes > 16777216:
                raise ValueError('Asset size exceeds the server batch limit')
            if kind == 'theme':
                if not re.fullmatch(r'[A-Za-z0-9_-]+(?:[/.][A-Za-z0-9_-]+)*\.(?:php|css|js|json|html|txt|svg)', x['path']) or '..' in x['path']:
                    raise ValueError('Invalid theme path')
                if 'expected_sha256' not in x or (x['expected_sha256'] is not None and not HASH.fullmatch(x['expected_sha256'])):
                    raise ValueError('Theme files require previous SHA-256 or explicit null for a new path')
                data.decode('utf-8')
                if b'\0' in data:
                    raise ValueError('Theme files must be text')
                name = f'{number:02d}-{path.name}'
            else:
                if not KEY.fullmatch(x['key']) or not re.fullmatch(r'[a-z0-9][a-z0-9-]{0,100}\.(?:webp|png|jpg|jpeg)', x.get('filename', '')):
                    raise ValueError('Invalid media key or filename')
                if not x.get('title') or not x.get('alt'):
                    raise ValueError('Media requires title and alt text')
                name = f'{number:02d}-{x["filename"]}'
            if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]{0,119}', name):
                raise ValueError('Invalid release asset name')
            assets[name] = data
            x.update(url=base_url + name, sha256=digest(data))
        items.append(x)
    manifest = {'schema': 1, 'site': SITE, 'kind': kind, 'batch_id': batch_id, 'items': items}
    name = f'manifest-{kind}.json'
    assets[name] = json_bytes(manifest)
    if len(assets[name]) > 1048576:
        raise ValueError('Manifest exceeds server limit')
    for filename, data in assets.items():
        (out / filename).write_bytes(data)
    sums = ''.join(f'{digest(data)}  {filename}\n' for filename, data in sorted(assets.items()))
    (out / 'SHA256SUMS').write_text(sums)
    request = {'url': base_url + name, 'sha256': digest(assets[name]), 'dry_run': True}
    (out / 'request-validate.json').write_bytes(json_bytes(request))
    request['dry_run'] = False
    (out / 'request-apply.json').write_bytes(json_bytes(request))
    (out / 'BATCH-REVIEW.md').write_text(f'# {batch_id}\n\nKind: {kind}. Items: {len(items)}. Asset bytes: {total_bytes}.\n\nEndpoint: /prompthai-deploy/v1/{KINDS[kind]}\n\nReview the full manifest, then stage release files. Validation makes no content/theme/media changes. Apply requires an enabled reviewed importer. New content stays draft. Native theme checks can reject a package; do not bypass them.\n\nNo files have been uploaded and no WordPress request has been sent by this builder.\n')
    return manifest


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('spec', type=Path)
    parser.add_argument('--out', required=True, type=Path)
    args = parser.parse_args()
    try:
        m = build(args.spec, args.out)
    except (ValueError, OSError, KeyError, TypeError) as e:
        parser.exit(2, f'Batch rejected: {e}\n')
    print(f'Built {m["batch_id"]}: {m["kind"]}, {len(m["items"])} items. Local only.')


if __name__ == '__main__':
    main()
