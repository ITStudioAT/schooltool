---
paths:
  - '{app/Http/Controllers/Admin/Teaching/CourseWorkController.php,resources/js/pages/admin/teaching/overview/components/CourseWorks.vue,resources/js/pages/admin/teaching/overview/components/CourseStudent*.vue,resources/js/pages/admin/teaching/overview/components/WorkEvaluationPdf.vue,resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue}'
---

# Homepage Student Overview My Course

## Show evaluation PDFs at their work and personal assessment
WorkEvaluationPdf selects only the active evaluation_import PDF by exact student_id (null means the overall PDF) and opens it via the existing course-authorized endpoint with inline=1. Show the overall PDF in the work list/editor and the personal PDF beside the student's work assessment and performance details. The student view label is exactly Auswertung (PDF); its API exposes only the current student's personal name/hash and rejects overall, foreign or obsolete PDFs. Preserve course/school/active-year/enrollment/work-entry checks and existing imported data.
