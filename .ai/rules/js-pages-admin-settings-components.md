---
paths:
  - '{app/Services/FeaturePreviewService.php,app/Http/Controllers/Admin/FeaturePreviewController.php,resources/js/pages/admin/settings/components/PreviewAccess.vue,tests/**/*Preview*}'
---

# Js Pages Admin Settings Components

## Scope preview account management to the selected school
Show only accounts whose school_id matches the signed-in super admin's selected school. Reject direct updates to users of another school, and keep all management responses school-scoped. Individual grants remain per account; the central preview enabled switch still applies to all schools and must be labeled accordingly.
