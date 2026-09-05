---
paths:
  - '{app/Http/Controllers/Student/CourseStudentEntryController.php,app/Services/TeachingCourseStudentEntryService.php,resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue}'
---

# My Course

## Split modern student entries by the course entry definition category
For entry-area schoolyears, TeachingCourseStudentEntry stores Benotung, Verhalten and Weitere alike. Resolve categories and labels through the course owner's school/year-scoped entry area; storage model or short code does not identify the category. Only Benotung belongs in performance/grade calculations. Merge modern Verhalten with legacy behaviour entries in the student display, and suppress modern Verhalten server-side when teaching_show_behaviour is false. Older schemas retain Benotung fallback.
