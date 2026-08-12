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

## Show selected modules instead of module search
Keep all module-type groups unfiltered and replace module search with a compact live overview of selected module codes and names. Show a clear empty state when no module is selected.

## Choose timetable creation mode before module selection
On the V3 modules step, ask “Soll der Stundenplan automatisch oder manuell erzeugt werden?” before a mode is chosen. Present exactly two side-by-side selectable cards: “Automatischer Stundenplan” and “Manueller Stundenplan”. Switch the heading to “Modulauswahl” and “Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?” only after automatic mode is selected.

## Use an orange module panel close button
Keep the solid square X button beside the open module-panel title orange, not red. Closing it clears only the active module type and preserves selected modules and courses.

## Gate module selection behind automatic mode
Each entry to the V3 modules step starts with neither timetable mode selected. The automatic card always carries the selected-module summary, but show the module-type cards and open module panel only after “Automatischer Stundenplan” is selected; “Manueller Stundenplan” intentionally shows no follow-up area until its workflow is implemented.

## Keep selected modules in the automatic mode card
Render the live “Ausgewählte Module” summary, including “Keine Module ausgewählt.”, inside the “Automatischer Stundenplan” card. Do not render a separate selected-module box below the mode cards; selecting automatic still reveals the module-type and detail workspace.

## Contrast module availability in timetable mode cards
Keep the automatic card focused on the user's selected modules. In the manual card, state explicitly that all modules and all lessons are available; this belongs inside the card rather than in a follow-up panel.

## Emphasize the selected timetable mode
Before a timetable mode is chosen, keep the two mode cards equal width. After selection, give the active card two thirds of the row and a stronger mode-specific color treatment; keep the inactive card at one third and stack both cards on narrow screens.

## Emphasize the open module type
On desktop, the open module-type summary card uses a 1.5 width factor relative to each sibling card. With no open type the cards remain equal width, and the existing two-column small-screen layout stays unchanged.

## Call module timetable choices Unterrichte
In visible German V3 UI copy, a module contains concrete `Unterrichte`, never `Kurse`. Use `Unterricht` / `Unterrichte` / `Unterrichten` in headings, counts, selection summaries, actions, and empty states. Keep internal API and JavaScript `course` field names unchanged.

## Disable module-step navigation while selection loads
While the modules route shows “Auswahl wird geladen”, keep both “Zurück” and “Weiter” disabled until the student selection details request finishes.

## Disable visible study choices while reloading
When V3 recalculates Studienauswahl, keep already loaded option buttons visible and disable them until the details request finishes. On the initial load, show only the loading feedback because no options exist yet.

## Allow bulk selection within the open module type
This supersedes the earlier individual-only module selection rule. In the open V3 module-type panel, provide group-wide actions labelled with that type (for example, “Aktuelle Module auswählen/abwählen”); selecting includes every available course key for those modules, and deselecting preserves selections from other module types.

## Confirm module course choices
Label the footer action in the persistent V3 Unterricht selection dialog “Bestätigen”, not “Schließen”. Course changes continue to persist immediately; the confirmation action closes the dialog.

## Remove modules from the automatic summary
Each selected-module chip inside the automatic mode card has an accessible red close action. Closing a chip removes that module and all of its selected course keys, persists the state, and leaves other selected modules untouched.
