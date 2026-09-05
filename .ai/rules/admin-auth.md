---
paths:
  - '{resources/js/pages/admin/auth/Login.vue,resources/js/pages/admin/auth/UnknownPassword.vue}'
---

# Admin Auth

## Open admin immediately after successful code login
After confirmed email-code, authenticator, or recovery-code login, navigate directly with window.location.replace('/admin'). Do not await loadConfig or reuse SPA authentication state first: pending/failed guest config requests can stall navigation or redirect back to login and trigger logout. Pending factors and failed codes must stay on their forms.
