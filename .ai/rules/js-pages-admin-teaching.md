---
paths:
  - resources/js/pages/admin/teaching/Teaching.vue
  - 'resources/js/pages/admin/teaching/**'
---

# Js Pages Admin Teaching

## Use the shared menu design and align teaching Admin right
The teaching main navigation matches the Settings/Materials 2 Admin pattern: dark slate bar, primary v-btn-toggle, subtle dividers, icons, and two-line metadata badges. Supersedes the adjacent-Admin placement: Admin remains after Einstellungen in order but aligns to the far right, including narrow layouts. Preserve role visibility, navigation locking, and existing route handlers.

## Scope course stars by their course schoolyear
Stars are teaching_course_students metadata; their schoolyear is the owning course's schoolyear_id. A star's date may be its award/entry date (the backend supplies today if absent), so do not exclude course stars using schoolyear from/until. In the student overview retain the course/year guard and use the shared table semester comparison (before sem_2_start versus on/after) for dated stars; both semesters include all course stars. Keep truly undated stars separate from exact single-semester counts.
