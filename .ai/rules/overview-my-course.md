---
paths:
  - '{app/Http/Controllers/Admin/Teaching/{TeachingController,TeachingCourseController}.php,app/Http/Controllers/Student/CourseController.php,resources/js/pages/admin/teaching/overview/components/CourseInfos.vue,resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue}'
---

# Overview My Course

## Keep course assigned-grade visibility separate from calculated columns
Course teaching_student_grade_columns JSON includes show_semester_grade and show_behaviour_grade, default true when absent. They are course-only settings; partial calculated-column saves must preserve them. Suppress hidden assigned grades in both student course list and detail responses. Hiding the behaviour grade must not hide behaviour entries; teacher-wide teaching_show_behaviour remains an overriding restriction.
