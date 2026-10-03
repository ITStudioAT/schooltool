---
paths:
  - '{app/Services/RestaurantSynchronisationService.php,resources/js/pages/admin/restaurant/components/Synchronisation.vue,tests/Feature/Controllers/Admin/Restaurant/RestaurantSynchronisationControllerTest.php}'
---

# Restaurant

## Review restaurant conflicts before replacing local test data
The local LIVE-to-local Restaurant sync may clear an existing user's import116_id when the uniquely matched live account has no student link; count and explain these removals in the preview and apply them only after confirmation. Preserve student records, teaching fields, reverse student-account links and unrelated accounts/roles; switching to another student or account remains blocked. Gather independent schema, identity, relationship and file conflicts before issuing a preview token, and recheck them during apply.
