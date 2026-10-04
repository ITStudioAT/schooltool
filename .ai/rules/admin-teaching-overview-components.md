---
paths:
  - resources/js/pages/admin/teaching/overview/components/CourseTable.vue
  - resources/js/pages/admin/teaching/overview/components/CourseWorks.vue
  - resources/js/pages/admin/teaching/overview/components/WorkDispatchStatus.vue
  - resources/js/pages/admin/teaching/overview/components/WorkDispatchLog.vue
  - resources/js/helpers/workDispatch.js
---

# Admin Teaching Overview Components

## Show imported evaluation PDFs in table dialogs
In CourseTable, show the imported overall PDF in the saved work list and beside the import action in the work dialog as Gesamtauswertung (PDF). Read PDF metadata from the saved courseWorks item rather than workDialogForm, whose status is normalized to an array. Show the matching personal Auswertung (PDF) below the work-derived student entry comment even when the entry is collapsed, and in individual/group grading rows. Reuse WorkEvaluationPdf and the protected inline route; select by registered user_id, never enrollment id or a null student fallback.

## Keep overview actions compact and dispatch status scoped to each work
Work overview cards expose only Importieren and Gesamtauswertung (PDF) as import/download actions. Details add one Download Aufgabenversand and one Download Ergebnisbenachrichtigung action; multiple saved logs appear in purpose-specific menus with time, test/live and teacher/student scope. Stop card action propagation and keep cancel/save in a separate footer. Show task and result icons side by side on each dated work chip and personal table entry, including grey task tests; green requires successful live evidence linked to a saved original. Teacher-only logs never set student status. Cards/details and personal entry cards show two labelled status rows, with personal result times belonging to that student and aggregate times using the latest confirmed result. Retain Europe/Vienna times, separate same-type work chips, prominent personal entry grades and the correction tooltip without parenthetical disclaimers.
