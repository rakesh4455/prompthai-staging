# PromptHai batch deployment

Build locally, review the output, stage a GitHub Release, then ask the PromptHai site to fetch and verify the approved files. This repository belongs to PromptHai only.

## Current checkpoint

Importer v1 and the local tools are ready for review. WPCode snippet #81 is saved on PromptHai and is OFF; only the owner may enable it. They have passed local PHP syntax checking, 39 PHP contract checks with a small WordPress substitute, and 11 Python builder checks. These are not a full WordPress integration test or a security certification. A real site pilot remains required after the owner enables the reviewed snippet.

Target site: `https://sienna-ostrich-209432.hostingersite.com`.
Repository: `rakesh4455/prompthai-staging`, public by owner approval.
Theme target: the existing private `prompthai-wpvibe-draft`; the active theme is not changed.

No publication, theme activation, settings/indexing changes, deletions, cache purge, Google/Pinterest setup, or scheduled jobs are provided by the importer.

## Installation and review

1. Review `src/wp-importer.php` and this document.
2. Create a PHP snippet in WPCode, location Everywhere, automatic insertion, **disabled**. When using WPVibe's code_snippet tool, omit the opening `<?php` tag; the source file retains it for local PHP linting.
3. The owner reviews and enables the snippet. The agent must never enable it or edit another enabled snippet to get around this gate.
4. Read authenticated `/prompthai-deploy/v1/health`. Requests require the same authenticated administrator connection as normal WordPress work. A cookie request must carry its normal WordPress REST nonce. No public secret URL or custom password is used.
5. Stage the prepared `pilot-content-v1` release, validate its manifest, apply once to create a private draft, repeat the identical apply to check replay safety, and inspect the actual draft in WordPress. Record its ID and results. Do not publish it.

## Endpoints

| Route under `/prompthai-deploy/v1` | Method | Purpose |
| --- | --- | --- |
| `/health` | GET | Version, target, limits, lock state; administrator only |
| `/snapshot` | POST | Hashes for up to 12 existing pages or theme files; optional recorded batch status |
| `/import-files` | POST | Release manifest for up to 12 WebP/PNG/JPEG images |
| `/import-batch` | POST | Release manifest for up to 12 new or existing **draft** pages/looks |
| `/import-theme` | POST | Release manifest for up to 12 text files; dispatches the native WPVibe draft writer |

Import requests contain `url`, lowercase `sha256`, and `dry_run`. The default is `dry_run: true`. Example request files are generated locally; their URLs become valid only after the Release assets have been staged.

## Content and Look Finder

New content is always a draft. Existing published, private, scheduled or pending pages cannot be updated by this v1 importer. This intentionally leaves a live-content approval/revision adapter for a later review; never silently demote or overwrite a published page to make an import succeed. Existing draft updates require the ID and an `expected_sha256` from a recent snapshot. URLs cannot be renamed through this importer.

Each content item has a durable client key. Reusing a key for a different record is refused. All items are preflighted before page creation. A duplicate slug or target ID within the batch is refused. User prompts and requirements are plain text; page HTML must pass WordPress's standard post-HTML filter unchanged. The shared theme CSS is not copied into pages.

The allowlisted metadata contains `ph_config`, the existing prompt/requirements/customization/search fields, and Rank Math title/description/focus fields. `ph_config` is stored as compact JSON text. Permanent codes, finder switches, arbitrary protected metadata, template settings and indexing settings cannot be supplied. Existing codes and finder switches remain unchanged. New draft looks have Finder disabled and do not allocate a public code; enable and publish them only through a later reviewed content workflow. The installed Look Finder `index()` hook is called after save; its native rules keep drafts out of public results. No replacement search index or direct registry SQL is used.

Existing Media Library image IDs can be referenced with `featured_media`. Do not pair untested prompts with reference photos and call them tested. The deployment tool does not certify originality, rights, image quality, prompt performance, SEO completeness or exact likeness. Those content checks remain part of the approved content batch.

## Media

The media manifest supplies a stable key, exact release URL/hash, descriptive filename, title and alt. Source image bytes and extension must match. Only JPEG, PNG and WebP are allowed; no PHP, HTML, SVG or PDF uploads through this endpoint. Maximum 5 MiB per image, 7,000 pixels per side and 32 million total pixels. WordPress creates its normal derivatives and returns the attachment ID. The original stored image is hash-checked after import.

An existing identical imported image can be reused; title, alt and checksum must still match. Existing files are never replaced. Public release assets and uploaded media URLs can be accessible even while a page remains a draft. Stage only approved public material, not private reference photos.

## Theme updates

Theme assets are a manifest of individually checksummed UTF-8 files. No ZIP extraction is performed on WordPress. Each path needs its previous SHA-256, or explicit `null` for a new file. Paths and symlink components are checked against the fixed inactive draft. Existing bytes are kept in a private, non-autoloaded recovery option before writes.

Every actual write goes through `rest_do_request()` to the existing `/wpvibe/v1/file/write` endpoint. Its permission callback, approval/proof requirements, file-type rules and PHP syntax checks still run. No proof token is invented or forwarded, no plugin callback is invoked directly, and no raw write fallback exists. If WPVibe requires an operation approval or rejects a file, the batch stops. Use the supported native review/write path; do not weaken the importer or plugin to force success. SVG support and native multi-file writes remain pilot-dependent. A successful dry run does **not** establish that native theme writing will be approved.

This means the call-saving target is strongest for media and draft content. Theme batching may still require native per-file approvals/calls. Do not promise all theme uploads will fit one call before the actual pilot proves that path.

## Downloads, integrity and limits

- Only `https://github.com/rakesh4455/prompthai-staging/releases/download/<tag>/<asset>` is accepted as an initial URL. No initial query strings, user information, ports or fragments.
- Bounded redirects to GitHub's `release-assets.githubusercontent.com` or `objects.githubusercontent.com` are supported; WordPress's safe HTTP validation and TLS checks remain enabled. Redirect URLs/tokens are not returned in logs.
- The expected SHA-256 must match downloaded bytes. This verifies equality to reviewed bytes, not authorship or protection from a compromised approved manifest.
- Manifest: 1 MiB. Theme text file: 1 MiB. Combined binary/text asset bytes: 16 MiB. Up to 12 items. Split large batches; host/network/runtime limits can require smaller ones.
- No WordPress credentials belong in this repository or release files. Do not paste tokens/passwords into chat.

## Retry, concurrency and recovery

A global non-autoloaded option lock serializes importer batches. Current post/file hashes are checked again immediately before their writes. This protects against ordinary concurrent changes but is not a database transaction or a complete lock against all third-party tools.

A batch ID is permanently tied to its manifest digest. Repeating a fully completed batch returns the stored receipt without writing again. That receipt is historical: it does not assert that content has not changed since then. Reusing the batch ID with different bytes is refused.

Receipts are saved before and after each item. A failed batch reports completed items and the current item. Automatic replay of partial/running/failed batches is refused because WordPress hooks, image processing or a killed request can have side effects before an item receipt is written. Use `/snapshot` and client keys to inspect actual records, reconcile manually, then prepare a new batch ID for only the remaining intended work. Do not delete anything as a retry shortcut.

Recovery snapshots are stored in the protected `_phri_batch_<hash>_backup` option. Existing content also retains WordPress's normal revisions where supported. These are not a hosting/database backup. After a hard process interruption a lock can remain; the owner must inspect the recorded batch and release only that specific lock through an approved administrative action. The importer has no public unlock or rollback endpoint. Restore reviewed previous content/theme bytes through the same guarded draft workflow; never bypass native checks. Preserve newly created files/records for reconciliation instead of deleting them automatically.

## Local commands

Requires Python 3.10+ and a PHP 8.1+ executable for checks. The CLI upload helper additionally needs GitHub CLI already authenticated by an approved login. A connected ChatGPT GitHub app does not automatically authenticate a local `gh` executable.

```sh
php -l src/wp-importer.php
php tests/test_importer.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 tools/build_batch.py examples/pilot-content.spec.json --out staging/pilot-content-v1
python3 tools/stage_release.py staging/pilot-content-v1
```

The stage helper prints the checked upload command by default. `--upload` explicitly stages those public assets to GitHub; it never calls WordPress. With no authenticated CLI, the owner-approved GitHub browser Release upload is the fallback. No Action workflow, recurring automation or credential setup is added.

For subsequent batches write a local specification matching the pilot's shape. Media/theme specs use a `file` path relative to the spec folder. Content can use `html_file` in place of `html`. Theme items require `path` and `expected_sha256`; media items require `key`, `filename`, `title` and `alt`. The builder rejects paths outside the batch folder and symlinked sources, then computes all hashes itself.

## Acceptance before normal use

After the owner enables the snippet: authenticated health; wrong hash fails with no draft; validate; apply; exact retry returns one ID; real draft preview; inspect title, content, SEO fields and status; confirm unchanged active theme/noindex settings and existing look results. Later run a small approved media pilot, then a separate theme pilot through native guards. New image/character content and design acceptance stay at their own batch checkpoints.

Append actual release tags, checksums, record IDs and test observations to `staging/PUBLISH-BATCHES.md`. Browser checks and call count estimates are not substitutes for these results. Normal modest media/content batches target roughly 2–3 WPVibe calls after setup; initial validation, snapshots, replay tests and failures cost extra.

## Primary implementation references

- https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- https://developer.wordpress.org/reference/functions/rest_do_request/
- https://developer.wordpress.org/reference/functions/wp_safe_remote_get/
- https://docs.github.com/en/rest/releases/assets
