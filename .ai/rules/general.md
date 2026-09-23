---
paths:
  - '**/*TimetableV3*'
  - vite.config.js
  - '{README.md,scripts/deploy_preview_cloudways.sh}'
  - '**'
---

# General

## Compact only equivalent adjacent schedule periods
Keep every underlying timetable group key and raw `schedule_labels`. `display_schedule_labels` may merge adjacent periods only when weekday, recurrence, exact appointment dates, block label, and instruction type are identical; gaps or differing series stay on separate lines.

## Emit the PDF module worker with a JavaScript extension
Cloudways serves .mjs assets as application/octet-stream, which Firefox rejects for module workers and dynamic imports. Emit pdf.worker.min.mjs through assetFileNames as a hashed .js asset, preserving its module bytes and Vite URL rewriting. Keep the real Rollup emission regression in FrontendBundleBoundaries.test.ts.

## Exclude stale live configuration before initial preview bootstrap
When separating a preview that previously used live credentials, keep preview closed and finish its private isolated configuration before booting new Artisan code. Cached configuration overrides .env even for config:clear and package:discover. Verify the effective cache path and ownership (including APP_CONFIG_CACHE), privately retain and remove the old cache from its active path, and check external server variables before the first new Artisan invocation. Do not retain secret-bearing cache backups under the web root.

## Remove live secrets from preview backup copies during isolation
When a preview formerly shared production credentials, old .env/config-cache backup copies also contain live database passwords and APP_KEY. Do not retain them anywhere readable by the preview Unix account, including private_html. If recovery retention is needed, encrypt and verify them on the administrator PC outside the repository before replacing/removing the exact reviewed old files. Private preview snapshot backups after isolation are different: they contain only preview-owned credentials/keys.

## Use proportional local verification and stop after a passing focused check
For local text/style/alignment changes use one agent, edit directly, and inspect the existing preview when available; Vite/HMR already compiles the component, so no separate test command is required. If no usable preview exists, use focused compilation only when useful. No full suites, coverage, implementation-mirroring tests, documentation lookup, release builds or publication by default. Behavior changes need affected regression tests. Broaden only for a concrete uncovered risk, then stop once verified. Existing explicit preview/release gates and database safety remain mandatory. AGENTS.md is the concise authoritative workflow.
