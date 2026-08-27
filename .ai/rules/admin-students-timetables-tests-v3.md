---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableExpectedModulesService.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Admin Students Timetables Tests V3

## Calculate Soll-Zusätzliche independently
For Tests V3, calculate expected additional modules independently from selected subject-plan tracks and stored result snapshots. Religion, branch, arts (BE/ME), and language choices are mandatory filters. In a numbered family, no passed/exempt result permits only the first two modules, while the latest passed/exempt result permits the next two; negative results never advance progression. Remove completed and negative modules from the eligible set, and include eligible unnumbered standalone modules such as VWA until they have a result. Compare this expected set with the V3 additional group and include it in overall OK/FAIL.

## Treat LPT and LPT1 as the same SOLL module
Normalize LPT1 to the unnumbered canonical LPT code in both backend expected-module filtering and the Tests V3 comparison. The existing LET alias follows the same rule (LET1 equals LPT), while LPT2 and higher numbered codes remain distinct.
