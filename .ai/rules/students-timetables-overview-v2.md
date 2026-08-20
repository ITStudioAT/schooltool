---
paths:
  - '{resources/routes/homepage.js,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Students Timetables Overview V2

## Use student timetable V2 as the default overview
Route /students-timetables/overview to OverviewV2 so normal login, profile, password, and drawer navigation land on V2. Keep /students-timetables/overview-v2 as a direct alias and expose the former overview at /students-timetables/overview-v1.

## Keep only the manual student creation mode inactive
On /students-timetables/create, the automatic creation card opens the private student module and course selection workflow. Keep the manual creation card disabled and without a target URL until its student workflow is explicitly implemented. Do not route either creation choice to overview-v1.

## Reset new student timetable routes to the top
New navigations into /students-timetables/* must start at the top so reused OverviewV2 routes do not retain the overview card scroll position and hide the manual selected-module summary and module catalogs. Preserve savedPosition for browser back/forward navigation.
