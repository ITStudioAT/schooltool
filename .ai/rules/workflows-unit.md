---
paths:
  - '{.github/workflows/ci.yml,scripts/git*.ps1,scripts/ci-*.php,scripts/release-policy.php,scripts/pdeploy_cloudways.sh,tests/Unit/*Release*Test.php}'
  - '{.github/workflows/ci.yml,scripts/git*.ps1,scripts/ci-*.php,scripts/release-policy.php,tests/Unit/*Release*Test.php}'
---

# Workflows Unit

## Keep extensive CI as asynchronous evidence
Main and preview artifacts remain source-bound. Background CI preserves full PHP batches, merged coverage, Linux/infrastructure and native Windows checks; failures must retain failing conclusions. Documentation and frontend lanes may reuse a fully proven exact ancestor under identical policy blobs and conservative immutable Git-object classification. These CI rules classify background validation; they do not gate publication. Optional local -Full still uses owned disposable databases and stops on failure.

## Policy v3 reuses unchanged backend evidence for frontend releases
Supersedes the policy-v2 all-code-full rule: main frontend-only changes may reuse a FULL-proven exact ancestor under identical policy blobs. Frontend tests and a fresh source-bound build remain required; backend, Windows, dependencies, tests, tooling, unknown paths and policy changes stay on the full lane. The first policy-v3 release needs a full baseline. Deployment verifies pinned release identity and the bounded isolated runtime smoke; CI completion is informational.

## Publish after fast preflight with nonblocking background CI
Explicit operator policy supersedes old publication gates: saving, preview, feature release and live never wait for full local suites or successful CI. Preserve builds, immutable source/artifact integrity, target/DB isolation, reservation/CAS and explicit PREVIEW/REFRESH/RELEASE/LIVE confirmations. Before LIVE run the bounded exact-release bootstrap+/up smoke with isolated SQLite memory and no external services. V3 preview receipts certify only build/integrity preflight, never full tests; preserve legacy provenance. Pushes to feature/** and preview/** run background CI on their exact commits with real failure conclusions. Optional main -Full remains explicit.
