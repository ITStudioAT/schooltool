---
paths:
  - '{app/Services/TeachingCourseDateService.php,app/Services/TeachingBackupService.php,app/Services/PersonalTeachingBackupService.php,resources/js/pages/admin/teaching/overview/components/CourseTable.vue}'
---

# Overview Components

## Share curriculum files through per-date private copies
Curriculum unit-file visibility is specific to a linked course date. Reuse adopted material attachments with a private local copy and source_teaching_curriculum_document_id; never make the curriculum source globally student-visible. Persist explicit student_visible booleans and retain student endpoint authorization. Backup restores must remap the source document ID or clear unverifiable references while preserving the copied file and visibility.
