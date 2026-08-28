---
paths:
  - resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue
  - 'resources/js/pages/admin/studentsTimetables/**'
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

## Prompt globally before missing subject-plan carry-forward
Check the personal schoolyear subject-plan carry-forward state in the shared StudentsTimetables shell so every module section shows one prominent warning. Auto-open the admin confirmation dialog, but never copy on page open; one confirmed request carries Normalstudium, Kompaktstudium, and mappings together and refreshes the active section.

## Remove the admin Stundenplan v2 entry
This supersedes the rules that keep Stundenplan v2 in the module navigation or use students_timetables.admin_version to select it. The admin Students Timetables shell exposes only Stundenplan v3; legacy timetable-v2 URLs redirect to /admin/students-timetables/timetable-v3/overview. Keep the underlying V2 implementation files intact for rollback.
