---
paths:
  - 'app/Jobs/ImportTeachersListJob.php,tests/Unit/ImportTeachersListJobTest.php'
  - 'app/Jobs/ImportTeachersListJob.php,tests/Unit/ImportTeachersListHeadersTest.php'
---

# Jobs Unit

## Normalize imported teacher emails to lowercase
Treat teacher-list email matching case-insensitively and persist imported teacher emails in lowercase. Re-importing an uppercase address must update the existing user or preregistration record without creating a duplicate.

## Assign teacher role to registered import matches
When a teacher-list import matches an existing same-school user, update their imported name, short code, and email, mark the account active and roster-listed, and assign the teacher role even if it was previously absent. Omitted non-admin teacher users remain inactive.

## Preserve short codes when the import omits the column
Kurz/Kürzel is optional in teacher-list imports. If the whole column is absent, keep an existing Teacher or User short code unchanged and store NULL for a new Teacher. If the column exists, its row value remains authoritative, including an explicitly blank value.

## Teacher imports now create accounts and preserve source identities
Teacher-list imports now create missing same-school User accounts by normalized email and assign teacher, superseding the preregistration-only behavior. New accounts follow teacher creation defaults (active, confirmed, verified) with a securely random hashed password; existing credentials, verification state, other roles, and schoolyear remain unchanged. Retain and synchronize existing Teacher source IDs because class-head assignments and groups reference them; new imported accounts also keep a source record. Report created/updated account counts without double-counting source records.

## Recognize Kurzzeichen in teacher exports
The supported short-code column names include Kurzzeichen, alongside Kurz and Kürzel. School XLSX exports use Nachname/Vorname/Kurzzeichen/E-Mail; treat Kurzzeichen as the authoritative short code, including blank values, rather than silently treating the optional column as absent. Keep header parsing regression tests independent of database migrations.
