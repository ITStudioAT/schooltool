---
paths:
  - 'app/Services/StudentsTimetables/**,app/Http/Controllers/Admin/StudentsTimetables/SubjectOverviewJsonUploadController.php,resources/js/pages/admin/studentsTimetables/subjectsOverview/**'
---

# Students Timetables Subjects Overview

## Subject-plan carry-forward is one explicit operation
Opening the subject overview must never create subject-plan data. When the personal schoolyear has no subject rows, show one carry-forward prompt across Grafik, Fächer, Zuordnung and both study-program views. The admin-only POST copies Normalstudium, Kompaktstudium, and mappings together from the immediate previous schoolyear exactly once.
