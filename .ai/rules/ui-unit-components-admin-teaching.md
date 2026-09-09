---
paths:
  - '{resources/js/pages/admin/teaching/overview/components/CourseTable.vue,tests/ui/unit/components/admin/teaching/CourseTable.test.ts}'
---

# Ui Unit Components Admin Teaching

## Edit attendance from the entry-cell dialog
The Tabelle cell dialog keeps Schüler:in, Termin, and an Anwesend/Abwesend selector in one metadata row. Selection uses the existing attendance persistence path, and applyUpdatedCourseDate must refresh the dialog's courseDate so its selected state updates immediately.

## Keep existing cell-entry types immutable
When a manual entry is opened from a Tabelle student/date cell, render its type as a read-only chip. Type selection remains available only when creating a new entry; selectCellEntryType must return immediately for forms with an existing entry id.

## Lock informed notification recipients
In an existing Tabelle cell entry, recipients with informed_at stay selected and their notification checkbox is disabled. Preserve informed recipients in the selected-key list even if recipient.available later becomes false.

## Delete entries from their summary row
In the Tabelle student/date entry dialog, render a delete icon directly in every existing entry summary row. Stop click propagation so deletion does not toggle the row, use the persistent confirmation dialog, and keep course-work-derived entries visibly disabled because they must be managed through the originating Arbeit.

## Show the current attendance state explicitly
In the Tabelle student/date entry dialog, do not communicate attendance only through the two action-button colors. Show a high-contrast status chip reading Aktuell anwesend or Aktuell abwesend, then label the outlined/filled buttons as controls for changing that state.

## Leave unchecked attendance blank
Attendance is a sparse map of explicit absences; a missing student key is not a confirmed presence. Show and count explicit absences, and infer presence only when the whole date has attendance_checked=true (legacy att_checked:1 only when the boolean is absent). Unchecked missing values remain blank, including the entry dialog current-state chip, and are excluded from percentage denominators. A dedicated empty attendance map or attendance_checked=false overrides legacy status flags. Keep the raw sparse-map toggle semantics separate from display state.

## Persist three individual attendance states
Supersedes the earlier sparse-absence-only storage assumption. The existing attendance JSON map retains canonical student keys with true=present, false=absent, null=explicitly unchecked; an own key always wins over attendance_checked. Only a missing key inherits whole-date checked=true as present, otherwise it is unknown. Cell clicks cycle null→false→true→null and cover the entire cell once; entries view shows X/check only for known states. Whole-date check changes preserve individual overrides. The separate confirmed reset action sends attendance={} and attendance_checked=false for one date only; its dialog stays persistent until explicit cancel or successful confirmation. Unknown states are excluded from percentages.

## Fetch protected curriculum files through the session client
API navigation with noreferrer loses the Referer/Origin Sanctum needs for stateful session detection and can redirect to login. Fetch curriculum downloads/previews with the configured Axios client, credentials and JSON error responses before creating local blobs; preserve server authorization. Reuse CurriculumPdfPreview for PDF blobs and a sandboxed iframe without scripts/same-origin permissions for other previews, so converting a protected response to a blob does not lose its isolation.
