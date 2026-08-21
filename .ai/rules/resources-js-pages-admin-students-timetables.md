---
paths:
  - resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue
---

# Resources Js Pages Admin Students Timetables

## Keep timetable V3 primary and V2 at the far right
The Students Timetables module defaults to the per-school V3 entry. Keep Stundenplan v3 first in the module navigation and keep the independently reachable Stundenplan v2 legacy item aligned at the far right before settings.

## Keep Stundenplan v2 last without right alignment
This supersedes the former far-right legacy alignment. Stundenplan v2 remains the last regular module-navigation item, directly following the preceding item with the normal menu gap; only the settings cog stays separately right-aligned.

## Place Tests v3 directly after Stundenplan v3
The main module navigation order starts Stundenplan v3, Tests v3, then the existing administration items, with Stundenplan v2 remaining last. Tests v3 routes to /admin/students-timetables/tests-v3/overview and its placeholder body contains only the heading “Tests für Stundenplan Version 3”.

## Open Tests v3 on Studierende
This supersedes the earlier Tests v3 /overview placeholder route. The main Tests v3 item opens /admin/students-timetables/tests-v3/students and renders the dedicated TestsV3 submenu component.
