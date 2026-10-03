---
paths:
  - resources/js/pages/admin/teaching/overview/components/CourseTable.vue
---

# Admin Teaching Overview Components

## Show imported evaluation PDFs in table dialogs
In CourseTable, show the imported overall PDF in the saved work list and beside the import action in the work dialog as Gesamtauswertung (PDF). Read PDF metadata from the saved courseWorks item rather than workDialogForm, whose status is normalized to an array. Show the matching personal Auswertung (PDF) below the work-derived student entry comment even when the entry is collapsed, and in individual/group grading rows. Reuse WorkEvaluationPdf and the protected inline route; select by registered user_id, never enrollment id or a null student fallback.
