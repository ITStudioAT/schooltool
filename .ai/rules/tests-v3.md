---
paths:
  - 'resources/js/pages/admin/studentsTimetables/testsV3/**'
---

# Tests V3

## Keep Tests v3 submenu empty until specified
Tests v3 defaults to /admin/students-timetables/tests-v3/students and exposes exactly two submenu items: Studierende and Tests. Both subsections contain no body content until product requirements are provided; legacy or invalid subsection routes redirect to Studierende.

## Show the personal schoolyear beside the Tests v3 title
Display the authenticated admin user's selected personal schoolyear directly after “Tests für Stundenplan Version 3”. Prefer selected_schoolyear.concerns, then name, while keeping both placeholder subsections otherwise empty.

## Select all personal-schoolyear students in Tests v3
This supersedes the rule that both Tests v3 subsections are empty. Studierende loads the existing school/personal-schoolyear robotStudents list through Wayfinder, displays every student ordered by class then last/first name, and supports per-row checkboxes plus Alle auswählen/Keine auswählen. The Tests subsection remains empty until specified.

## Show imported religion and sex in the Tests v3 student list
In the Tests v3 student list, render each row in the order name, imported religion, imported sex icon. Use the religion value unchanged after trimming. For sex, use only import116.sex: m is the blue male icon, w is the pink female icon, and unknown or missing values render no icon.

## Show compact study selections next to each Tests v3 student
Place the Studienauswahl column directly beside the compact name column. Show only non-empty normalized values in this order: religion, language, branch, arts subject. Uppercase the short values and display branch as WIKU or GYM.

## Show study program and semester together
In Tests V3 > Studierende, place Semester directly after Studienauswahl. Display the backend-provided study type as N (Normal) or K (Kompakt) before the semester and append the imported school level in parentheses, e.g. `N 5 (11_1)`.

## Carry selected students into Tests
When switching from Tests V3 > Studierende to Tests, retain the checked student keys and show those students as the current test selection. Returning to Studierende must keep the same checkbox selection; show an empty-state notice when no students were selected.

## Show one complete row per selected test student
The Tests subsection must render exactly one read-only table row per student selected under Studierende. Reuse the same class, name/religion/sex, study selection, semester, completed/exempted, and negative-result presentation so no student information is lost during transfer.

## Keep the Run Tests button presentational
In Tests V3 > Tests, show a visible `Run Tests` button in the selected-students header. Until the test workflow is specified, it must have no click handler, route, state mutation, or backend request.

## Run progressive V3 module checks for selected students
This supersedes the presentational-only Run Tests rule. Run Tests calls the existing V3 student-information Wayfinder action for every selected student with bounded concurrency and updates each row as soon as its request finishes. Display exactly finished, negative, previous, current, and additional with each backend group's count and module codes; do not reproduce V3 business calculations in Vue.

## Show V3 module results in a second student row
This supersedes the one-complete-row rule. Keep the first Tests table row identical to the transferred student overview; render V3-Modultest as a full-width second row directly below that student. Retain progressive per-student updates and the five backend-calculated groups.

## Show both study plans before selected test students
In Tests V3 > Tests, render one read-only "Module nach Semester" card immediately before "Ausgewählte Studierende". Load both personal-schoolyear Normalstudium and Kompaktstudium rows through the existing subject-plan settings action, group active rows by semester, and mark branch-specific modules GYM/WIKU; do not hardcode plan modules in Vue.

## Toggle Tests v3 students by class
In Tests V3 > Studierende, show one chip per loaded class. Clicking a class chip selects every student in that class unless the whole class is already selected; clicking a fully selected class chip deselects every student in that class.

## Show the matching semester modules for each test student
In Tests V3 > Tests, render a Module nach Semester row directly below each selected student's overview and before V3-Modultest. Select the Normalstudium/Kompaktstudium plan and semester from the same backend-provided values used for the student's N/K semester label (for example N 1); never derive this row from the class name.

## Compare snapshot completion with V3 finished modules
After each Tests V3 student calculation completes, compare the unique, case-normalized module-code set from the student's stored Befreit/Bestanden snapshot with the V3 finished/Abgeschlossene set. Show a green OK or red FAIL at the top right of the Abgeschlossene group; compare sets independent of order and treat two empty sets as matching.

## Compare stored and V3 negative module sets
After a student's V3 calculation completes, compare the stored Negativ snapshot module codes with the V3 Negative module codes. Normalize case, remove duplicates, and ignore order. Show green OK or red FAIL at the top right of the Negative group; two empty sets count as a match.

## Persist the Tests V3 student selection
Keep selected Tests V3 student keys in schoolyear-scoped browser storage so F5 restores them on both Studierende and Tests. Always reload the server student list after restoration and remove stored keys that are no longer available.

## Compare religion module variants generically
For Tests V3 finished/negative snapshot comparisons, treat Rev, Ris, Rk, and Ror module codes as the generic R module with the same number (for example Ris1 equals R1). Keep the displayed V3 module code unchanged.

## Build Soll modules independently
For the V3 module test, build `expected_modules` independently from active subject-plan rows through the student's current semester, resolve the stored study selection with the subject-plan rules, then subtract completed and negative snapshot modules. Compare this Soll set only with the union of V3 `previous` and `current`; never derive the Soll set from those V3 groups.

## Show the finished-module comparison in reading order
In Tests V3 > V3-Modultest, render Abgeschlossene with the snapshot Soll-Module first, a horizontal divider, and only the symmetric module differences below it. Place the Abgeschlossene OK/FAIL result at the bottom right; keep the other module groups unchanged until specified.

## Use the comparison layout for finished and negative modules
This extends the finished-only comparison rule. In V3-Modultest, both Abgeschlossene and Negative show snapshot Soll-Module, a horizontal divider, symmetric module differences, and OK/FAIL at the bottom right. Keep Frühere, Aktuelle, and Zusätzliche unchanged until specified.

## Color snapshot grades in module comparisons
In the Abgeschlossene and Negative comparison blocks, append each stored snapshot grade in parentheses after its module code. Render Abgeschlossene grades green and Negative grades red; show the same grade treatment for missing snapshot modules in the differences list.

## Align V3 comparison results
Render each module comparison result at the bottom right of its group. Keep the module-group columns as equal-height vertical flex containers so every OK/FAIL chip shares the same vertical position even when module lists have different lengths.

## Compare previous and current modules separately
Extend the V3 comparison layout to Frühere and Aktuelle. Derive Frühere Soll-Module from expected_modules rows before the student's current semester and Aktuelle Soll-Module from rows in the current semester; show the divider, symmetric differences, and bottom-right OK/FAIL. Keep Zusätzliche unchanged.

## Keep the selected-student summary compact
In Tests V3 > Tests, the selected-student overview row contains only selection, class, name, study selection, and semester. Omit Befreit/Bestanden and Negativ headers and cells there because those snapshots are shown in V3-Modultest comparisons; keep both columns in the Studierende selection table.

## Hide empty module differences
In V3-Modultest comparison groups, render the horizontal divider and Abweichende Module section only when the symmetric difference contains at least one module. When there are no differences, show only the Soll-Module and bottom-right OK result without an empty placeholder.

## Omit the separate per-student expected-module row
This supersedes “Show the matching semester modules for each test student”. In Tests V3 > Tests, do not render a separate “Soll-Module nach Semester” row between the selected-student overview and V3-Modultest. Keep expected_modules for the Frühere/Aktuelle comparison groups and keep the general “Module nach Semester” card.

## Show per-student V3 test status
In Tests V3 > Tests, use the leading student icon for test status: neutral before a run, clock while pending, progress icon while running, green check only when all finished/negative/previous/current comparisons pass, and red alert for comparison failures or request errors.

## Collapse both study-plan sections by default
In Tests V3 > Tests, render Normalstudium and Kompaktstudium as independently expandable sections. Both sections start collapsed when the page loads.

## Use semester Soll for previous and current groups
This supersedes the earlier rule “Use progression Soll for all open groups”. Frühere and Aktuelle must use expected_modules split at the student's current semester (< current / = current). Only Zusätzliche uses expected_additional_modules progression (> current).

## Show a persistent completion summary
After every Tests V3 run finishes, open a persistent dialog summarizing checked, passed, and failed student tests. List failed students with calculation errors or mismatching module groups; the dialog stays open until the user explicitly closes it.

## Batch V3 test transport supersedes per-student requests
This supersedes the bounded-concurrency per-student request rule. Run Tests uses sequential batches of at most 100 student codes; rows become running per batch and receive their canonical comparison groups when that batch completes.

## Completed SOLL results override negative
When the same normalized module code exists in completed and negative snapshots, Tests V3 SOLL comparison keeps it only under Abgeschlossene and excludes it from Negative. Use the existing comparison-code aliases; leave raw snapshot rows unchanged outside the SOLL comparison.

## Show Tests V3 progress in small batches
Run selected-student V3 checks sequentially in batches of 10 so medium selections receive intermediate results instead of one final update. While running, show a determinate progress bar with completed percentage and the number currently being processed.

## Skip Tests V3 for invalid student data
When the backend reports data_quality_issues for a student, keep the record visible and label it clearly as Falsche Daten with every reason. Do not send that student's code to the V3 calculation endpoint; mark the run as skipped invalid data and report it separately from passed or failed tests.
