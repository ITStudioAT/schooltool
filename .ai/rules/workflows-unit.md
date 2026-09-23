---
paths:
  - '{.github/workflows/ci.yml,scripts/git*.ps1,scripts/ci-*.php,scripts/release-policy.php,scripts/pdeploy_cloudways.sh,tests/Unit/*Release*Test.php}'
  - '{.github/workflows/ci.yml,scripts/git*.ps1,scripts/ci-*.php,scripts/release-policy.php,tests/Unit/*Release*Test.php}'
---

# Workflows Unit

## Gate main deployments on exact policy v2 GitHub evidence
Main gitsave builds and saves a source-bound release, then GitHub runs required checks; -Full adds optional local checks. Preview and feature release keep mandatory local full checks. A documentation lane requires a FULL-proven exact ancestor under identical policy blobs, conservative immutable Git-object classification and equal canonical frontend payloads except source markers. Code, unknown paths, tests, locks or workflow changes require full CI. Never reuse loose development logs or legacy READY results. gitdeploy verifies the latest exact push/main commit, trusted repository/workflow, attempt and every required successful job before SSH and after LIVE; ignore Git replacement objects. Bare pdeploy fails before side effects without pinned identities and CI handoff IDs; IDs are metadata, not server-side attestations. Keep PHP batches sequential with at most ten files, merge all coverage, exercise Linux and native Windows cases, real snapshot MySQL opt-in and narrow SQLite-only cases. Native Windows DB provisioner integration remains separately verified.

## Policy v3 reuses unchanged backend evidence for frontend releases
Supersedes the policy-v2 all-code-full rule: main frontend-only changes may reuse a FULL-proven exact ancestor under identical policy blobs. Frontend tests and a fresh source-bound build remain required; backend, Windows, dependencies, tests, tooling, unknown paths and policy changes stay on the full lane. The first policy-v3 release needs a full baseline. Deployment still verifies exact trusted latest push/main evidence and pinned release identity.
