---
paths:
  - 'resources/js/pages/homepage/studentsTimetables/overviewV2/**,tests/ui/unit/components/homepage/studentsTimetables/StudentTimetablesOverviewV2.test.ts'
---

# Components Homepage Students Timetables

## Reload saved timetable sources on overview return
Overview, creation, results, and adoption reuse OverviewV2. Before a reused component enters /students-timetables/overview or /overview-v2, reload the authenticated overview so a newly teacher-published timetable appears after Neustart, Zurück, or browser back without a manual page refresh.
