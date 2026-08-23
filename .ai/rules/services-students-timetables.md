---
paths:
  - 'app/Services/StudentsTimetables/**'
  - 'app/Services/StudentsTimetables/*TimetableV3*'
  - 'app/Services/StudentsTimetables/StudentTimetableV3Timetable*.php'
  - 'app/Services/StudentsTimetables/*.php'
  - app/Services/StudentsTimetables/StudentTimetableExpectedModulesService.php
  - app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php
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

## Persist V3 timetable sets as a lesson catalog
Store generated V3 timetables in the existing JSON column as a versioned compact envelope: shared lesson payloads once, with each timetable slot retaining only primary/same-slot/conflict lesson references. Expand at the service response boundary so the API shape stays unchanged; read legacy list rows without mutating them, and validate the 8 MiB bound against the compact envelope.

## Treat resolved V3 modules as authoritative during generation
After V3 selection keys and underlying active course-group keys are resolved against the current server catalog, timetable generation must use the exact selected module codes. Do not filter or rewrite those modules from the student's religion, language, branch, or arts choices. Keep strict one-to-one module resolution, active-group completeness, ambiguity, and cross-module integrity checks.

## Resolve unnumbered authoritative V3 modules strictly
Authoritative V3 module codes may be all-letter unnumbered codes such as LPT. Match an empty numeric suffix only to subject rows whose module number is also empty, then retain active alias/mapping resolution and the exact one-to-one plus required-course-group validation. Bump the V3 algorithm version whenever resolution semantics change.

## Subject-plan rules are authoritative
Persist subject-plan rules by school, schoolyear, and study program using stable subject UUID keys. When a versioned rule set exists, recommendations and legacy V2 preparation must use the shared evaluator and must not fall back to code-pattern inference. V3-selected modules remain authoritative; include the rule-set version in its generation fingerprint.

## Advance additional Soll only from completed modules
Negative results never advance Soll-Zusätzliche. With no passed/exempt result, only the first two modules in a numbered family are eligible; remove every completed or negative module from that open set. After passed/exempt module N, allow the next two modules. Unnumbered standalone modules such as VWA are prerequisite-free until completed or negative.

## Apply V3 progression before removing failed modules
For V3 selectable modules, determine the completed-result progression window before selecting the first remaining candidate: modules 1-2 are initially eligible, while module N>=3 requires its positive prerequisite. Failed modules stay unavailable and must never cause module N+1 to become first available. Treat RIS, REV, ROR, and RK as aliases of the R religion family for progression.

## Visited ethics dominates religion without merging result identities
Any visited ETH/ET result, including a negative one, makes ETH the effective study selection and must be persisted by the student snapshot refresh. Religion aliases may share one progression/prerequisite family, but concrete result matching keeps ETH/Rk/Ris/Rev/Ror distinct (except ET/ETH synonyms) so one choice cannot hide another choice's module.
