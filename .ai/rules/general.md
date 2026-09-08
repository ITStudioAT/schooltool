---
paths:
  - '**/*TimetableV3*'
  - vite.config.js
---

# General

## Compact only equivalent adjacent schedule periods
Keep every underlying timetable group key and raw `schedule_labels`. `display_schedule_labels` may merge adjacent periods only when weekday, recurrence, exact appointment dates, block label, and instruction type are identical; gaps or differing series stay on separate lines.

## Emit the PDF module worker with a JavaScript extension
Cloudways serves .mjs assets as application/octet-stream, which Firefox rejects for module workers and dynamic imports. Emit pdf.worker.min.mjs through assetFileNames as a hashed .js asset, preserving its module bytes and Vite URL rewriting. Keep the real Rollup emission regression in FrontendBundleBoundaries.test.ts.
