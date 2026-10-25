---
paths:
  - resources/js/pages/admin/teaching/overview/components/CourseTable.vue
  - resources/js/pages/admin/teaching/overview/components/CourseWorks.vue
  - resources/js/pages/admin/teaching/overview/components/WorkDispatchStatus.vue
  - resources/js/pages/admin/teaching/overview/components/WorkDispatchLog.vue
  - resources/js/helpers/workDispatch.js
---

# Admin Teaching Overview Components

## Distinguish module and exercise folders for current and historical work
The link icon opens the learning module; the Leistungsfeststellung icon opens the concrete exercise/group folder. Historical associations must retain these two meanings without importing, recomputing or replacing grades. A known imported assessment package can establish exercise identity even when individual source files are missing or changed: list those differences as warnings and allow explicit folder confirmation. Do not treat changed source files as new grades or new submission versions. Unknown or ambiguous assessment identity still blocks confirmation. Exclude temporary tmp/temp/inventory trees from folder suggestions and never bind their working copies as original exercise folders.

Historical folder proposals show their full path without opening a subfolder listing. Read original evidence with bounded concurrency; after a successful byte check, confirmation refreshes the online inventory and compares the proof-bound file identities, revisions, sizes and content hashes instead of downloading unchanged revisions again. Any change requires a fresh preview, and saving the association preserves every work field and prior workflow receipt.

## Preserve prior results and advance only changed personal versions
After collecting submissions again, byte-identical files leave downstream steps valid. New or changed submission bytes reopen evaluation only for the affected person. Preserve the last confirmed grade and reports as the prior version until an explicit new evaluation is accepted. Publication and result notification refer to that person's accepted result version; notify only people with a new published result, excluding already confirmed notifications for that version. A rescan timestamp alone never invalidates grades or justifies resending. Show personal pending counts and distinguish current, pending and prior-version evidence. Folder relinking only changes the verified folder association and never recomputes or imports grades.

## Keep repeat actions in the status dialog footer
Status dialogs place actions such as Erneut einsammeln, Erneut prüfen or Erneut bewerten at the bottom left, inside the action footer. Schließen stays at the bottom right, separated by a spacer. Do not place repeat actions among the information paragraphs. Apply this arrangement to new status dialogs as well.

## Show imported evaluation PDFs in table dialogs
In CourseTable, show the imported overall PDF in the saved work list and beside the import action in the work dialog as Gesamtauswertung (PDF). Read PDF metadata from the saved courseWorks item rather than workDialogForm, whose status is normalized to an array. Show the matching personal Auswertung (PDF) below the work-derived student entry comment even when the entry is collapsed, and in individual/group grading rows. Reuse WorkEvaluationPdf and the protected inline route; select by registered user_id, never enrollment id or a null student fallback.

## Keep overview actions compact and dispatch status scoped to each work
Work overview cards expose only Importieren and Gesamtauswertung (PDF) as import/download actions. Details add one Download Aufgabenversand and one Download Ergebnisbenachrichtigung action; multiple saved logs appear in purpose-specific menus with time, test/live and teacher/student scope. Stop card action propagation and keep cancel/save in a separate footer. Show task and result icons side by side on each dated work chip and personal table entry, including grey task tests; green requires successful live evidence linked to a saved original. Teacher-only logs never set student status. Cards/details and personal entry cards show two labelled status rows, with personal result times belonging to that student and aggregate times using the latest confirmed result. Retain Europe/Vienna times, separate same-type work chips, prominent personal entry grades and the correction tooltip without parenthetical disclaimers.
