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

## 2026-11-06 — Pilot release staged; owner enable gate pending

- Release: https://github.com/rakesh4455/prompthai-staging/releases/tag/pilot-content-v1 (pre-release).
- Source commit: f264140a6e30694c6e6105382d5558d6fda3f118.
- Uploaded manifest-content.json (567 bytes): SHA-256 9218b61920138775a18a9194953fdcf1476cf1a3febc3660ef727596f6481559.
- Uploaded SHA256SUMS (88 bytes): SHA-256 c49462435962fe450e4c764bb0982b07d8298138f9a0e162cc49a7b25875af80.
- GitHub's rendered asset digests matched the locally computed values for both files.
- Importer source SHA-256: 4a74545997cb96ab74e49b911312b13b281d54d8398cbb79cae24129d67fb90f.
- Snippet #81 remains OFF. No health/validate/apply/replay call has been made to its new routes. No pilot page ID exists yet.
- No media imported, private theme written/activated, or content published by this deployment setup.
- Next: owner reviews/enables #81; then test the actual WordPress pilot and record results. Do not treat local mock tests as proof of end-to-end deployment.

## 2026-11-06 — Enabled v1.0.0 health check blocked; v1.0.1 startup correction prepared

- Owner reported snippet enabled. Authenticated post read confirmed snippet 81 publish/active status and the saved PHP source; no activation was performed by the agent.
- GET /prompthai-deploy/v1/health returned rest_no_route (404); REST namespace listing confirmed the importer namespace absent. No import request was attempted and no pilot page/media was created.
- Root cause reproduced locally: an unconditional PHP class declaration is known before an early class_exists check, so v1.0.0 returned before boot() registered its REST initialization hook. Earlier function-level tests missed startup.
- v1.0.1 places the class declaration inside a conditional declaration and makes boot registration idempotent. Eight startup checks were added: hook registration, all five routes, five permission callbacks and safe repeat include. New regression check was confirmed to fail with the old guard.
- PHP syntax check passed; 47 mock WordPress PHP checks and 11 Python tests passed. This remains local evidence, not a successful real WordPress pilot.
- Corrected source SHA-256: 23cef0f5c57d4eef43e1ce733c3dc468df1b2bb70a6e44b37f119ac19c6871d6.
- Next action: request the supported WPVibe update for existing snippet 81. An enabled-snippet edit may require owner approval; PHP saves land OFF and need owner re-enablement. Do not bypass either gate. Source preparation alone does not mean the corrected code has been saved or enabled on WordPress.
- Pilot release manifest and its hash are unchanged. Existing active theme, private theme files, content, media and indexing settings were not changed.
