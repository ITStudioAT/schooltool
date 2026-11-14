---
paths:
  - '**/*TimetableV3*'
  - vite.config.js
  - README.md
  - '**'
---

# General

## Compact only equivalent adjacent schedule periods
Keep every underlying timetable group key and raw `schedule_labels`. `display_schedule_labels` may merge adjacent periods only when weekday, recurrence, exact appointment dates, block label, and instruction type are identical; gaps or differing series stay on separate lines.

## Emit the PDF module worker with a JavaScript extension
Cloudways serves .mjs assets as application/octet-stream, which Firefox rejects for module workers and dynamic imports. Emit pdf.worker.min.mjs through assetFileNames as a hashed .js asset, preserving its module bytes and Vite URL rewriting. Keep the real Rollup emission regression in FrontendBundleBoundaries.test.ts.

## Use proportional local verification and stop after a passing focused check
For local text/style/alignment changes use one agent, edit directly, and inspect the existing preview when available; Vite/HMR already compiles the component, so no separate test command is required. If no usable preview exists, use focused compilation only when useful. No full suites, coverage, implementation-mirroring tests, documentation lookup, release builds or publication by default. Behavior changes need affected regression tests. Broaden only for a concrete uncovered risk, then stop once verified. Publication uses build/integrity preflight and a bounded isolated smoke before live deployment; full CI is nonblocking. Database safety remains mandatory. AGENTS.md is the concise authoritative workflow.

## Integrate verified worktree changes into the locally used checkout
Before reporting a programming task ready for visual review, safely integrate its verified worktree changes into C:/laravel/schooltool, preserving existing and unrelated changes. Local integration is authorized by default; autonomously resolve safe overlaps and report only genuine unresolved conflicts. Verify the checkout served by the existing preview and Vite/HMR and state any required reload. This does not authorize commits, pushes, publication, deployment or production changes; worktree-only completion is not local availability.
