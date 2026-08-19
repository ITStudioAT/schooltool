---
paths:
  - '{resources/js/pages/homepage/studentsTimetables/overviewV2/**,resources/js/stores/studentsTimetables/**,app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php}'
---

# Students Timetables Services Students Timetables

## Save manual timetable as the student personal version
In student V2 manual adoption, Speichern serializes the currently committed displayed timetable and stores it through the authenticated my-timetable endpoint. Scope stays with the student's school, schoolyear, user, and student code; never update StudentTimetablePublishedTimetable. Pending dialog choices are excluded, and saving refreshes overview data so the personal-start option becomes available.
