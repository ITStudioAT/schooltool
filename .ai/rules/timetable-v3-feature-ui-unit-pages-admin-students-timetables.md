---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController.php,resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue,routes/api.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/TimetableV3.test.ts}'
---

# Timetable V3 Feature Ui Unit Pages Admin Students Timetables

## Scope the student-view switch to dedicated timetable accounts
The admin student-card switch may be used only by super_admin, admin, and studentstimetables_admin. Resolve the target again by student code within the authenticated school and personal schoolyear; require an active linked account whose only role is studentstimetables_user. Start the existing Lab404 impersonation session and redirect to /students-timetables/overview; moderators and mixed-role accounts must never receive the action.

## Allow the standard base student role during student-view switching
This supersedes the exact-single-role part of the earlier student-view rule. A valid target must have studentstimetables_user and may additionally have the standard student role, because imported student accounts normally carry both. Reject every other role, including user and all admin roles; keep the existing school, personal-schoolyear, active-account, and linkage checks.

## Allow timetable staff to open the scoped student view
This supersedes the launcher-role and pre-existing-link requirements in the earlier student-view rule. The Timetable V3 student-card switch is available to super_admin, admin, studentstimetables_admin, and studentstimetables_moderator. Do not hide it merely because the imported student account has not been linked or assigned studentstimetables_user yet; safely resolve or provision that account from the selected current-school/current-personal-year Import 116 row on click. Reject inactive accounts and any target with roles outside student and studentstimetables_user, and keep the resulting impersonation timetable-only.
