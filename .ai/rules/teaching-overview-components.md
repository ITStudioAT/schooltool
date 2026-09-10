---
paths:
  - '{app/Services/TeachingCourseDateService.php,resources/js/pages/admin/teaching/overview/components/CourseTable.vue}'
---

# Teaching Overview Components

## Allow curriculum file sharing before manual unit linkage
The compact per-file visibility switch remains available for unlinked units. Enabling auto-creates the unit's dated material placeholder within the same transaction as copying only the requested attachment; disabling an absent copy is a no-op. Keep the course/curriculum guards and roll back the auto-link if copying fails. The UI refreshes planning and visibility from the saved date.
