---
paths:
  - '{app/Http/Controllers/Student/CourseStudentEntryController.php,resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue,app/Services/TeachingWorkMarkdownImport.php}'
---

# My Course Services

## Expose only the current student's personal evaluation PDF
Student Leistungen receives only the current student's active personal evaluation-PDF metadata, never the work's full status/PDF list or storage paths. Download uses ParentStudentAccessService::currentStudent (preserving existing parent/representation rules), active schoolyear, same-school owning course, active enrollment and the student's work entry; exclude null-student overall reports and foreign identities server-side. Reuse the private streamed PDF reader (fread/finally, no fpassthru). A successful folder reimport replaces that person's active report; obsolete hashes no longer authorize a download.
