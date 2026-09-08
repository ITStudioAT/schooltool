---
paths:
  - '{resources/js/pages/admin/auth/Login.vue,resources/js/pages/admin/App.vue,resources/js/stores/admin/AdminStore.js}'
---

# Stores Admin

## Discard the previous document when admin authentication changes
Completed password/code login, explicit logout, and impersonation transitions must use full document navigation, not Vue Router navigation or a config refresh in the old document. Teaching and other Pinia stores cache private account data; changing config.user while retaining these stores can expose the previous teacher's courses. Do not await loadConfig after an impersonation session change; callers navigate immediately on success.
