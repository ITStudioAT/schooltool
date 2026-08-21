---
paths:
  - '{resources/js/pages/admin/teaching/admin/import116/Import116.vue,tests/ui/unit/components/admin/teaching/Import116Page.test.ts}'
---

# Teaching

## Start Import 116 fallback polling at upload start
Start the Import 116 run-status polling when FilePond emits uploadStart. Do not defer the fallback until fileUploadFinished: a missing final FilePond event must not leave the UI showing an endless processing state after the backend run completed. Stop polling on upload error, reset, and unmount.
