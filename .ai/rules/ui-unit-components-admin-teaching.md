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
