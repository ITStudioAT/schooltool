---
paths:
  - '{app/Services/TeachingWorkMarkdownImport.php,app/Http/Controllers/Admin/Teaching/CourseWorkController.php,resources/js/pages/admin/teaching/overview/components/CourseWorks.vue,app/Services/*TeachingBackupService.php,app/Services/TeachingSynchronisation*.php}'
  - app/Services/TeachingWorkFolderImport.php
  - app/Services/TeachingWorkDispatchImport.php
  - resources/js/pages/admin/teaching/overview/components/WorkEvaluationImport.vue
---

# Components Services Services

## Import evaluated work folders with numeric points and private PDF references
Evaluation folders pair Beurteilungen Markdown/PDF by exact basename and verified report identity. Beurteilt/Offen are statuses, not school grades: import totals only into a numeric-points entry type with matching maximum. Match full name plus class uniquely inside authorized active course participants. The legacy evaluation API previews overwrites and requires a fresh server snapshot hash; the normal complete-folder import validates and applies under one lock and transaction. Open submissions never become zero or erase previous values. Changed sources update points/comments and the imported PDF slot; unchanged source hashes preserve manual edits. Preserve unrelated attachments and old private files. Work status.evaluation_pdfs is server-managed and stores file_path/storage_disk/student_id metadata. School/personal backups and synchronisation must capture/remap those nested references, preserving null student_id for the overall PDF.

## Keep imported evaluation comments to the result line
For evaluated submissions import only the original Markdown result line starting with **Gesamt: and including its MC/E-Mail totals as the comment. Exclude frontmatter, headings, maximum-point introductions and rubric details; students read the full personal PDF. Open submissions retain existing comments and values.

## Import original dispatch logs without sending messages or changing grades
TeachingWorkDispatchImport validates separate tasks/results purposes. Infer purpose from consistent subjects and standard folder paths; when present, Versandzweck must exactly agree (Aufgabenversand or Ergebnisbenachrichtigung). The legacy dispatch API also checks its selected purpose, unique saved title and snapshot hash; complete-folder imports use the explicitly selected work ID without title matching. Support original numbered EMPFÄNGER blocks as historical Mailpit task tests; archive teacher-only result logs without student flags. Bind authorized current participant full name/class/email plus work membership, and recheck under lock. Preserve original bytes in private server-managed dispatch_logs; live dispatch_notifications require all existing Postmark evidence, and non-live dispatch_attempts stay separate. Idempotent history cannot be forged/erased by work updates; backups/sync preserve private files, purpose and remap student IDs for both record collections. No actual send or migration.

## Import complete work folders atomically without routine confirmation
The normal UI uses one Importieren action and a complete standard work folder. TeachingWorkFolderImport detects Beurteilungen MD/PDF and Versand/Aufgaben or Versand/Ergebnisse logs, validates every recognized source under the authorized work lock, and applies the bundle atomically; any mismatch rolls back records and newly created private files. Unchanged source hashes preserve manual grade edits; changed/new sources are additive and retain history. Teacher-only format tests require exact metadata and subjects, are archived without student live flags, and errors identify the relative source path. Legacy preview/hash endpoints remain separate compatibility APIs, not the normal folder flow.

## Use the explicitly selected work for complete folder imports
Importieren in a saved work's detail targets that authorized work ID. Differences between its title and source titles, or another work with the same title/date, must not block or redirect the complete-folder import. Keep titles consistent within each source report and preserve course/person/class/email/work membership, points scale, source validity and provider evidence checks. Legacy preview/hash dispatch APIs retain their title matching.

## Shared school-skill dispatch contract
New school-skill journals follow C:/Dropbox/AI/.agents/skills/schule/send-exercise-via-email/references/schooltool-dispatch-v1.md and its read-only canonical Office checker. Preserve historical source bytes and accept only documented aliases with matching actual Office evidence; planned attachments and Mailpit never prove live sends. Keep tasks/results purposes, separate teacher rows, and preview/apply distinct; explicit verified empty attachment lists are valid.
