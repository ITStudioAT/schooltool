---
paths:
  - '{app/Http/Controllers/Student/CourseController.php,resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue}'
---

# Student Overview My Course

## Expose only the current student's attendance on course dates
Student course dates expose only attendance_status (present, absent or null) for ParentStudentAccessService::currentStudent(), never the full attendance map or legacy att:* metadata. Explicit tri-state values take precedence; missing values imply presence only after attendance was checked. Future and cancelled/free lessons have no attendance display; past/today unknown values are labelled Nicht erfasst.

## Do not hide recorded attendance by date
Supersedes the earlier future-date exclusion: student course dates show recorded present/absent states even on future lessons; unknown states display Nicht erfasst. Only free/cancelled lessons omit the attendance badge. Preserve current-student-only exposure, explicit tri-state precedence, and checked-date fallback.

## Leave unrecorded student attendance blank
User preference supersedes previous Nicht erfasst labels: render an attendance badge only for present or absent. Null/missing attendance has no badge, including future lessons. Recorded future attendance remains visible; free/cancelled lessons still omit badges.
