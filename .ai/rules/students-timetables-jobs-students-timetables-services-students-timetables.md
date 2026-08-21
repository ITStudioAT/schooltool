---
paths:
  - 'app/{Http/Controllers/Admin/StudentsTimetables,Jobs/StudentsTimetables,Services/StudentsTimetables}/**/*.php'
---

# Students Timetables Jobs Students Timetables Services Students Timetables

## Run manual student snapshot refresh in the background
The Importe/Datenaktualisierung action is admin-only, is scoped server-side to the authenticated user's school and personal schoolyear, and queues one active refresh per scope. Persist progress in student_timetable_data_refreshes; update Import116 study_selection and course_results through StudentTimetableStudySelectionRefreshService, then expose only the persisted status and summary to Vue.
