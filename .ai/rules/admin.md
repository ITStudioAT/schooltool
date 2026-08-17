---
paths:
  - 'resources/js/pages/admin/App.vue,tests/ui/unit/components/admin/AdminAppStartup.test.ts'
---

# Admin

## Keep global admin loading non-blocking
Represent adminStore.is_loading with a thin indeterminate progress bar at the top of v-main. Do not cover the admin page with the centered LoadingAnimation overlay; page-specific loaders may remain where they explain a concrete operation.
