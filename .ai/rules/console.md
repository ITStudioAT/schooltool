---
paths:
  - '{app/Services/CloudwaysApiClient.php,app/Console/Commands/CloudwaysPullCommand.php,tests/Feature/Console/CloudwaysPullCommandTest.php,scripts/pdeploy_cloudways.sh}'
---

# Console

## Enforce main in effective Cloudways pull configuration
Cloudways API deployment must accept exactly services.cloudways.deployment.branch === 'main' before either history/preflight or POST /git/pull. Check Laravel config(), not env(), because production config may be cached. Reject an invalid branch before pdeploy enters maintenance; retain regressions for direct client calls and a cached feature branch despite a main process environment.
