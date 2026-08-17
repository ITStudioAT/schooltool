---
paths:
  - 'resources/views/pdfs/students-timetable-overview.blade.php,tests/Feature/StudentsTimetablesModuleTest.php'
---

# Pdfs Feature

## Expand sparse manual PDF timetable rows
For manual timetable PDF page 2, keep the compact base height at 15 rows. When fewer than 15 booked hour rows are present, increase row height from the available page space, capped at 20mm so sparse timetables are easier to read without creating oversized single rows.

## Enlarge manual PDF typography without Saturday
When the manual PDF omits the Saturday column, slightly increase timetable body, header, detail, and course-label font sizes. Keep the compact font sizes whenever Saturday is present so all six weekday columns remain readable.

## Avoid duplicate manual PDF timetable heading
On manual timetable PDF page 2, render the main page title and metadata only. Suppress the semester label so “Stundenplan” is not repeated immediately above the table; keep semester headings for non-manual overview PDFs.

## Enlarge manual PDF hour and time labels
On manual V3 timetable PDF page 2, render the hour number and its from/until times with a dedicated 7.5pt font. Use 7pt when Saturday is present so all six weekday columns remain readable; keep compact course-detail typography unchanged.

## Keep manual summary time in the card heading
In the numbered manual PDF summary, render the shared period time once in the card heading after the hour and end the heading with a colon. Render each appointment as `TT.MM.` without year or repeated time. Keep the course identifier and title vertically aligned.

## Match the overlap summary header to the timetable
On the manual PDF overlap summary page, reuse the timetable page's `header`, `title`, and `meta` presentation. Title the page `Überschneidungen`. Do not render the former `Nummern- und Terminübersicht` title or the explanatory red-date legend.

## Append a weekly plan to manual PDFs
After the manual timetable and optional overlap summary, always append a page titled Wochenplan even when the posted course_overview option is false. Render hours with from-to time as rows and Monday-Friday as columns; add Saturday only when weekday index 5 contains a booked course. Each occupied cell shows the expanded course name and its details.

## Stack manual PDF courses vertically
This supersedes the two-column multi-course PDF rule. On manual timetable page 2, render multiple courses in the same cell vertically. In dense cells, keep each full course name and identifier together on one line so all entries fit; render recurrence and learning-mode labels together on one blue information line. Use the enlarged manual timetable typography for headings, periods, course names, identifiers, and details.

## Replace weekly plan with alphabetical subject overview
The timetable PDF must not render a Wochenplan page. Append a Fächerübersicht instead, sorted alphabetically by expanded subject name with columns Fachname, Kurzname, Tag, Stunde(n), and Zeit(en). Prefer the source subject abbreviation (for example BU or INF) as Kurzname; use the identifier prefix for full labels such as ENGLISCH 7 → E7.

## Compact consecutive subject overview periods
In the Fächerübersicht, merge consecutive booked hour numbers for the same subject and weekday into one time range from the first start to the last end. This also bridges normal timetable breaks, e.g. 08:00-08:45 plus 08:50-09:35 becomes 08:00 - 09:35; non-consecutive hours remain separate.

## Use 10pt and 8pt manual course names
Render full course names on manual timetable page 2 at 10pt for Monday-Friday PDFs and 8pt when Saturday is present. Allow long full names to wrap instead of clipping or ellipsizing them.

## Use 8pt and 7pt two-line manual course labels
This supersedes the prior 10pt/8pt course-name rule and any dense-cell inline title/identifier rule. On manual timetable page 2, render the full course name at 8pt for Monday-Friday and 7pt when Saturday is present. Always render the full name on line 1 and the canonical course identifier on line 2, including stacked multi-course cells.

## Append identifier subject numbers to expanded PDF names
When a known abbreviated subject label has no number but the identifier prefix has one, append that number to the expanded subject name in every PDF section. Examples: CH1-4A-KOW + CH → CHEMIE 1, BU2 + BU → BIOLOGIE 2, REV2 + REV → RELIGION EVANGELISCH 2. Keep the identifier unchanged and only transfer the number when both prefixes resolve to the same mapped subject.

## Center all manual PDF timetable cells
On manual timetable PDF page 2, center every header and body cell horizontally and vertically, including the Std. column and all weekday course cells. Keep the inner cell content height automatic with its existing max-height so vertical-align: middle can center single and stacked course content.
