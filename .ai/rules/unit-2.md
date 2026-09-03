---
paths:
  - 'composer.json,tests/Unit/QueueTimeoutConfigurationTest.php'
---

# Unit 2

## Reload local import code for each queued job
The local imports queue uses queue:listen so source changes are loaded for each job. A persistent queue:work process can report successful imports while still running an older account-creation implementation. When replacing a running local import worker, pause its queue and verify there are no reserved jobs before stopping it, then resume with the replacement running. Keep production workers unchanged.
