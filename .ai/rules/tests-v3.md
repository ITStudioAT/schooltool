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
