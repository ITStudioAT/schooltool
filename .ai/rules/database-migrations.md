---
paths:
  - 'database/migrations/*sepa*.php'
---

# Database Migrations

## Remove legacy SEPA JSON checks before encryption
Imported MySQL/MariaDB schemas can retain json_valid(child_entries) after changing JSON to TEXT, which rejects encrypted child entries. Keep the prerequisite cleanup migration ordered before the 2026_07_27 widening/encryption migrations; remove only that exact legacy check and preserve all other constraints. Existing databases use pending repair migrations; never repair schema or execute DDL inside a synchronization request.
