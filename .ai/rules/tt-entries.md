---
paths:
  - resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue
  - 'resources/js/pages/admin/studentsTimetables/ttEntries/**'
---

# Tt Entries

## Nest TT entry sub-items below their parent
Render the selected meta-course subject rows directly below the clicked TT entry in the left list with visible left indentation. Keep offers and concrete timetable entries in the adjacent detail area.

## Always show remembered TT entries
Show the “Gemerkte Einträge” section above the TT entries card even when it is empty. Use a clear empty state; show remembered-item actions only when entries exist.

## TT entries use the personal schoolyear
The TT entries overview title shows the authenticated user's personal schoolyear. Its subject settings, course groups, school hours, and remembered offers must all be loaded for that same personal schoolyear, even when the school's global active schoolyear differs.

## Use subject-plan labels for TT entry headings
TT entry group headings use the subject-plan code and subject name, consistently with their child modules (for example BE instead of KG, ME instead of MU, GW instead of GWB). Keep TT mapping keys and offer matching unchanged; imported-only subjects retain their fallback labels.
