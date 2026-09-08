---
paths:
  - '{resources/js/pages/admin/teaching/Teaching.vue,resources/js/pages/admin/teaching/admin/TeacherAdministration.vue}'
---

# Admin Teaching Admin

## Swap teaching and administration toolbars like Restaurant
In teaching administration, replace the normal teaching navigation at the same position with Lehrer, Import 116, Ferien, and Schulstunden plus a right-aligned Unterricht return. Follow Restaurant's navigation-slot pattern so TeacherAdministration remains the single owner of panel query/history and content. Return through handleNavigation('overview'); propagate editing locks to panel changes and the return button. Never show both toolbars together.
