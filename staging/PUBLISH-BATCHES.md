# PromptHai deployment log

## 2026-11-06 — Repository setup

- Public repository `rakesh4455/prompthai-staging` created with owner approval.
- Initial repository commit: 909ac3c2922a82366948aba8a1cc1aafaa761002.
- Setup log commit: 269b1c9cc26da9118afd8e9e827865b25627a4bb.
- Authenticated GitHub read/write access verified.
- No release assets or WordPress content imported during repository setup.

## 2026-11-06 — Importer and local tooling review checkpoint

- Source: src/wp-importer.php, v1.0.0.
- Local PHP 8.3.6 syntax check passed.
- 39 PHP contract checks passed in a small mock WordPress environment.
- 11 Python builder/staging tests passed; Python compilation passed.
- PHP contract tests cover wrong site/admin, URLs, hashes, redirects, paths/symlinks, default draft, replay safety, duplicate destinations, snapshot conflicts, published-content refusal, partial failures, native-writer refusal/success, active-theme refusal, code preservation and media-byte checks.
- These checks are not a real WordPress runtime test, security audit or performance measurement.
- Authenticated site metadata confirmed PromptHai, Twenty Twenty-Five 1.5 active, private draft present, WPVibe 1.21.3 and PHP 8.3.36.
- Installed Look Finder source/registered metadata inspected read-only; native indexing hook retained.
- WPCode snippet #81 saved OFF, PHP / Everywhere / automatic insertion. No code executes until the owner reviews and enables it.
- No page/media import, theme write or activation, indexing change, cache purge, final-domain launch or social posting occurred.
- Pilot prepared: pilot-content-v1, one new private page, no images, no Look Code allocation.
- Native theme batch approval/file-type compatibility remains untested. Any native refusal must stop; no direct filesystem write fallback.
- Published-content updates are intentionally unsupported in v1; only existing drafts can be updated.
- Next gate: owner reviews/enables snippet #81. Then authenticated health, hash rejection, validation, one draft import, exact retry, and actual WordPress draft inspection. Log actual IDs and outcomes before declaring this pipeline working.

## Pilot checksums

9218b61920138775a18a9194953fdcf1476cf1a3febc3660ef727596f6481559  manifest-content.json

## Rules and future batch records

PromptHai only. Never store credentials, cookies, private reference photos or private preview links here. New content stays draft. Theme changes use the native private-draft writer. Never enable snippets automatically, bypass approvals, activate themes, change indexing, delete records or add scheduled workflows.

For each actual import append release tag, approved SHA-256 hashes, target, previous/current snapshots, returned page/media IDs, per-item outcomes, local/site checks and approval/publication state. Reconcile partial failures before preparing a new batch; never blindly replay them.
