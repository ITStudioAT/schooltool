---
paths:
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/**'
---

# Students Timetables Timetable V3

## V3 module selection is direct and backend-grouped
Show all five module groups together in one workspace; do not hide them behind tabs, dropdowns, or separate page jumps. Modules are directly clickable tiles. The backend supplies finished, negative, previous, current, and additional group membership plus defaults; Vue only searches, renders, toggles, and persists selection keys.

## Start V3 module selection empty
Do not preselect backend-recommended/default modules when a V3 module selection context is first loaded or recalculated. Start with no selected module keys; only restore explicit persisted selections when the saved planning context still matches.

## Keep V3 module selection individual
V3 modules are selected and deselected only through individual module tiles. Do not add group-wide select-all or deselect-all controls.

## Use compact module-type cards above one open group
This supersedes the collapsible section-header pattern. Keep all five compact module-type summary cards visible side by side; clicking one card shows that group's complete module list in one full-width panel below the card row. Start with no module type open, close an open type when its card is clicked again, and keep module selection individual.

## Keep module interactions subtly animated
Use only short, restrained motion for module-category switching and hover feedback (roughly 160–170 ms). Avoid continuous animation and always disable these transitions under prefers-reduced-motion.

## Do not pre-open a module type
Start the V3 module-type card deck with no active type and no detail panel. A card click opens that type; clicking the already active card closes it again without changing selected modules.

## Choose V3 courses in a persistent module dialog
This supersedes direct module selection from a tile. Clicking a module tile opens a persistent dialog containing the backend-supplied timetable courses; users select or deselect courses individually, and a module counts as selected while at least one of its courses is selected. Persist both module and course selection keys in the matching V3 planning context.

## Allow V3 study selections to be cleared
Clicking the currently selected Studienauswahl option clears that field. Persist cleared values as null, serialize them as empty query values so Axios does not omit them, and apply V3 selection overrides strictly so the backend does not restore the calculated default.
