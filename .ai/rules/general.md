---
paths:
  - '**/*TimetableV3*'
---

# General

## Compact only equivalent adjacent schedule periods
Keep every underlying timetable group key and raw `schedule_labels`. `display_schedule_labels` may merge adjacent periods only when weekday, recurrence, exact appointment dates, block label, and instruction type are identical; gaps or differing series stay on separate lines.
