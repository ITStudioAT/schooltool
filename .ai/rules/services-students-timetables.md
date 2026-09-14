---
paths:
  - 'app/Services/StudentsTimetables/**'
  - 'app/Services/StudentsTimetables/*TimetableV3*'
  - 'app/Services/StudentsTimetables/StudentTimetableV3Timetable*.php'
  - 'app/Services/StudentsTimetables/*.php'
  - app/Services/StudentsTimetables/StudentTimetableExpectedModulesService.php
  - app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php
  - app/Services/StudentsTimetables/StudentTimetableCompletedCourseHistoryService.php
  - app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php
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

## Union Soll progression from every completion
For expected_additional_modules / Soll-Zusätzliche, union the next two numbered modules opened by every positive or exempt completed result, then remove all completed and negative modules. For example, completed E1 and E3 yield E2, E4, E5. This supersedes latest-completed-only progression.

## Union TT-V3 progression from every completion
For TT-V3 selectable modules, every positive or exempt completed module N unlocks N+1 and N+2. Union all unlocked windows, retain each family-and-module result, then exclude finished and negative modules. E1 and E3 therefore yield selectable E2, E4, and E5; do not use a fixed N-2 prerequisite or collapse additional results to one module per family.

## Apply progression after semester Soll rules
Subject-plan semester rules provide candidate SOLL modules but never bypass numbered progression. With no positive/exempt completion, only absolute module numbers 1 and 2 are valid—even when the plan's first rows are D2 and D3. A positive/exempt N opens N+1 and N+2; apply this same gate to expected_modules and expected_additional_modules before removing completed or negative modules.

## Keep unfinished lower religion modules in SOLL
For canonical religion progression R (including Ris, Rk, Rev, and Ror aliases), a positive/exempt completion of module N opens N+1 and N+2 but does not imply lower religion modules were completed. Keep every lower candidate unless it is explicitly completed or negative; for example, R2 completed without R1 yields R1 plus the opened R3/R4 window. Do not apply this lower-gap exception to unrelated subject bases.

## Keep SOLL result groups disjoint
For Tests V3 SOLL calculation, completed and negative snapshot modules belong only to Abgeschlossene or Negative. Remove those module codes from expected_modules and expected_additional_modules so they never reappear in Frühere, Aktuelle, or Zusätzliche. A visited ETH/ET result may still make ETH the effective SOLL religion selection, but the visited module itself remains excluded.

## Keep unfinished lower religion modules in TT-V3
When a visited ETH/ET result switches TT-V3 to ethics, completions from another religion variant may advance the shared progression but must not mark matching ETH modules completed. Keep lower unfinished ETH modules selectable unless that exact ETH/ET module is completed or failed.

## Do not advance ETH from religion after visited ethics
A visited ETH/ET result still switches TT-V3 to ETH, but completed R/Rk/Ris/Rev/Ror modules must not advance the ETH progression window in that case. Only positive ETH/ET results advance ETH; a negative ETH result selects ETH without advancing it, so the initial ETH1 and ETH2 remain eligible. This supersedes shared R/ETH progression for students with visited ethics.

## Mirror arts rules in TT V3 fallbacks
When no persisted subject-plan rule set exists, TT V3 fallbacks must preserve the same arts semantics: Wirtschaftskundlich chooses BE1 or ME1; Gymnasial requires both BE1 and ME1, and the arts choice filters only BE2 versus ME2. Apply branch eligibility before the arts choice.

## Do not infer a branch from shared modules
A completed module that matches subject-plan rows in both wirtschaftskundlich and gymnasial is branch-neutral and must not win by row order. Infer the branch only from an unambiguous branch-specific completion; otherwise keep a valid persisted Import116 study_selection.branch (then use the legacy school-level fallback).

## Keep every unfinished lower module in SOLL
A positive or exempt completion of numbered module N never implies that lower modules in the same family are complete. Keep every lower subject-plan candidate in expected_modules and expected_additional_modules unless that exact code is completed or negative; this applies to ETH and all other numbered module families and supersedes the religion-only lower-gap exception.

## Keep every unfinished lower module in TT-V3
A positive or exempt numbered module N opens N+1 and N+2 but never implies that lower modules in the same family are complete. Keep every lower subject-plan module selectable unless that exact module is completed or negative; apply this to every numbered family, not only D, ETH, or religion aliases. Negative modules remain unavailable and never advance progression.

## Memoize recognition subject-plan resolution per scope
Recognition history batches can contain thousands of rows from upper classes. Cache resolved subject labels, module label lists, and course names by school/schoolyear scope during the request; do not rescan the full subject-plan collection for every recognition row.

## Accept stable and legacy timetable course keys
Student overview course keys use the subject plan stable_key as their first segment. Robot setup must build stable keys, while still accepting legacy numeric-ID keys by matching the remaining six structured segments; diagnostics must return the exact requested key.

## Mapped module aliases belong in the internal course index
Resolve active school/schoolyear subject mappings for numbered TT module codes in the student overview's internal course index, preserving the module suffix (KG1 -> BE1, KG2 -> BE2). Keep shared course-group payloads and keys unchanged for alias-only fixes: V3 generation fingerprints include complete selected course groups, so adding display/alias fields there can invalidate otherwise unchanged saved results.

## Apply the shared numbered progression to arts modules
BE/ME/MU use the same planned/additional progression as mathematics; do not exclude arts from plannedCoursesForProgression. This permits eligible BE1+BE2 or ME1+ME2 without an earlier completion. Always apply the existing study-program, branch and arts-choice filters first: Gym keeps both first modules and only the chosen second; Wiku keeps only its chosen first module. Preserve the shared completed/negative-result handling.
