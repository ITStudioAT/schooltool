---
paths:
  - '{app/Services/TeachingCourseDateService.php,resources/js/pages/admin/teaching/overview/components/CourseTable.vue}'
  - '{app/Services/TeachingWorkMarkdownImport.php,resources/js/pages/admin/teaching/overview/components/WorkEvaluationImport.vue}'
---

# Teaching Overview Components

## Allow curriculum file sharing before manual unit linkage
The compact per-file visibility switch remains available for unlinked units. Enabling auto-creates the unit's dated material placeholder within the same transaction as copying only the requested attachment; disabling an absent copy is a no-op. Keep the course/curriculum guards and roll back the auto-link if copying fails. The UI refreshes planning and visibility from the saved date.

## Support the surname-first evaluation report variant with verified name boundaries
Keep directory selection and explicit preview/apply for evaluation imports. Include Gesamtübersicht.md/pdf alongside the existing overview filenames. The three-field fach variant is surname-first Name / Klasse | Fach | Datum: verify that report name equals surname_firstname filename parts in order, then use those explicit underscore boundaries to build the course's firstname surname identity. Never guess by splitting multiword names or accept both orders opportunistically. Recognize the overview's Maximal N Punkte: and consistent offen cells plus the detail's Keine abschließende Gesamtsumme marker; keep open values/comments unchanged and retain complete overview/detail, unique course identity and point consistency validation.
