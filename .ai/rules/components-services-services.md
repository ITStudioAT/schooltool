---
paths:
  - '{app/Services/TeachingWorkMarkdownImport.php,app/Http/Controllers/Admin/Teaching/CourseWorkController.php,resources/js/pages/admin/teaching/overview/components/CourseWorks.vue,app/Services/*TeachingBackupService.php,app/Services/TeachingSynchronisation*.php}'
---

# Components Services Services

## Import evaluated work folders with numeric points and private PDF references
Evaluation folders pair Beurteilungen Markdown/PDF by exact basename and verified report identity. Beurteilt/Offen are statuses, not school grades: import totals only into a numeric-points entry type with matching maximum. Match full name plus class uniquely inside authorized active course participants; preview all overwrites and require a fresh server snapshot hash. Open submissions never become zero or erase previous values. Last valid import updates points/comments and the imported PDF slot; preserve unrelated attachments and old private files. Work status.evaluation_pdfs is server-managed and stores file_path/storage_disk/student_id metadata. School/personal backups and synchronisation must capture/remap those nested references, preserving null student_id for the overall PDF.

## Keep imported evaluation comments to the result line
For evaluated submissions import only the original Markdown result line starting with **Gesamt: and including its MC/E-Mail totals as the comment. Exclude frontmatter, headings, maximum-point introductions and rubric details; students read the full personal PDF. Open submissions retain existing comments and values.
