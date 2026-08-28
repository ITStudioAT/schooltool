---
paths:
  - app/Jobs/Teaching/Import116Job.php
---

# Jobs Teaching

## Finalize unexpected import failures safely
Any unexpected Import116 parser or worker failure must finalize the active import run as failed, preserve available semantic analysis metadata, and expose only a generic user-safe failure message rather than the raw exception.
