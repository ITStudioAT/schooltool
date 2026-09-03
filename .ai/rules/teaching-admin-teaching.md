---
paths:
  - '{app/Http/Controllers/Admin/Teaching/TeachingCourseController.php,app/Services/TeachingClassHeadEmailService.php,resources/js/pages/admin/teaching/overview/components/MyCourses.vue,resources/js/stores/admin/teaching/CourseStore.js,tests/Feature/Controllers/Admin/Teaching/TeachingCourseControllerTest.php,tests/ui/unit/{components/admin/teaching/MyCourses.test.ts,stores/admin/teaching/CourseStore.test.ts}}'
---

# Teaching Admin Teaching

## Select class heads from the teacher roster
Klassenvorstand assignments accept teacher_1_id/teacher_2_id only. Validate each ID as an active Teacher in the course school, prohibit raw email fields, and expose no teacher email in selector options. TeachingClassHeadEmailService resolves selected teacher IDs to the legacy email_1/email_2 columns so existing notification delivery remains compatible.

## Select class heads from active teacher accounts
This supersedes the teacher-roster selection rule: teacher_1_id/teacher_2_id refer to User IDs, never Teacher preregistration IDs. Selector options, saved-email matching, and server validation must use active users with the teacher role in the course school. Continue prohibiting raw email inputs and omitting emails from selector options; resolve the selected users' emails into the existing email_1/email_2 columns for notification compatibility.
