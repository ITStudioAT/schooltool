---
paths:
  - '{app/Services/FeaturePreviewRuntimeService.php,tests/Feature/FeaturePreviewRuntimeTest.php}'
---

# App Services Feature

## Allow only captured preview SQL connections before PDO opens
Preview runtime permits only its captured configured default database. No preview_control or other live SQL credentials may remain; config records a boolean for legacy control credentials so removing the old connection does not hide unsafe environment values. Guard named and dynamically built/connectUsing connections at connector resolution before PDO opens, reject unknown names and purge stale forbidden connections. A ConnectionEstablished event alone is too late. Outbound HTTP permits only signed POST requests to the exact configured HTTPS control endpoint with verified TLS, redirects/proxies disabled and short timeouts; all other integration guards remain active.
