---
paths:
  - 'resources/js/pages/homepage/studentsTimetables/overviewV2/**'
---

# Overview V2

## Keep student timetable V2 full-width and independent
The student-facing timetable V2 lives at /students-timetables/overview-v2 and must remain separate from the existing overview. Its hero and content use the full available viewport width and the hero carries the existing student's identity, class, religion, completed/negative courses, and timetable selection summary.

## Use a compact card-based V2 dashboard header
Keep the V2 overview full width, but do not use one oversized orange hero. Use a compact toolbar and separate responsive cards for welcome/date, student identity, course history, and selection details. Orange is an accent, not the dominant surface.

## Use one continuous V2 header surface
This supersedes the card-based dashboard-header rule. Keep the V2 header as one compact, continuous light surface with a thin orange accent. Organize navigation, introduction, student/date facts, course history, and selection details through typography and spacing; do not split them into separate dashboard cards.

## Keep the V2 overview header minimal
Do not render the Zur Startseite action, explanatory intro sentence, student initials/name identity block, or Vorbereitung placeholder card on the student timetable V2 overview. Keep the remaining welcome, facts, course history, selection summary, logout, and drawer navigation.

## Show only the welcome in the V2 overview body
This supersedes the earlier V2 header-content rules. Do not render date, class, religion, course history, or timetable-selection summaries in the V2 overview body. Keep the wordmark, welcome message, logout action, and drawer navigation.

## Mirror the admin current-selection card on V2
This supersedes the welcome-only body rule. Directly below the welcome heading, show an admin-V3-style Aktuelle Auswahl card using the authenticated student's class, surname/first name, religion, gender icon, email, and email-copy action. Continue to omit the old date, course-history, and timetable-selection-summary sections.

## Keep the student card unlabeled
The admin-style student-information card below the V2 welcome heading must not display the text “Aktuelle Auswahl”. Preserve the card’s student details and email-copy action.

## Show the compact study selection below the student card
Directly below the OverviewV2 student-information card, render the admin V3 compact read-only Studienauswahl card. Reuse the four backend-calculated student_information items (religion, language, branch, arts subject) and the admin card styling; do not add semester or duplicate selection calculations in Vue.

## Offer three student timetable starting points
Below Studienauswahl, show three admin-style side-by-side start cards: an always-available empty timetable, the student's private saved timetable when personal_timetable exists, and the teacher-published timetable when published_timetable exists. Keep unavailable saved sources visible but disabled. Empty opens the student creation-mode page; personal and published open the existing student workspace through overview-v1. Do not call admin APIs.

## Mirror the admin recommendation badge
On the student creation-mode page, render the automatic “Empfohlen” badge with the same yellow gradient, border, shadow, uppercase typography, dedicated 27px header row, and right-aligned kicker position as the admin V3 schedule-mode card.

## Place creation back action in the footer
On the student creation-mode page, keep the Zurück action below the mode cards at the bottom right. Match admin V3 with a large primary outlined VBtn and prepended mdi-arrow-left; do not place a second back action above the workspace.

## Keep the creation footer close to the cards
The student creation-mode Zurück action is right-aligned directly below the mode workspace with a compact 24px separation. Do not use viewport min-height or margin-top:auto to push it to the bottom of the screen.

## Keep automatic timetable selection student-private
On /students-timetables/create, Automatic uses the admin-style 2:1 module-selection UI with 10-module/30-hour limits, clickable module groups, bulk actions, and a persistent course dialog. Populate it from student_information.module_selection_groups and keep selections transient: do not call admin endpoints or persist official profile changes. Manual remains disabled until explicitly implemented.

## Mirror the admin V3 automatic result layout
The student automatic creation/results stage mirrors the current admin V3 creation layout: a 2fr automatic card spanning two rows, a white copper-accent manual card, a teal options card, and the 20-segment LED progress display. Reuse the admin timetable component and result/count/filter presentation; keep the student manual transfer button inert until its workflow is explicitly requested.

## Reset planning state when starting empty
Clicking Leer beginnen must clear all transient timetable-planning state before opening /students-timetables/create: creation mode, module/course selections, open module UI, calculation workspace/results/progress/errors, paging, and filters. Invalidate in-flight calculation/page requests. Do not reset the student's private persisted Studienauswahl.

## Repeat the student information card on creation
On /students-timetables/create, render the same authenticated-student identity card directly above the creation workspace. Include both existing information actions with their hover previews and persistent click dialogs; reuse the overview data and handlers.

## Show Studienauswahl read-only on creation
Directly below the repeated student card on /students-timetables/create, show the current backend-provided Studienauswahl values in the compact card design. This creation copy is display-only: no reset, pencil, click, or selection controls. Keep editing confined to the overview.

## Keep Neustart on student creation steps
On both /students-timetables/create and /students-timetables/create/results, show an admin-style red outlined Neustart action left of Zurück. It clears transient planning state through resetStudentTimetablePlanning and router-replaces /students-timetables/overview; disable during calculation or timetable-page loading. Preserve the student's private Studienauswahl.

## Show student identity throughout creation
Render the same authenticated-student identity card, including both information hover/actions and dialogs, on /students-timetables/create and /students-timetables/create/results. Share the card markup across both route branches; keep the read-only Studienauswahl card limited to /create.

## Align planning student card with following content
On /students-timetables/create and /students-timetables/create/results, keep the shared student card inset to the same horizontal edges as the following Studienauswahl/result cards. Use a compact 16px vertical gap to the next card; preserve the existing student-card visual treatment and responsive route-specific insets.

## Show planning selection throughout creation
This supersedes the earlier rule limiting the read-only Studienauswahl to /create. Render the same shared, backend-provided read-only card directly below the student identity card on both /students-timetables/create and /students-timetables/create/results, with route-specific insets aligned to the following content; editing remains confined to the overview.

## Open the student manual-adoption shell
This supersedes the inert student result transfer CTA. Stundenplan übernehmen opens /students-timetables/create/adoption with the user-scoped workspace, fingerprint, global timetable index, and timetable key so the exact paged result can be restored. At this stage render only the shared student card, read-only Studienauswahl, and selected Manueller Stundenplan module summary; do not show PDF, save, timetable-grid, editing, or persistence controls until requested.

## Keep student adoption edits transient and student-scoped
The student adoption page may reuse the authenticated overview catalogs (`module_selection_groups` and `main_module_selection_groups`) and the shared V3 timetable renderer. Manual Unterricht choices remain draft-only until `Verplanen`, then merge locally into the selected result while excluding already placed course keys. Do not call admin endpoints or add PDF/save/persistence unless explicitly requested.

## Mirror not-intended badges in student manual catalog
In the student V2 manual timetable’s “Alle Module” catalog, use the backend-provided `is_intended_for_selection` flag. Show “Nicht vorgesehen!” on each module where the flag is false, and on a non-empty group only when every contained module is false; do not recreate eligibility logic in Vue.

## Remove student adoption modules locally
On the student V2 adoption page, selected-module chips are closable. Removing one locally excludes all of its canonical course keys from both the transferred base timetable and manual additions, updates the summary, and makes those courses selectable again. Keep this transient and student-scoped; never mutate the generated source or call admin persistence.

## Show exact manual-dialog overlaps in red
In the student V2 manual timetable Unterricht dialog, map compact schedule rows to their entry keys. Under only the affected row, show each overlapping already placed or pending Unterricht label in parentheses using red #b42318 normal-weight text. Require intersecting concrete dates and overlapping time intervals; fall back to weekday/hour only when exact data is unavailable.

## Keep Neustart and Zurück on student adoption
On /students-timetables/create/adoption, show the same large outlined footer actions as the other creation steps: red Neustart on the left and primary Zurück on the right. Neustart clears transient planning state and returns to /students-timetables/overview; Zurück restores /students-timetables/create/results with workspace and fingerprint.

## Limit red dialog overlaps to the visible timetable
In the student manual Unterricht dialog, calculate red overlap labels only from canonical course-group keys in the currently displayed base timetable plus committed and pending manual additions. Published V3 selection metadata may restore module summaries and duplicate prevention, but must never create red overlap labels for courses absent from the opened timetable.

## Compare saved overlap entries directly
Saved personal and teacher timetables may contain synthetic entry keys that do not exist in the current catalog. For red Unterricht-dialog overlaps, compare the candidate's concrete date/time entries directly with the serialized visible timetable entries and use their stored display labels; use catalog keys only for committed or pending manual additions. This supersedes relying on visible-base course keys for saved overlap detection.

## Keep the local loader through page initialization
OverviewV2 must render its own local Stundenplan loader from the first render until authentication, overview loading, saved/generated timetable restoration, and committed manual-draft restoration have finished. Hide the page content until that initialization completes; keep the homepage-wide loader disabled for student timetable routes.

## Restore saved-source manual additions after reload
Personal or teacher-published adoption URLs have no calculation workspace query. Derive a stable student draft workspace UUID from the saved V3 manual-draft fingerprint and source marker, reuse its fingerprint/key/index for the existing scoped state endpoint, and restore the committed manual draft after initializing the exact saved base. Keep the local loader visible until restoration finishes.

## Use the shared three-dot initial loader
OverviewV2 keeps its page content hidden during initial loading and renders the existing shared LoadingAnimation dots locally. Do not replace it with a circular loader or re-enable the homepage-wide overlay for student timetable routes.
