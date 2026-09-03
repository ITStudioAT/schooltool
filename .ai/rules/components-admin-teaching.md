---
paths:
  - '{resources/js/pages/admin/teaching/settings/components/Entries.vue,tests/ui/unit/components/admin/teaching/EntriesSettings.test.ts}'
---

# Components Admin Teaching

## Manage grading-part assignments in one selector
In Berechnung, the action is labelled “Zuordnung”. Show all Benotung entries, preselect entries assigned to the active Benotungsteil, and save selection/deselection (including moves from another part) in that workflow. Do not render a separate row-level unlink action.

## Scope entry-category query to Entries
Only persist entry_category while panel=entries. Leaving Entries must remove it without the mounted Entries component restoring it; returning to Entries restores and persists the selected category.
