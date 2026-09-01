---
paths:
  - 'resources/js/pages/admin/studentsTimetables/subjectsOverview/**'
---

# Pages Admin Students Timetables Subjects Overview

## Grafik v2 renders compact subject plans
Grafik v2 uses the active, personally scoped Kompaktstudium subject rows for semesters 1–5. Sequential modules stored in the same compact semester render as a combined label such as L6+7, while rule alternatives stay selectable alternatives and totals count exactly one option.

## Use one subject-row order for both study programs
The Fächer table uses the same deterministic display order for Normalstudium and Kompaktstudium: semester, then common/Gymnasial/Wirtschaftskundlich branch, then JSON code with numeric comparison. Never expose the program-specific import or seed sort order as the default UI order.

## Label the compact presentation-work column as VWA
For Kompaktstudium, the shared internal LPT/VWA subject column is displayed with the title VWA because no LPT module exists in that plan. Normalstudium keeps the visible title LPT/VWA.

## Hide hours and totals in compact Grafik v2
Grafik v2 for Kompaktstudium renders only semester and subject/module cells. Do not render per-cell hours, the SUMME column, or the SUMME footer row there; Normalstudium keeps hours and both totals.

## Do not show the compact hours explanatory alert
Do not render the former Kompaktstudium alert about school teaching units and excluded self-study in the subject overview graphics.

## Open Fächer on Grafik v2 by default
The main Fächer navigation target and a direct subjects-overview route without a subsection resolve to subject-plan-v2. Removed or unauthorized subject subpages also return to Grafik v2; the embedded legacy graphic remains unchanged.

## Expose one Grafik submenu with role-scoped subject settings
Label subject-plan-v2 as Grafik and expose it as the only Fächer submenu item for studentstimetables_moderator. Admin roles additionally see Fächer, Regeln, and Zuordnung. Do not expose the legacy subject-plan menu item; direct legacy or unauthorized subject subroutes redirect to subject-plan-v2, while embedded legacy rendering may remain for compatibility.
