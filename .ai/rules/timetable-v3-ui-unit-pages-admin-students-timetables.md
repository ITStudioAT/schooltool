---
paths:
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/**,tests/ui/unit/pages/admin/studentsTimetables/**'
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue,tests/ui/unit/pages/admin/studentsTimetables/TimetableV3.test.ts'
---

# Timetable V3 Ui Unit Pages Admin Students Timetables

## Print only booked PDF rows and conditional Saturday
The manual timetable PDF payload includes only hour rows containing at least one course. Keep Monday–Friday columns, and include Saturday only when at least one Saturday cell is booked. This PDF rule does not change the full 1–15 grid shown in the interactive manual timetable.

## Match the manual PDF and back button sizes
Use Vuetify size=large for the manual timetable PDF action so it matches the Zurück navigation button. Keep the button's outlined copper treatment and loading/disabled behavior unchanged.

## Call the adoption main catalog Alle Module
On V3 adoption/manual timetable pages, label the main-module catalog selector and its accessibility labels “Alle Module”. Keep internal main-module keys and backend structures unchanged.
