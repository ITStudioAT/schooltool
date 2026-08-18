---
paths:
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/**'
  - resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue
  - resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue
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

## Restore the exact timetable on the adoption step
Stundenplan übernehmen opens `/admin/students-timetables/timetable-v3/adoption` only after a successful calculation. Carry the selected global index, timetable key, and calculation fingerprint in the route; restore the containing persisted page read-only and reject stale identity rather than showing another plan. The adoption page reuses Aktuelle Auswahl, shows Automatischer:Manueller Stundenplan in a 1:2 desktop grid, and renders only the selected timetable without navigation or editing controls.

## Place the adoption timetable below the mode cards
On the adoption step, the 1:2 Automatischer/Manueller row contains exactly the two mode cards. Render the selected timetable as a separate full-width block below that card row, never inside either card.

## Return from adoption to its remembered source
The adoption step provides a Zurück button below the selected timetable. Navigate explicitly to the remembered source subsection with the current planning context (`modules` for Manueller Stundenplan, `creation` for Stundenplan übernehmen); legacy or missing origins fall back to creation. Preserve the in-memory calculation between the two result-bearing steps and disable the action while state or timetable-page loading is active.

## Show only the manual card on V3 adoption
This supersedes the earlier two-card adoption layout. On the V3 adoption step, do not render the “Automatischer Stundenplan” card; render the “Manueller Stundenplan” card full-width and keep the selected timetable as a separate full-width block below it.

## Show selected modules in the V3 adoption card
On the V3 adoption step, render the read-only “Ausgewählte Module” count, hours, and naturally sorted module chips inside the full-width “Manueller Stundenplan” card. Do not add close controls or selection mutations; keep the selected timetable below the card.

## Open manual timetable from modules and remember its origin
On the V3 modules step, activating Manueller Stundenplan lazily restores the current persisted timetable page and opens adoption with its validated fingerprint, global index, and key; do not hardcode that identity or persist manual mode as the marker. Persist only a whitelisted adoptionReturnStep (`modules` or `creation`) in the workspace state so Zurück and invalid-adoption fallback return to the actual source after F5; legacy or missing values fall back to creation.

## Manual timetable entry never requires an automatic result
Manueller Stundenplan must always open from the modules step in both planning modes. Reuse a validated persisted timetable when one exists; otherwise open adoption without timetable identity as an intentional blank manual page, remember modules as the return source, and keep that page valid after F5.

## Open manual planning from modules without a timetable
This supersedes the earlier rule that reused a persisted timetable from the modules card. Activating Manueller Stundenplan on the modules step must always open adoption without fingerprint, timetable index, or timetable key, must not call the persisted-timetable endpoint, and must never render an in-memory automatic or official timetable there. Only Stundenplan übernehmen from creation may carry an existing timetable into adoption.

## Keep overview study selection compact
On the V3 overview route, render the editable backend-provided Studienauswahl inside a centered responsive card capped at 960px. Keep the existing clear-on-second-click behavior and loading/error states; compact spacing must not hide or recompute any selection option.

## Show study selection on manual timetable
On the V3 manual timetable/adoption page, show the backend-provided Studienauswahl in the shared compact read-only card above the manual timetable. Reuse compactPlanningSelectionItems; do not make this page another editable study-selection surface.

## Show module catalog on blank manual timetable
On a V3 manual timetable opened from modules, render the backend-provided module groups below the manual card using the same module-group card visuals and counts. Keep this catalog read-only until manual placement behavior exists; it must not trigger automatic selection limits, course mutations, or timetable restoration.

## Keep limits off the manual timetable card
This supersedes the earlier adoption selected-module summary rule. The V3 manual timetable card must not show the automatic 10-module/30-hour limit summary or selected-module chips; show the separate read-only module catalog below it instead.

## Keep restart available on the manual timetable
On the V3 adoption/manual timetable page, show Neustart beside Zurück. Reuse restartPlanning and disable the action while state is loading/saving or the timetable page is loading, so resetting always clears the planning workspace and returns to the overview.

## Number V3 workflow pages by the active branch
Show `Seite n` at the upper right of the V3 card header. Overview is 1, modules is 2, automatic creation is 3; manual adoption opened from modules is also 3, while adoption reached from automatic creation is 4.

## Use 2A and 2B for the automatic V3 branch
This supersedes the earlier sequential page-number rule. Show overview as Seite 1 and untouched modules as Seite 2; selecting Automatischer Stundenplan changes modules to Seite 2A, creation is Seite 2B, and the manual/adoption page is Seite 3.

## Label automatic timetable transfer as page 3A
This refines the V3 page-label rule: direct manual timetable entry from modules is Seite 3. Clicking Stundenplan übernehmen on Seite 2B opens adoption as Seite 3A; distinguish the two with the remembered adoption return step.

## Hide page labels until explicitly assigned
Only render the upper-right Seite label for explicitly numbered V3 states. Currently those are overview 1, modules 2, automatic modules 2A, creation 2B, and automatic adoption 3A; direct manual adoption and future states remain unnumbered until specified.

## Label other manual timetable entries as page 3B
Refine the explicit V3 labels: adoption reached through Stundenplan übernehmen is Seite 3A. Every other entry into the Manueller Stundenplan page, identified by the modules return origin, is Seite 3B.

## Render page 3B as an empty timetable
On manual timetable page 3B, render the existing timetable grid in an explicit empty mode instead of hiding it or showing the missing-result warning. Use ten Monday-to-Friday period rows without invented times or lessons; page 3A continues to render the transferred calculated timetable.

## Use configured school-hour rows on page 3B
This refines the empty page-3B grid rule: load the existing tenant/schoolyear school-hours endpoint through its Wayfinder action and render those configured hour numbers and from–until ranges. Keep the same normal timetable component/styles, show Monday through Saturday, and use the ten untimed fallback rows only when no configured hours can be loaded.

## Split the student manual catalog heading
On manual page 3B with planning_mode=with_student, label the existing catalog heading “Studierenden Module” and render a second equally sized read-only “Hauptmodule” heading card beside it. Stack the two heading cards on narrow screens. Without a selected student, keep the single Hauptmodule heading.

## Start the empty page-3B grid at period 6
The empty manual timetable on page 3B starts at period 6 and continues without gaps through the last configured school hour, preserving configured time ranges. If school hours are unavailable, render ten empty rows covering periods 6–15.

## Start the empty page-3B grid at period 1
This supersedes the period-6 rule. The empty manual timetable on page 3B starts at period 1 and continues without gaps through the last configured school hour, preserving configured time ranges. If school hours are unavailable, render ten empty rows covering periods 1–10.

## Drill into the manual V3 module catalog read-only
On manual timetable page 3B, Hauptmodule/module-type cards may disclose their concrete modules and a module may open its Unterricht details. This drill-down is browse-only: keep separate transient UI state, hide automatic selection actions/checkbox semantics, and never mutate or persist selected module/course keys.

## Place manual courses without automatic selection
On manual page 3B, clicking a concrete Unterricht toggles all of that course's structured timetable entries in the manual grid. Keep manual course keys transient and separate from automatic selectedModuleKeys/selectedCourseKeys; never run automatic limits or saveState for manual placement. Preserve the configured full hour grid and render additional entries in the same cell through sameSlotEntries.

## Show adoption module summary and catalogs
On every V3 adoption page, show Studierenden Module/Hauptmodule catalog cards when student details are available. The manual timetable card always contains a read-only Ausgewählte Module summary: page 3A derives it from the transferred automatic module selection, while page 3B derives it from transient manually placed course keys. Keep 3A catalog course details browse-only; manual placement remains limited to page 3B.

## Hide placed manual courses across catalogs
On manual timetable page 3B, once an Unterricht is placed, hide it from both Studierenden Module and Hauptmodule course dialogs using any overlapping canonical course key. Reject repeat placement at the mutation boundary as well; catalog switching must not make a placed course selectable again.

## Allow manual placement on adoption page 3A
This supersedes the earlier rule that kept page 3A catalog details browse-only. Studierenden Module and Hauptmodule must allow manual Unterricht placement on both 3A and 3B. On 3A, overlay transient manual entries on the transferred timetable without mutating it, and treat transferred plus manually added course-group keys as already placed so neither catalog permits duplicates.

## Confirm manual course placement explicitly
Manual Unterricht clicks in the adoption dialog are draft selections only. The footer action is Verplanen and commits them to the manual timetable; Abbrechen clears the draft and closes without changing placed courses. Opening, closing, switching catalogs, or resetting the planning context must clear pending manual course keys.

## Highlight allowed manual timetable overlaps
Manual placement may keep multiple Unterrichte in the same weekday/hour cell. On adoption pages, preserve and render every entry, mark each multi-entry cell prominently as Mehrfachbelegung, and show a visible status explaining that an overlap is possible and allowed. Do not apply this extra manual-editor warning to ordinary automatic timetable result browsing.

## Hide variant position on manual timetable
On every V3 adoption/manual timetable page, the timetable heading is exactly Stundenplan and never displays a variant position such as 1 von 15. Keep positions visible in automatic possible-timetable result browsing, where users navigate among variants.

## Remove modules only from the manual timetable draft
On page 3 adoption, each selected-module chip has a red close action. Removing a module removes all of its course-group keys from the displayed manual timetable, updates the manual module count/hours, and exposes those courses for selection again. Keep this removal transient and isolated: never mutate or persist the automatic selectedModuleKeys/selectedCourseKeys or the generated source timetable.

## Persist manual timetable drafts across reloads
This supersedes the earlier transient-only manual placement/removal rules. Persist committed manual selected course-group keys and removed course-group keys in the workspace-scoped V3 state, separately from automatic selectedModuleKeys/selectedCourseKeys. For page 3A, bind restoration to the exact generated fingerprint, timetable key, and global index; for page 3B, bind it to the blank-manual source. Revalidate keys against the current module catalog, rebuild slots/overlaps from current structured course data, and never persist pending dialog choices or mutate the generated source timetable.

## Use course-group keys for adoption membership
Restored automatic timetable entries can carry a Robot lesson identity in entry.key, while module course DTOs reference the canonical course-group identity. For manual-adoption placed-course summaries, duplicate prevention, and removal, resolve entry.courseGroup.key before entry.key across primary, same-slot, and conflict entries.

## Show one canonical module code in V3
Visible V3 Unterricht labels must use the module/catalog code rather than imported timetable aliases (for example GW2, never GWB2). Apply this to dialogs, manual and automatic/restored timetable entries, same-slot/conflict entries, and accessibility labels; keep legacy aliases only for internal matching and stored identities.

## Use the canonical LPT name
Every visible V3 module reference with canonical code LPT uses the full name “Lern- und Präsentationstechniken”, even when imported or persisted source data still says LET, LPT, or “Literarisches Praktikum”. Keep those legacy values only for matching and stored identities.

## Call manual overlaps Einzeltermin-Überschneidung
On manual/adoption timetables, label cells containing multiple or overlapping Unterrichte as „Einzeltermin-Überschneidung“, never „Mehrfachbelegung“. The visible allowed-overlap status uses the same term.

## Reuse automatic overlap styling in manual timetable
Manual/adoption Einzeltermin-Überschneidungen use the same amber lesson card, left warning border, calendar-alert icon, and marker text as automatic timetable overlaps. Manual overlaps remain allowed; communicate that with `· erlaubt` in the shared warning status instead of introducing a separate red cell frame or multi-occupancy badge.

## Use calm teal for the manual timetable card
This supersedes the earlier orange-manual-card rule. Across modules, creation, and adoption, the Manueller Stundenplan card uses calm teal (`#0f766e`) with a pale teal-to-white surface (`#f0fdfa`) and light teal border (`#99f6e4`). Keep its related transfer action and module chips teal while remove controls remain red.

## Use white with copper accent for the manual timetable card
This supersedes the calm-teal manual-card rule. Across modules, creation, and adoption, keep Manueller Stundenplan on a white surface with neutral `#e2e8f0` border and a thin copper `#c2410c` top accent. Selected emphasis, the transfer action, and module chips use copper; remove controls remain red.

## Close module-type panels from the active card only
This supersedes the orange module-panel X-button rule. Automatic and manual module-type detail panels must not repeat the active type's icon, title, description, or count and must not render a separate close button. Clicking the already active module-type/Hauptmodule card closes its panel; automatic bulk selection actions may remain above the module grid.

## Keep overlap notices inside timetable lessons
This supersedes the visible manual allowed-overlap status rule. Do not render Einzeltermin-Überschneidung or Einzeltermin-Überschneidung · erlaubt beside the Stundenplan heading. Keep overlap styling, icon, and marker text on the affected Unterricht card inside the timetable grid.

## Mark planned modules in the manual catalog
In the manual timetable module catalog, show “Bereits verplant!” as the first row of a module card whenever any canonical course key from that module is already present in the displayed timetable. Derive this from adoptionPlacedCourseKeys so transferred and manually added Unterrichte are both covered.

## Mark fully planned manual module types
In the manual timetable catalog, show the same “Bereits verplant!” badge on a module-type/Hauptmodul card only when the group is non-empty and every contained module is already planned according to adoptionPlacedCourseKeys.

## Focus the selected manual Hauptmodul
In the manual Hauptmodule catalog, selecting a Hauptmodul hides its sibling Hauptmodul cards and makes the active card span two grid columns while its concrete modules are disclosed. A second click closes it and restores all Hauptmodule cards. Do not apply this focus behavior to the five Studierenden Module status cards.

## Show specific V3 religion module names
Visible V3 module names must resolve religion codes specifically instead of displaying the generic source name Religion/Ethik: ET/ETH = Ethik, Rev = Religion evangelisch, Ris = Religion Islam, Rk = Religion katholisch, and Ror = Religion orthodox. Keep internal codes and source names unchanged.

## Mark fully unintended manual Hauptmodule
In the manual Hauptmodule catalog, show the red “Nicht vorgesehen!” badge on a Hauptmodul card only when the group is non-empty and every contained concrete module has backend-provided is_intended_for_selection=false. Do not reproduce Studienauswahl eligibility rules in Vue.

## Show the complete manual timetable day
On every V3 manual/adoption timetable, render at least periods 1–15 and preserve configured school-hour from–until ranges for each row. Automatic possible-timetable browsing may continue to use its occupied-hour range.

## Number and explain timetable overlaps
Number visible V3 overlap markers in weekday/hour order starting at 1. Below the timetable, repeat that reference on one line per involved Unterricht and list every exact course date; color a date red only when another involved Unterricht occurs on that date with an overlapping time interval.

## Right-align only in-cell overlap references
Right-align the numbered calendar-alert reference inside each affected Unterricht card. Keep the !n references in the Überschneidungen summary below the timetable left-aligned.

## Warn only for exact manual overlaps
This supersedes the rule that every multi-entry manual cell is an Einzeltermin-Überschneidung. Keep same weekday/hour courses visible together, but show overlap styling, marker, counter, and summary only when their exact date sets intersect and their time intervals overlap; disjoint date series stay normal.

## Visually group overlap summary entries
In the Überschneidungen summary below the timetable, keep each numbered overlap as its own bordered amber group. Render the left-aligned !n references as bordered badges so all course lines belonging to one overlap are visibly connected; do not change the right-aligned references inside timetable lessons.

## Name overlaps by exact date count
This supersedes the rule that every manual overlap is called Einzeltermin-Überschneidung. Use “Einzeltermin-Überschneidung” only when a numbered conflict group has exactly one distinct exact date with an overlapping time interval. Use “Überschneidungen” for multiple overlapping dates and whenever no single exact overlap date can be established; apply the same label to visible lesson markers and aria labels.

## Put one-date overlap warning on the one-off course
When an exact overlap has exactly one course with one date, place the overlap card styling, icon, counter, and label on that one-date course instead of the technical conflicts entry. Hide Kompaktunterricht and block markers on one-date courses.

## Publish the current V3 adoption timetable
On the V3 adoption page, show the publish/save action only when a student is selected. Label it with the student's last name and publish the current adoptionDisplayedTimetable through the generated publishStudentTimetable Wayfinder action; keep the PDF button beside it.
