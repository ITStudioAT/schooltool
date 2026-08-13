---
paths:
  - 'app/Services/StudentsTimetables/**'
  - 'app/Services/StudentsTimetables/*TimetableV3*'
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

## Display imported weekly hours on V3 course cards
Course-card `hours_label` shows the calculated `scheduled_hours` from the imported recurring timetable slots, weighted by recurrence interval. Fall back to the selected study program's `usual_hours` only when no recurring schedule load can be calculated. Keep module-level hours and `usual_hours` as selected-study-program values for planning; use `regular_hours` from Normalstudium for Fernunterricht comparison.

## Compare regular timetable options with Normalstudium hours
Keep module-card hours tied to the selected study program. For V3 Fernunterricht detection, compare each non-compact concrete Unterricht's imported recurring weekly load with the module's Normalstudium hours; exactly half is Fernunterricht. Q–V Kompaktunterricht remains excluded because its missing half is self-study, not scheduled Fernunterricht.

## Keep completed V3 religion selections authoritative
In V3 Studienauswahl, a completed ETH/ET course selects ETH; otherwise a completed generic R resolves through import116.religion (or a specific religion code selects itself). A blank strict/persisted religion override must not erase that completed-course selection, while a non-empty explicit alternative remains allowed.

## Group V3 no-student modules by main module
For V3 requests without student_code, transform the available selection modules into naturally sorted main-module families by removing trailing module numbers from code/name (BU1/BU2 -> BU). Preserve each concrete module's existing selection_key, course payload, hours, and other selection data; requests with a student_code keep the five progression groups.
