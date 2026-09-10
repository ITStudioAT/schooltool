---
paths:
  - app/Services/AbaLocalDocumentTextExtractor.php
  - 'app/Services/PersonalTeachingBackup*.php'
  - 'app/Services/TeachingBackupArchive*.php'
---

# Services

## Do not install document extractors at runtime
Mammoth is optional and may be invoked only when already installed. Never call npx --yes or another runtime network installer for an uploaded document; keep the local XML/PhpWord fallbacks deterministic.

## Keep personal teaching recovery isolated from school backups
Personal teaching snapshots span all schoolyears of one owner/current school and must never call school-wide TeachingBackupService restoration. Preserve the complete course graph, including orphan children left by legacy course deletion, and reject foreign incoming references before rebuilding with stable IDs. Shared student accounts remain unchanged; missing identities require administrator-approved remapping. Download/import envelopes are authenticated and encrypted with the installation key; never silently promise recovery without that key.

## Keep teaching archives compatible with disabled tmpfile
Cloudways production disables tmpfile(), causing school teaching backup creation and attachment restoration to fail. Use tempnam() plus fopen() for disk-backed streams; register paths before opening and close/unlink them in finally. Keep writer temporary paths until ZIP finalization. TeachingBackupArchiveTest runs archive round-trip and failure cleanup in a child PHP process with disable_functions=tmpfile because PHPUnit itself needs tmpfile().
