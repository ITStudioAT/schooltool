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

## Restore completed religion choices after clearing
This narrows the general clearable-Studienauswahl rule: religion cannot remain cleared when a completed ETH/ET or religion module determines it. The backend restores ETH or the mapped confession; Vue must accept that returned value. Non-empty alternative choices remain selectable.

## Make timetable creation modes visually unmistakable
Keep the Automatischer and Manueller Stundenplan choices as large, color-distinct cards with prominent icons, plain-language explanations, and an explicit full-width choose/selected state. Preserve the stronger selected-card emphasis and the responsive single-column layout.

## Cap automatic V3 module selection
Automatic V3 planning may contain at most 10 selected modules and at most 30 total canonical module hours. Enforce both limits before individual, all-courses, or group-wide additions; allow exactly 10/30, show a warning for rejected additions, and constrain oversized persisted selections during restoration.

## Show timetable mode details only after selection
This supersedes the always-visible mode-card detail behavior. Hide the automatic selected-module summary until Automatic is selected, and hide the manual module-availability summary until Manual is selected; keep each summary inside its matching card.

## Use main-module drill-down without a student
When V3 plans without a student, show backend-provided main-module families (for example BU Biologie) instead of the five progression/status groups. Opening a family reveals its concrete modules and must reuse the existing module/course selection, limits, and persistence; planning with a student keeps the status groups.

## Keep the V3 modules step back-only
This supersedes the earlier rule that mentions disabling both module-step navigation buttons. The V3 modules route renders only the Zurück navigation action; do not render a Weiter button on this step. Keep Zurück disabled while student details or persisted state are loading/saving, and retain the overview step's Weiter action that enters the modules route.

## Show an inert automatic timetable CTA
When Automatic is selected, show a bottom-right “Stundenplan erstellen” button inside the automatic mode card. It is currently presentational only: its click must prevent default and stop propagation, with no timetable creation, navigation, state mutation, or accidental schedule-mode selection.

## Gate and emphasize the automatic timetable CTA
This supersedes the rule that shows the inert CTA as soon as Automatic is selected. Render “Stundenplan erstellen” only when selectedModuleCount is greater than zero, keep it bottom-right inside the automatic card, and style it as the prominent primary completion action. It remains inert until creation behavior is explicitly implemented.

## Open a static V3 creation stage from the automatic CTA
This supersedes the inert-CTA rule. With Automatic selected and at least one module chosen, “Stundenplan erstellen” navigates to the V3 creation subsection. Keep the complete current-selection summary, then show a static read-only automatic card without module remove actions or CTA and a completely empty second card reserved for future creation status. Render no module workspace beneath these cards and apply no hover/selection animation to them.

## Configure V3 creation options in the right static card
This supersedes the empty-right-card part of the creation-stage rule. The static right card is the creation-options panel; currently it exposes a boolean “Samstags Unterricht?” switch defaulting to no. Keep the card itself free of hover/selection animation. The creation-page Zurück action must use router history so it returns to the immediately previous view and preserves the automatic module view.

## Restore the complete V3 workflow after page reload
Every V3 subsection must survive F5 by awaiting persisted entry, planning, module, course, timetable-mode, and creation-option restoration before the step is considered loaded. Restore module data before any student hydration write so an incomplete reload cannot overwrite saved selections. On the creation step, Zurück navigates explicitly to the modules subsection as a reliable fallback when browser history was lost by a reload, while preserving the restored automatic mode.

## Make V3 F5 restoration deterministic
Persisted planning-value objects come back from MySQL JSON with normalized key order; compare their scalar/null key-value content, never raw JSON.stringify order, before restoring module/course selections. While the V3 draft and current catalog are restoring, render only a loading state and expose no mutating controls. Serialize full-state PUTs and await the latest save before navigating to creation so an older snapshot cannot overwrite newer module/settings state.

## Step back through V3 creation substates
On the V3 creation route, Zurück from calculation success/error first resets the in-memory calculation and returns to the Optionen/Los view. Only Zurück from the idle Optionen view navigates to the modules subsection. Keep Zurück disabled while calculation is running.

## Render all possible V3 timetable entries
Show backend-returned possible timetables one at a time in preserved array order with client-side index navigation. A slot must render its primary entry, every sameSlotEntries item, and every permitted conflicts item; never hide an Unterricht. Use timetable.key for identity, include Saturday only when allowed/used, and present exact backend times, recurrence/date ranges, and Unterricht markers in a responsive semantic weekly table.

## Use generic bulk-module action labels
In the open V3 module-type panel, label the group-wide actions “Alle auswählen” and “Alle abwählen” for every group. Render “Alle abwählen” with the Vuetify error color; selection behavior remains scoped to the open module type.

## Clear the automatic module selection in one action
When the V3 automatic timetable card has selected modules, show a red “Alle abwählen” action beside the creation action. It clears every selected module and course key together, clears the selection-limit warning, persists once, and must not toggle the enclosing mode card.

## Keep only the active V3 timetable page in browser memory
Own the selected timetable as a global zero-based index in TimetableV3.vue. Within a loaded 100-item page, navigate locally; at a page boundary fetch the scoped page with the active fingerprint and atomically replace the timetables array. Never append or cache prior pages, and ignore stale responses after resets or context changes.

## Start automatic timetable calculation from the module CTA
With automatic mode and selected modules, “Stundenplan erstellen” must persist the draft, navigate to creation, and start calculation immediately. The creation step always shows the automatic summary beside a status/result card; Saturday is fixed to Ja with no switch or Los action. Progress and the calculation result stay in the status card, while timetable or solution-plan output spans below both cards. Back returns directly to modules; restoring the creation URL remains read-only and must not silently recalculate.

## Place V3 calculation status in the automatic card
This supersedes the earlier success-card ownership of progress/results. On the creation step, mirror the modules page's selected-Automatic 2fr/1fr card ratio. Keep calculation idle/progress/error/result content in the wider automatic card; keep options and used modules in the narrower right card. Timetable and solution-plan output continues below and spans both cards.

## Show the manual mode card beside V3 creation results
This supersedes the right-card options/used-modules summary on the creation step. Keep the wider automatic card responsible for selected modules and calculation states/results. Render the narrower right card as a static, non-interactive orange “Manueller Stundenplan” card with the same title and description as the modules step; timetable and solution-plan output remains below both cards.

## Keep the manual timetable transfer CTA inert
On the creation step, the static manual card explains that the currently selected timetable can later be taken over for individual editing. Show a read-only “Stundenplan übernehmen” button with no click handler, route, or state mutation until the manual editor workflow is implemented.

## Sort selected V3 module summaries by code
Render “Ausgewählte Module” alphabetically by module code on both the modules and creation routes. Use natural German sorting so numeric codes such as D2 precede D10, and keep persisted selected-module keys and backend catalog order unchanged.

## Use a result heading after V3 calculation
On the V3 creation route, label the automatic card “Berechnung der Stundenpläne” while work is pending or running. Once calculation succeeds, switch the heading to “Ergebnis der Stundenplanberechnung” so the completed state is described semantically.

## Reserve a creation options card
On the V3 creation route, keep a static teal “Optionen” card for future timetable settings. Place it below the manual card in the narrow right column while the automatic calculation card spans both right-column card rows; stack all three cards in DOM order on narrow screens. Do not add option controls or click behavior until explicitly requested.

## Show real times in the V3 timetable grid
In the V3 possible-timetable table, show each visible period number together with the distinct starts_at–ends_at ranges already supplied by that timetable's primary course-group slots. Never hardcode school-hour times or invent a time for an empty intermediate period. Keep subtle outer, row, and column borders so weekdays and periods remain easy to track.

## Right-align the automatic recommendation
On the V3 modules route, keep the “Empfohlen” badge in the automatic timetable card aligned to the right of its header area. Preserve the title, mode-selection status, and responsive card behavior.

## Show timetable reload feedback in the options card
While a persisted V3 timetable page or option filter is loading, keep the current timetable visible without a centered overlay. Show the indeterminate loading status inside the teal Optionen card and keep filter/navigation actions disabled until the request completes.

## Show the active V3 option result count
In the V3 Optionen card, show the backend-provided filtered possible-timetable total as a compact badge on the selected option button only. Hide the badge while the filter request is loading so the newly selected option never displays the previous result count.

## Keep Saturday option counters compact and visible
In the V3 Optionen card, the section already supplies the label “Samstag”, so the toggle buttons must say only “Ja” and “Nein”. Show each backend-provided count before selection as a second line in the form “xxx Variante(n)”, and let the Vuetify button group grow beyond its density height so neither line is clipped. This supersedes the earlier active-option-only count display.
