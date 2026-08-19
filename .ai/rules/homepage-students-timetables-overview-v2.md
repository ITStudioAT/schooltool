---
paths:
  - '{app/Http/Controllers/Homepage/StudentsTimetablesStudentController.php,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Homepage Students Timetables Overview V2

## Reuse V3 student information on the student overview
OverviewV2 information and study buttons mirror the admin V3 hover cards and persistent dialogs. Supply the authenticated student's calculation items and module result groups through `student_information` on the existing student overview response by reusing StudentTimetableV3StudentInformationService; never call an admin API or recalculate grouping in Vue. Show the semester without the parenthesized imported school level, and keep the email display read-only without a copy action.

## Keep student study selections private and extensible
OverviewV2 Studienauswahl is student-editable using backend-provided selection_fields/options and card/chip controls. Persist overrides only through StudentTimetableProfileSelection per user and schoolyear; never mutate Import116 or expose/merge these overrides into admin V3. Profile update/reset responses must return enriched student_information so the UI remains consistent. This supersedes the earlier read-only compact-card rule.
