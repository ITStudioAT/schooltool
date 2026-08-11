---
paths:
  - 'app/Services/StudentsTimetables/**'
---

# Services Students Timetables

## Use compact-specific student semester progression
After classifying the student by the shared Kompaktunterricht class-name rule, map compact school levels as 09_1→1, 09_2→2, 11_1→3, 11_2→4, and 12_2→5. Normalunterricht keeps the 09_1→1 through 12_2→8 mapping. Do not guess unlisted compact levels; allow the completed-course fallback.

## Resolve generic religion courses from the imported confession
A recognized course code R is generic religion and must not be interpreted as Catholic. Specific codes such as RK, REV, RIS, and ROR may determine the selection; otherwise resolve generic R through import116.religion. Explicit ETH remains a valid course-history selection.

## Identify recognition records by module ID
Recognition CSV exports are snapshots. Use the stable imported `modulid` (scoped by school and schoolyear) as the primary record identity; never hash mutable display fields such as student names. Repeated exports of the same module ID are one record, while equal grades with different module IDs are distinct attempts. Legacy duplicate rows must be collapsed at read time without deleting source data.

## Limit V3 selectable modules by student progression
For a selected V3 student, build selectable modules with the shared progression rules: no positive/exempt M allows M1 and M2, positive/exempt M1 allows M2 and M3, and higher modules stay hidden until their prerequisites are met. Keep failed modules in the negative group and leave the no-student module catalog unrestricted.
