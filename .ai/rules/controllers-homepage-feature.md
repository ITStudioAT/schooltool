---
paths:
  - '{resources/js/pages/homepage/studentsTimetables/overviewV2/**,app/Http/Controllers/Homepage/StudentsTimetablesStudentController.php,tests/Feature/StudentsTimetablesModuleTest.php}'
---

# Controllers Homepage Feature

## Export the student manual timetable like the admin flow
On student manual adoption, show the large outlined copper PDF action beside Speichern. Export the currently displayed timetable through the authenticated homepage overview PDF endpoint with manual_cover=true, student/study-selection metadata, exact appointment and overlap fields, and the fixed warning cover; never call the admin endpoint.
